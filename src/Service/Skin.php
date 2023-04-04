<?php

namespace CILogon\Service;

use CILogon\Service\DBProps;
use CILogon\Service\Util;
use tubalmartin\CssMin\Minifier as CSSmin;
use PEAR;
use DB;

/**
 * Skin
 *
 * This class reads in CSS and configuration options
 * for a 'skin'. The skin is set by passing the
 * '?skin=...' (or '?cilogon_skin=...') query parameter.
 * If found, the associated config XML and CSS are read
 * in from either the filesystem (under the /skin/NAME
 * directory) or the database. It also sets a PHP
 * session variable so that the skin name is remembered
 * across page loads. If no skin name is found, then the
 * default /skin/config.xml file is read in.
 *
 * Note that this class uses the SimpleXML class to parse
 * the config XML. This stores the XML in a special
 * SimpleXMLElement object, which is NOT an array. But
 * you can iterate over elements in the structure. See
 * the PHP SimpleXML online manual 'Basic Usage' for
 * more information.
 *
 * This class provides a getConfigOption() method to
 * access XML (sub)blocks to get at a config value.
 * It is important to rememeber that the values returned
 * by the getConfigOption() method must be typecast to
 * native datatypes in order to be used effectively.
 *
 * An example configuration file (with all available options) is at
 *     /var/www/html/skin/config-example.xml
 *
 * Example usage:
 *   require_once 'Skin.php';
 *   $skin = new Skin();
 *   // While outputting the <head> HTML block...
 *   $skin->printSkinCSS();
 *   // Get the value of a configuration option
 *   $idpgreenlit = $skin->getConfigOption('idpgreenlit');!!
 *   // Now, process entries in the $idpgreenlit list
 *   if ((!is_null($idpgreenlit)) && (!empty($idpgreenlit->idp))) {
 *       foreach ($idpgreenlit->idp as $entityID) {
 *           echo '<p>' , (string)$entityID , "<\p>\n";
 *       }
 *   }
 *   // Check to see if <hideportalinfo> is set
 *   $hideportalinfo = false;
 *   $hpi=$skin->getConfigOption('portallistaction','hideportalinfo');
 *   if ((!is_null($hpi)) && ((int)$hpi > 0)) {
 *       $hideportalinfo = true;
 *   }
 */
class Skin
{
    /**
     * @var string $skinname The name of the skin
     */
    protected $skinname;

    /**
     * @var \SimpleXMLElement $configxml A SimpleXMLElement object for the
     *      config.xml file
     */
    protected $configxml;

    /**
     * @var string $css The un-minified CSS for the skin
     */
    protected $css;

    /**
     * @var array $forcearray An array of (URI,skinname) pairs for forcing
     *      skin application
     */
    protected $forcearray;

    /**
     *  __construct
     *
     * Default constructor. Calls init() to do the actual work.
     *
     * @return Skin A new Skin object.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * init
     *
     * This function does the work of (re)initializing the skin object.
     * It finds the name of the skin (if any) and reads in the skin's
     * config XML file (if found). Call this function to reset the
     * skin in case of possible forced skin due to IdP or portal
     * callbackURL.
     *
     * @param bool $reset True to reset the 'cilogon_skin' PHP session var
     *        to blank so that readSkinConfig doesn't check for it.
     *        Defaults to 'false'.
     */
    public function init($reset = false)
    {
        if ($reset) {
            Util::unsetSessionVar('cilogon_skin');
        }

        $this->skinname = '';
        $this->configxml = null;
        $this->css = '';
        $this->forcearray = Util::getBypass()->getForceSkinArray();
        $this->readSkinConfig();
        $this->setMyProxyInfo();
    }

    /**
     * readSkinConfig
     *
     * This function checks for the name of the skin in several places:
     * (1) The FORCE_SKIN_ARRAY (if defined) or ciloa2.bypass database table
     * (where 'type'='skin') for a matching IdP entityID or portal
     * callbackURL, (2) in a URL parameter (can be '?skin=',
     * '?cilogon_skin=', '?vo='), (3) cilogon_vo form input variable
     * (POST for ECP case), or (4) 'cilogon_skin' PHP session
     * variable. If it finds the skin name in any of these, it then
     * checks to see if such a named skin exists, either on the filesystem
     * or in the database. If found, the class variable $skinname AND the
     * 'cilogon_skin' PHP session variable (for use on future page
     * loads by the user) are set. It then reads in the config XML and the
     * CSS and stores them in the class variables $configxml and $css.
     * If no skin name is found, read in the default /skin/config.xml file.
     *
     * Side Effect: Sets the 'cilogon_skin' session variable if needed.
     */
    protected function readSkinConfig()
    {
        $skinvar = '';

        // Check for matching IdP, callbackURI (OAuth1),
        // redirect_uri (OAuth2), or client_id (OAuth2)
        // in the FORCE_SKIN_ARRAY or ciloa2.bypass database table
        // (where 'type' = 'skin').
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        $uristocheck = array(
            Util::getGetVar('redirect_uri'),
            Util::getGetVar('client_id'),       // $idx == 1
            @$clientparams['client_id'],        // $idx == 2
            Util::getSessionVar('callbackuri'),
            Util::getSessionVar('idp'),
        );

        $idx = 0;
        foreach ($uristocheck as $value) {
            if (strlen($value) > 0) {
                // For 'client_id', check if there is a matching admin client
                $skin = $this->getForceSkin($value, (($idx == 1) || ($idx == 2)));
                if (strlen($skin) > 0) {
                    $skinvar = $skin;
                    break;
                }
            }
            $idx++;
        }

        // If no force skin found, check GET and POST parameters, as well as
        // previously set cilogon_skin PHP session variable.
        if (strlen($skinvar) == 0) {
            // First, look for '?skin=...'
            $skinvar = Util::getGetVar('skin');
        }
        if (strlen($skinvar) == 0) {
            // Next, look for '?cilogon_skin=...'
            $skinvar = Util::getGetVar('cilogon_skin');
        }
        if (strlen($skinvar) == 0) {
            // Next, look for '?vo=...'
            $skinvar = Util::getGetVar('vo');
        }
        if (strlen($skinvar) == 0) {
            // Next, check 'cilogon_vo' form input variable
            $skinvar = Util::getPostVar('cilogon_vo');
        }
        if (strlen($skinvar) == 0) {
            // Finally, check 'cilogon_skin' PHP session variable
            $skinvar = Util::getSessionVar('cilogon_skin');
        }

        // If we found $skinvar, attempt to read the skin config/css from
        // either the filesystem or the database, or failing that, read the
        // default /skin/config.xml file.
        $skinvar = strtolower($skinvar); // All skin dirs are lowercase
        $this->readSkinFromFile($skinvar) ||
            $this->readSkinFromDatabase($skinvar) ||
            $this->readDefaultSkin();
    }

    /**
     * readSkinFromFile
     *
     * This function reads in the skin config XML and CSS from the
     * filesystem into the class variables $configxml and $css.
     *
     * @param string $skinvar The name of the skin as found by
     *        readSkinConfig().
     * @return bool True if at least one of config.xml or skin.css could
     *         be found (and read in) for the skin (i.e., the skin
     *         directory exists and isn't empty). False otherwise.
     */
    protected function readSkinFromFile($skinvar)
    {
        $readin = false; // Make sure we read in either XML or CSS (or both)

        if (strlen($skinvar) > 0) {
            $skindir = Util::getServerVar('DOCUMENT_ROOT') . "/skin/$skinvar";
            if (is_dir($skindir)) {
                // Read in the config XML
                $skinconf = $skindir . '/config.xml';
                if (is_readable($skinconf)) {
                    if (($xml = @simplexml_load_file($skinconf)) !== false) {
                        $this->configxml = $xml;
                        $readin = true;
                    }
                }
                //Read in the CSS
                $skincss = $skindir . '/skin.css';
                if (is_readable($skincss)) {
                    if (($css = file_get_contents($skincss)) !== false) {
                        $this->css = $css;
                        $readin = true;
                    }
                }
            }
        }

        if ($readin) {
            $this->skinname = $skinvar;
            Util::setSessionVar('cilogon_skin', $skinvar);
        } else {
            Util::unsetSessionVar('cilogon_skin');
        }

        return $readin;
    }

    /**
     * readSkinFromDatabase
     *
     * This function reads in the skin config XML and CSS from the
     * database into the class variables $configxml and $css.
     *
     * @param string $skinvar The name of the skin as found by
     *        readSkinConfig().
     * @return bool True if at least one of config XML or CSS could
     *         be read from the database for the skin. False otherwise.
     */
    protected function readSkinFromDatabase($skinvar)
    {
        $readin = false; // Make sure we read in either XML or CSS (or both)

        if (strlen($skinvar) > 0) {
            $dbprops = new DBProps('mysqli');
            $db = $dbprops->getDBConnect();
            if (!is_null($db)) {
                $data = $db->getRow(
                    'SELECT * from skins WHERE name = ?',
                    array($skinvar),
                    DB_FETCHMODE_ASSOC
                );
                if ((!DB::isError($data)) && (!empty($data))) {
                    // Read in the config XML
                    if (
                        (strlen(@$data['config']) > 0) &&
                        (($xml = @simplexml_load_string($data['config'])) !== false)
                    ) {
                        $this->configxml = $xml;
                        $readin = true;
                    }
                    //Read in the CSS
                    if (strlen(@$data['css']) > 0) {
                        $this->css = $data['css'];
                        $readin = true;
                    }
                }
                $db->disconnect();
            }
        }

        if ($readin) {
            $this->skinname = $skinvar;
            Util::setSessionVar('cilogon_skin', $skinvar);
        } else {
            Util::unsetSessionVar('cilogon_skin');
        }

        return $readin;
    }

    /**
     * readDefaultSkin
     *
     * If we don't read a skin from the filesystem or the database, then
     * read in the default "/skin/config.xml" file.
     *
     * @return bool True if the default config.xml file was read in.
     *         False otherwise.
     */
    protected function readDefaultSkin()
    {
        $readin = false;

        $this->skinname = '';
        $this->css = '';
        Util::unsetSessionVar('cilogon_skin');

        $skinconf = Util::getServerVar('DOCUMENT_ROOT') . '/skin/config.xml';
        if (is_readable($skinconf)) {
            if (($xml = @simplexml_load_file($skinconf)) !== false) {
                $this->configxml = $xml;
                $readin = true;
            }
        }

        return $readin;
    }

    /**
     * getConfigOption
     *
     * This method returns a SimpleXMLElement block corresponding to
     * the passed in arguments. For example, to get the redlit list of
     * idps, call $idps = getConfigOption('idpredlit') and then
     * iterate over $idps with foreach($idps as $idp) { ... }. To get
     * a single subblock value such as the initial lifetime number for
     * the PKCS12 download option, call $life =
     * (int)getConfigOption('pkcs12','initiallifetime','number'). Note
     * that you should explicitly cast the values to int, string,
     * float, etc., when you use them.
     *
     * @param mixed $args Variable number of parameters corresponding to XML
     *              blocks (and possible sub-blocks).
     * @return \SimpleXMLElement|null A SimpleXMLElement corresponding to the
     *         passed-in XML option, or 'null' if no such option exists.
     */
    public function getConfigOption(...$args)
    {
        $retval = null;
        $numargs = count($args);
        if ($numargs > 0) {
            $retval = $this->configxml;
        }
        for ($i = 0; $i < $numargs; $i++) {
            $argval = $args[$i];
            if (empty($retval->$argval)) {
                $retval = null;
                break;
            } else {
                $retval = $retval->$argval;
            }
        }
        return $retval;
    }

    /**
     * printSkinCSS
     *
     * Call this function in the HTML <head> block to print out the
     * <style> tag for the internal CSS of the skin. The CSS is minified
     * to remove whitespace. Note that you should call readSkinConfig
     * to set the contents of $css.
     */
    public function printSkinCSS()
    {
        if (strlen($this->css) > 0) {
            $cssmin = new CSSmin();
            $cssmin->removeImportantComments();
            $cssmin->setLineBreakPosition(255);
            $outputcss = $cssmin->run($this->css);
            echo "<style>$outputcss</style>";
        }
    }

    /**
     * hasGreenlitIdps
     *
     * This function checks for the presence of a <idpgreenlit> block
     * in the skin's config file. There must be at least one <idp>
     * in the <idpgreenlit>.
     *
     * @return bool True if skin has a non-empty <idpgreenlit>.
     */
    public function hasGreenlitIdps()
    {
        $retval = false;  // Assume no <idpgreenlit> configured
        $idpgreenlit = $this->getConfigOption('idpgreenlit');
        if ((!is_null($idpgreenlit)) && (!empty($idpgreenlit->idp))) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * hasRedlitIdps
     *
     * This function checks for the presence of a <idpredlit> block
     * in the skin's config file. There must be at least one <idp>
     * in the <idpredlit>.
     *
     * @return bool True if skin has a non-empty <idpredlit>.
     */
    public function hasRedlitIdps()
    {
        $retval = false;  // Assume no <idpredlit> configured
        $idpredlit = $this->getConfigOption('idpredlit');
        if ((!is_null($idpredlit)) && (!empty($idpredlit->idp))) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * hasGreenlitRegAuths
     *
     * This function checks for the presence of a <regauthgreenlit> block
     * in the skin's config file. There must be at least one <regauth>
     * in the <regauthgreenlit>.
     *
     * @return bool True if skin has a non-empty <regauthgreenlit>.
     */
    public function hasGreenlitRegAuths()
    {
        $retval = false;  // Assume no <regauthgreenlit> configured
        $regauthgreenlit = $this->getConfigOption('regauthgreenlit');
        if ((!is_null($regauthgreenlit)) && (!empty($regauthgreenlit->regauth))) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * hasRedlitRegAuths
     *
     * This function checks for the presence of a <regauthredlit> block
     * in the skin's config file. There must be at least one <regauth>
     * in the <regauthredlit>.
     *
     * @return bool True if skin has a non-empty <regauthredlit>.
     */
    public function hasRedlitRegAuths()
    {
        $retval = false;  // Assume no <regauthredlit> configured
        $regauthredlit = $this->getConfigOption('regauthredlit');
        if ((!is_null($regauthredlit)) && (!empty($regauthredlit->regauth))) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * idpGreenlit
     *
     * This method checks to see if a given entityId of an IdP
     * is greenlit. 'Greenlit' in this case means either (a) the
     * entityId is in the skin's <idpgreenlit> list or (b) the skin
     * doesn't have a <idpgreenlit> at all. In the second case, all
     * IdPs are by default 'greenlit'. If you want to find if an
     * IdP should be listed in the WAYF, use 'idpAvailable' which
     * checks the greenlit AND the redlit lists.
     *
     * @param string $entityId The entityId of an IdP to check for greenlit.
     * @return bool True if the given IdP entityId is in the skin's
     *         greenlit list (or if the skin doesn't have a greenlit list).
     */
    public function idpGreenlit($entityId)
    {
        $retval = true;  // Assume the entityId is 'greenlit'
        if ($this->hasGreenlitIdps()) {
            $entityId = Util::normalizeOAuth2IdP($entityId);
            $idpgreenlit = $this->getConfigOption('idpgreenlit');
            $found = false;
            foreach ($idpgreenlit->idp as $greenidp) {
                $greenidp = Util::normalizeOAuth2IdP($greenidp);
                if ($entityId == ((string)$greenidp)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $retval = false;
            }
        }
        return $retval;
    }

    /**
     * idpRedlit
     *
     * This method checks to see if a given entityId of an IdP
     * appears in the skin's <idpredlit>.
     *
     * @param string $entityId The entityId of an IdP to check for
     *        redlit status.
     * @return bool True if the given IdP entityId is in the skin's
     *         redlit list.
     */
    public function idpRedlit($entityId)
    {
        $retval = false;  // Assume entityId is NOT in the idpredlit
        if ($this->hasRedlitIdps()) {
            $entityId = Util::normalizeOAuth2IdP($entityId);
            $idpredlit = $this->getConfigOption('idpredlit');
            foreach ($idpredlit->idp as $redidp) {
                $redidp = Util::normalizeOAuth2IdP($redidp);
                if ($entityId == ((string)$redidp)) {
                    $retval = true;
                    break;
                }
            }
        }
        return $retval;
    }

    /**
     * regAuthGreenlit
     *
     * This method checks to see if a given entityId has a RegAuth which is
     * greenlit. 'Greenlit' in this case means either (a) the entityId has a
     * RegAuth (Registration Authority) in the skin's <regauthgreenlit> list
     * or (b) the skin doesn't have a <regauthgreenlit> config option. In
     * the second case, all IdPs are by default 'greenlit' as far as RegAuth
     * is concerned.
     *
     * @param string $entityId The entityId of an IdP to check for a
     *        greenlit RegAuth.
     * @return bool True if the given IdP entityId has a RegAuth which is in
     *         the skin's <regauthgreenlit> list (or if the skin doesn't
     *         have a <regauthgreenlit> list). False otherwise.
     */
    public function regAuthGreenlit($entityId)
    {
        $retval = true;  // Assume the entityId has a 'greenlit' RegAuth
        if ($this->hasGreenlitRegAuths()) {
            $regauthgreenlit = $this->getConfigOption('regauthgreenlit');
            $found = false;
            $idplist = Util::getIdpList();
            $regAuthForEntityId = $idplist->getRegAuth($entityId);
            foreach ($regauthgreenlit->regauth as $greenregauth) {
                if ($regAuthForEntityId == ((string)$greenregauth)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $retval = false;
            }
        }
        return $retval;
    }

    /**
     * regAuthRedlit
     *
     * This method checks to see if a given entityId has a RegAuth
     * (Registration Authority) which appears in the skin's <regauthredlit>
     * list.
     *
     * @param string $entityId The entityId of an IdP to check for a
     *        redlit RegAuth.
     * @return bool True if the given IdP entityId has a RegAuth which is in
     *         the skin's <regauthredlit> list. False otherwise, or if the
     *         skin doesn't have a <regauthredlit> list.
     */
    public function regAuthRedlit($entityId)
    {
        $retval = false;  // Assume entityId does NOT have a 'redlit' RegAuth
        if ($this->hasRedlitRegAuths()) {
            $regauthredlit = $this->getConfigOption('regauthredlit');
            $idplist = Util::getIdpList();
            $regAuthForEntityId = $idplist->getRegAuth($entityId);
            foreach ($regauthredlit->regauth as $redregauth) {
                if ($regAuthForEntityId == ((string)$redregauth)) {
                    $retval = true;
                    break;
                }
            }
        }
        return $retval;
    }

    /**
     * idpAvailable
     *
     * This method combines idpGreenlit and idpRedlit to return
     * a 'yes/no' for if a given IdP should be made available for
     * selection in the WAYF. It first checks to see if the IdP is
     * greenlit. If not, it returns false. Otherwise, it then
     * checks if the IdP is redlit. If not, it returns true.
     *
     * @param string $entityId The entityId of an IdP to check to see if it
     *        should be available in the WAYF.
     * @return bool True if the given IdP entityId is available to be
     *         selected in the WAYF.
     */
    public function idpAvailable($entityId)
    {
        $retval = false;   // Assume IdP is not available in the WAYF
        if (
            ($this->idpGreenlit($entityId)) &&
            (!$this->idpRedlit($entityId))
        ) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * setMyProxyInfo
     *
     * This method sets the 'myproxyinfo' PHP session variable. The
     * variable has the form 'info:key1=value1,key2=value2,...' and is
     * passed to the 'myproxy-logon' command as part of the username
     * when fetching a credential. The MyProxy server will do extra
     * processing based on the content of this 'info:...' tag. If the
     * skinname is not empty, that is added to the info tag. Also,
     * the apache REMOTE_ADDR is added. For other key=value pairs that
     * get added, see the code below.
     */
    public function setMyProxyInfo()
    {
        $infostr = '';

        // Add the skinname if available
        if (strlen($this->skinname) > 0) {
            $infostr .= 'cilogon_skin=' . $this->skinname;
        }

        // Add the REMOTE_ADDR
        $remoteaddr = Util::getServerVar('REMOTE_ADDR');
        if (strlen($remoteaddr) > 0) {
            $infostr .= (strlen($infostr) > 0 ? ',' : '') .
                        "remote_addr=$remoteaddr";
        }

        // Add eppn, eptid, open_id, and oidc if available
        // Note that these values are lowercase after an update to make
        // them the same as those used by the dbService. BUT, MyProxy
        // expects the old versions. So this array maps the new lowercase
        // versions back into the old ones.
        $mpid = array(
            'eppn' => 'ePPN',
            'eptid' => 'ePTID',
            'open_id' => 'openidID',
            'oidc' => 'oidcID'
        );
        foreach (array('eppn','eptid','open_id','oidc') as $id) {
            $sessvar = Util::getSessionVar($id);
            if (strlen($sessvar) > 0) {
                $infostr .= (strlen($infostr) > 0 ? ',' : '') .
                    $mpid[$id] . "=" . $sessvar;
            }
        }

        // Finally, set the 'myproxyinfo' PHP session variable
        if (strlen($infostr) > 0) {
            Util::setSessionVar('myproxyinfo', 'info:' . $infostr);
        } else {
            Util::unsetSessionVar('myproxyinfo');
        }
    }

    /**
     * hasPortalList
     *
     * This function checks for the presence of a <portallist> block in
     * the skin's config file. There must be at least one <portal> in
     * the <portallist>.
     *
     * @return bool True if skin has a non-empty <portallist>.
     */
    public function hasPortalList()
    {
        $retval = false;  // Assume no <portallist> configured
        $portallist = $this->getConfigOption('portallist');
        if ((!is_null($portallist)) && (!empty($portallist->portal))) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * inPortalList
     *
     * This function takes in a 'callback' URL of a portal passed to
     * the CILogon Delegate service. It then looks through the list
     * of <portal> patterns in the skin's <portallist>. If the
     * callback URL matches any of these patterns, true is returned.
     * This is used to hide the 'Site Name / Site URL / Service URL'
     * box on the delegation WAYF page, for example.
     *
     * @param string $portalurl A 'callback' URL of a portal accessing the
     *        delegate service.
     * @return bool True if the callback URL matches one of the patterns
     *         in the skin's <portallist>. False otherwise.
     */
    public function inPortalList($portalurl)
    {
        $retval = false;  // Assume the portalurl not a listed <portal>
        if ($this->hasPortalList()) {
            $portallist = $this->getConfigOption('portallist');
            foreach ($portallist->portal as $portalmatch) {
                if (preg_match(((string)$portalmatch), $portalurl)) {
                    $retval = true;
                    break;
                }
            }
        }
        return $retval;
    }

    /**
     * getForceSkin
     *
     * The $forcearray contains 'uripattern' => 'skinname' pairs
     * corresponding to IdP entityIDs, portal callbackurls, or client_ids
     * that should have a particular skin force-applied. This function
     * looks in the $forcearray for a pattern-matched URI and returns
     * the corresponding skin name if found. If not found, empty
     * string is returned.
     *
     * @param string $uri A URI to search for in the $forcearray.
     * @param bool $checkadmin If true, see if the $uri matches an admin
     *        client. Defaults to false. Use when the $uri is a 'client_id'.
     * @return string The skin name for the URI, or empty string if not
     *         found.
     */
    protected function getForceSkin($uri, $checkadmin = false)
    {
        $retval = '';  // Assume uri is not in $forcearray

        foreach ($this->forcearray as $key => $value) {
            if (
                ($key === $uri) ||
                (@preg_match($key, $uri)) ||
                ($checkadmin &&
                    (($key === @(Util::getAdminForClient($uri))['admin_id']) ||
                    (@preg_match($key, @(Util::getAdminForClient($uri))['admin_id'])))
                )
            ) {
                $retval = $value;
                break;
            }
        }
        return $retval;
    }

    /**
     * getHiddenIdPs
     *
     * CIL-1632 - Green light IdPs, but do not show them, but still allow
     * admin access using "idphint=entityId" query parameter.
     *
     * This is a convenience function to return the list of "hidden" IdPs
     * for the skin. In this case "hidden" means that the IdP should have
     * the 'hidden="hidden"' attribute set on the <option> tag when
     * generating the "Select an Identity Provider" list. This means that
     * the hidden IdP is technically still in the list of IdPs (so it
     * passes "greenlit" checks to see if the IdP is allowed), but the
     * <option> does not get displayed to the user. A skin can be configured
     * to "green light" an IdP, but hide it from most users, allowing it
     * to be selected by specifying the "idphint=entityId" query parameter.
     */
    public function getHiddenIdPs()
    {
        $hiddenidps = array();
        $idphidden = $this->getConfigOption('idphidden');
        if ((!is_null($idphidden)) && (!empty($idphidden->idp))) {
            foreach ($idphidden->idp as $hiddenidp) {
                $hiddenidp = Util::normalizeOAuth2IdP($hiddenidp);
                $hiddenidps[] = $hiddenidp;
            }
        }
        return $hiddenidps;
    }

   /**
     * hiddenFormElement
     *
     * Returns an <input ...> form element of type 'hidden' with the
     * name of the skin. If there is no current skinname, return
     * empty string.
     *
     * @return string The string of an <input> HTML element, or
     *         empty string if skinname is blank.
     */
    public function hiddenFormElement()
    {
        $retval = '';
        if (strlen($this->skinname) > 0) {
            $retval = '<input type="hidden" name="skinname" id="skinname" ' .
                'value="' . $this->skinname . '" />';
        }
        return $retval;
    }
}
