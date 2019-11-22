<?php

namespace CILogon\Service;

use CILogon\Service\CSRF;
use CILogon\Service\Loggit;
use CILogon\Service\IdpList;
use CILogon\Service\DBService;
use CILogon\Service\SessionMgr;
use CILogon\Service\Skin;
use CILogon\Service\TimeIt;
use CILogon\Service\PortalCookie;
use \PEAR as PEAR;
use \Config as Config;

/**
 * Util
 *
 * This class contains a bunch of static (class) utility
 * methods, for example getting and setting server environment
 * variables and handling cookies. See the header for each function for
 * detailed description.
 */
class Util
{
    /**
     * @var array $ini_array Read the cilogon.ini file into an array
     */
    public static $ini_array = null;

    /**
     * @var TimeIt $timeit Initialize by calling static::startTiming() in
     * init().
     */
    public static $timeit;

    /**
     * @var IdPList $idplist A 'global' IdpList object since dplist.xml is
     *      large and expensive to create multiple times.
     */
    public static $idplist = null;

    /**
     * @var CSRF $csrf A 'global' CSRF token object to set the CSRF cookie
     * and print the hidden CSRF form element. Needs to be set only once
     * to keep the same CSRF value through the session.
     */
    public static $csrf = null;

    /**
     * @var Skin $skin A 'global' Skin object for skin configuration.
     */
    public static $skin = null;

    /**
     * @var array $oauth2idps An array of OAuth2 Identity Providers.
     */
    public static $oauth2idps = ['Google', 'GitHub', 'ORCID'];


    /**
     * getIdPList
     *
     * This function initializes the class $idplist object (if not yet
     * created) and returns it. This allows for a single 'global'
     * $idplist to be used by other classes (since creating an IdPList
     * object is expensive).
     *
     * @return IdPList The class instantiated IdPList object.
     **/
    public static function getIdpList()
    {
        if (is_null(static::$idplist)) {
            static::$idplist = new IdpList();
        }
        return static::$idplist;
    }

    /**
     * getCsrf
     *
     * This function initializes the class $csrf object (if not yet
     * created) and returns it. This allows for a single 'global'
     * $csrf to be used by other classes (since we want the CSRV value
     * to be consistent for the current page load).
     *
     * @return CSRF The class instantiated CSRF object.
     */
    public static function getCsrf()
    {
        if (is_null(static::$csrf)) {
            static::$csrf = new CSRF();
        }
        return static::$csrf;
    }

    /**
     * getSkin
     *
     * This function initializes the class $skin object (if not yet
     * created) and returns it. This allows for a single 'global'
     * $skin to be used by other classes (since loading the skin is
     * potentially expensive).
     *
     * @return The class instantiated Skin object.
     */
    public static function getSkin()
    {
        if (is_null(static::$skin)) {
            static::$skin = new Skin();
        }
        return static::$skin;
    }

    /**
     * startTiming
     *
     * This function initializes the class variable $timeit which is
     * used for timing/benchmarking purposes.
     */
    public static function startTiming()
    {
        static::$timeit = new TimeIt(TimeIt::DEFAULTFILENAME, true);
    }

    /**
     * getServerVar
     *
     * This function queries a given $_SERVER variable (which is set
     * by the Apache server) and returns the value.
     *
     * @param string $serv The $_SERVER variable to query.
     * @return string The value of the $_SERVER variable or empty string
     *         if that variable is not set.
     */
    public static function getServerVar($serv)
    {
        $retval = '';
        if (isset($_SERVER[$serv])) {
            $retval = $_SERVER[$serv];
        }
        return $retval;
    }

    /**
     * getGetVar
     *
     * This function queries a given $_GET parameter (which is set in
     * the URL via a '?parameter=value' parameter) and returns the
     * value.
     *
     * @param string $get The $_GET variable to query.
     * @return string The value of the $_GET variable or empty string if
     *         that variable is not set.
     */
    public static function getGetVar($get)
    {
        $retval = '';
        if (isset($_GET[$get])) {
            $retval = $_GET[$get];
        }
        return $retval;
    }

    /**
     * getPostVar
     *
     * This function queries a given $_POST variable (which is set when
     * the user submits a form, for example) and returns the value.
     *
     * @param string $post The $_POST variable to query.
     * @return string The value of the $_POST variable or empty string if
     *         that variable is not set.
     */
    public static function getPostVar($post)
    {
        $retval = '';
        if (isset($_POST[$post])) {
            $retval = $_POST[$post];
        }
        return $retval;
    }

    /**
     * getGetOrPostVar
     *
     * This function looks for a $_GET or $_POST variable, with
     * preference given to $_GET if both are present.
     *
     * @param string $var The $_GET or $_POST variable to query.
     * @return string The value of the $_GET or $_POST variable
     *         if present. Empty string if variable is not set.
     */
    public static function getGetOrPostVar($var)
    {
        $retval = static::getGetVar($var);
        if (empty($retval)) {
            $retval = static::getPostVar($var);
        }
        return $retval;
    }

    /**
     * getCookieVar
     *
     * This function returns the value of a given cookie.
     *
     * @param string $cookie he $_COOKIE variable to query.
     * @return string The value of the $_COOKIE variable or empty string
     *         if that variable is not set.
     */
    public static function getCookieVar($cookie)
    {
        $retval = '';
        if (isset($_COOKIE[$cookie])) {
            $retval = $_COOKIE[$cookie];
        }
        return $retval;
    }

    /**
     * setCookieVar
     *
     * This function sets a cookie.
     *
     * @param string $cookie The name of the cookie to set.
     * @param string $value (Optional) The value to set for the cookie.
     *        Defaults to empty string.
     * @param int $exp The future expiration time (in seconds) of the
     *        cookie. Defaults to 1 year from now. If set to 0,
     *        the cookie expires at the end of the session.
     */
    public static function setCookieVar($cookie, $value = '', $exp = 31536000)
    {
        if ($exp > 0) {
            $exp += time();
        }
        setcookie($cookie, $value, $exp, '/', '.' . static::getDN(), true);
        $_COOKIE[$cookie] = $value;
    }

    /**
     * unsetCookieVar
     *
     * This function unsets a cookie. Strictly speaking, the cookie is
     * not removed, rather it is set to an empty value with an expired
     * time.
     *
     * @param string $cookie The name of the cookie to unset (delete).
     */
    public static function unsetCookieVar($cookie)
    {
        setcookie($cookie, '', 1, '/', '.' . static::getDN(), true);
        unset($_COOKIE[$cookie]);
    }

    /**
     * getPortalOrNormalCookieVar
     *
     * This is a convenience function which first checks if there is a
     * OAuth 1.0a ('delegate') or OIDC ('authorize') session active.
     * If so, it attempts to get the requested cookie from the
     * associated portalcookie. If there is not an OAuth/OIDC session
     * active, it looks for a 'normal' cookie. If you need a
     * portalcookie object to do multiple get/set method calls from
     * one function, it is probably better NOT to use this method since
     * creating the portalcookie object is potentially expensive.
     *
     * @param string $cookie The name of the cookie to get.
     * @return string The cookie value from either the portalcookie
     *         (in the case of an active OAuth session) or the
     *         'normal' cookie. Return empty string if no matching
     *         cookie in either place.
     */
    public static function getPortalOrNormalCookieVar($cookie)
    {
        $retval = '';
        $pc = new PortalCookie();
        $pn = $pc->getPortalName();
        if (strlen($pn) > 0) {
            $retval = $pc->get($cookie);
        } else {
            $retval = static::getCookieVar($cookie);
        }
        return $retval;
    }

    /**
     * getSessionVar
     *
     * This function returns the value of a given PHP Session variable.
     *
     * @param string $sess The $_SESSION variable to query.
     * @return string The value of the $_SESSION variable or empty string
     *         if that variable is not set.
     */
    public static function getSessionVar($sess)
    {
        $retval = '';
        if (isset($_SESSION[$sess])) {
            $retval = $_SESSION[$sess];
        }
        return $retval;
    }

    /**
     * setSessionVar
     *
     * This function can set or unset a given PHP session variable.
     * The first parameter is the PHP session variable to set/unset.
     * If the second parameter is the empty string, then the session
     * variable is unset.  Otherwise, the session variable is set to
     * the second parameter.  The function returns true if the session
     * variable was set to a non-empty value, false otherwise.
     * Normally, the return value can be ignored.
     *
     * @param string $key The name of the PHP session variable to set
     *        (or unset).
     * @param string $value (Optional) The value of the PHP session variable
     *        (to set), or empty string (to unset). Defaults to empty
     *        string (implies unset the session variable).
     * @return bool True if the PHP session variable was set to a
     *         non-empty string, false if variable was unset or if
     *         the specified session variable was not previously set.
     */
    public static function setSessionVar($key, $value = '')
    {
        $retval = false;  // Assume we want to unset the session variable
        if (strlen($key) > 0) {  // Make sure session var name was passed in
            if (strlen($value) > 0) {
                $_SESSION[$key] = $value;
                $retval = true;
            } else {
                static::unsetSessionVar($key);
            }
        }
        return $retval;
    }

    /**
     * unsetSessionVar
     *
     * This function clears the given PHP session variable by first
     * setting it to null and then unsetting it entirely.
     *
     * @param string $sess The $_SESSION variable to erase.
     */
    public static function unsetSessionVar($sess)
    {
        if (isset($_SESSION[$sess])) {
            $_SESSION[$sess] = null;
            unset($_SESSION[$sess]);
        }
    }

    /**
     * removeShibCookies
     *
     * This function removes all '_shib*' cookies currently in the
     * user's browser session. In effect, this logs the user out of
     * any IdP. Note that you must call this before you output any
     * HTML. Strictly speaking, the cookies are not removed, rather
     * they are set to empty values with expired times.
     */
    public static function removeShibCookies()
    {
        foreach ($_COOKIE as $key => $value) {
            if (strncmp($key, '_shib', strlen('_shib')) == 0) {
                static::unsetCookieVar($key);
            }
        }
    }

    /**
     * startPHPSession
     *
     * This function starts a secure PHP session and should be called
     * at the beginning of each script before any HTML is output.  It
     * does a trick of setting a 'lastaccess' time so that the
     * $_SESSION variable does not expire without warning.
     *
     * @param string $storetype (Optional) Storage location of the PHP
     *        session data, one of 'file' or 'mysql'. Defaults to null,
     *        which means use the value of STORAGE_PHPSESSIONS from the
     *        config.php file, or 'file' if no such parameter configured.
     */
    public static function startPHPSession($storetype = null)
    {
        // No parameter given? Use the value read in from cilogon.ini file.
        // If STORAGE_PHPSESSIONS == 'mysqli', create a sessionmgr().
        $storetype = STORAGE_PHPSESSIONS;

        if (preg_match('/^mysql/', $storetype)) {
            $sessionmgr = new SessionMgr();
        }

        ini_set('session.cookie_secure', true);
        ini_set('session.cookie_domain', '.' . static::getDN());
        session_start();
        if (
            (!isset($_SESSION['lastaccess']) ||
            (time() - $_SESSION['lastaccess']) > 60)
        ) {
            $_SESSION['lastaccess'] = time();
        }
    }

    /**
     * getScriptDir
     *
     * This function returns the directory (or full url) of the script
     * that is currently running.  The returned directory/url is
     * terminated by a '/' character (unless the second parameter is
     * set to true). This function is useful for those scripts named
     * index.php where we don't want to actually see 'index.php' in the
     * address bar (again, unless the second parameter is set to true).
     *
     * @param bool $prependhttp (Optional) Boolean to prepend 'http(s)://' to
     *        the script name. Defaults to false.
     * @param bool $stripfile (Optional) Boolean to strip off the trailing
     *        filename (e.g. index.php) from the path.
     *        Defaults to true (i.e., defaults to directory
     *        only without the trailing filename).
     * @return string The directory or url of the current script, with or
     *         without the trailing .php filename.
     */
    public static function getScriptDir($prependhttp = false, $stripfile = true)
    {
        $retval = static::getServerVar('SCRIPT_NAME');
        if ($stripfile) {
            $retval = dirname($retval);
        }
        if ($retval == '.') {
            $retval = '';
        }
        if (
            (strlen($retval) == 0) ||
            ($stripfile && ($retval[strlen($retval) - 1] != '/'))
        ) {
            $retval .= '/';  // Append a slash if necessary
        }
        if ($prependhttp) {  // Prepend http(s)://hostname
            $retval = 'http' .
                      ((strtolower(static::getServerVar('HTTPS')) == 'on') ? 's' : '') .
                      '://' . static::getServerVar('HTTP_HOST') . $retval;
        }
        return $retval;
    }

    /**
     * tempDir
     *
     * This function creates a temporary subdirectory within the
     * specified subdirectory. The new directory name is composed of
     * 16 hexadecimal letters, plus any prefix if you specify one. The
     * full path of the the newly created directory is returned.
     *
     * @param string $dir The full path to the containing directory.
     * @param string $prefix (Optional) A prefix for the new temporary
     *        directory. Defaults to empty string.
     * @param int $mode (Optional) Access permissions for the new
     *        temporary directory. Defaults to 0775.
     * @return string Full path to the newly created temporary directory.
     */
    public static function tempDir($dir, $prefix = '', $mode = 0775)
    {
        if (substr($dir, -1) != '/') {
            $dir .= '/';
        }

        $path = '';
        do {
            $path = $dir . $prefix . sprintf("%08X%08X", mt_rand(), mt_rand());
        } while (!mkdir($path, $mode, true));

        return $path;
    }

    /**
     * deleteDir
     *
     * This function deletes a directory and all of its contents.
     *
     * @param string $dir The (possibly non-empty) directory to delete.
     * @param bool $shred (Optional) Shred the file before deleting?
     *        Defaults to false.
     */
    public static function deleteDir($dir, $shred = false)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        static::deleteDir($dir . "/" . $object);
                    } else {
                        if ($shred) {
                            @exec('/bin/env /usr/bin/shred -u -z ' . $dir . "/" . $object);
                        } else {
                            @unlink($dir . "/" . $object);
                        }
                    }
                }
            }
            reset($objects);
            @rmdir($dir);
        }
    }

    /**
     * htmlent
     *
     * This method is necessary since htmlentities() does not seem to
     * obey the default arguments as documented in the PHP manual, and
     * instead encodes accented characters incorrectly. By specifying
     * the flags and encoding, the problem is solved.
     *
     * @param string $str : A string to process with htmlentities().
     * @return string The input string processed by htmlentities with
     *         specific options.
     */
    public static function htmlent($str)
    {
        return htmlentities($str, ENT_COMPAT | ENT_HTML401, 'UTF-8');
    }

    /**
     * sendErrorAlert
     *
     * Use this function to send an error message. The $summary should
     * be a short description of the error since it is placed in the
     * subject of the email. Put a more verbose description of the
     * error in the $detail parameter. Any session variables available
     * are appended to the body of the message.
     *
     * @param string $summary A brief summary of the error (in email subject)
     * @param string $detail A detailed description of the error (in the
     *        email body)
     * @param string $mailto (Optional) The destination email address.
     *        Defaults to 'alerts@cilogon.org'.
     */
    public static function sendErrorAlert(
        $summary,
        $detail,
        $mailto = 'alerts@cilogon.org'
    ) {
        $sessionvars = array(
            'idp'          => 'IdP ID',
            'idpname'      => 'IdP Name',
            'uid'          => 'Database UID',
            'dn'           => 'Cert DN',
            'firstname'    => 'First Name',
            'lastname'     => 'Last Name',
            'displayname'  => 'Display Name',
            'ePPN'         => 'ePPN',
            'ePTID'        => 'ePTID',
            'openID'       => 'OpenID ID',
            'oidcID'       => 'OIDC ID',
            'loa'          => 'LOA',
            'affiliation'  => 'Affiliation',
            'ou'           => 'OU',
            'memberof'     => 'MemberOf',
            'acr'          => 'AuthnContextClassRef',
            'entitlement'  => 'Entitlement',
            'itrustuin'    => 'iTrustUIN',
            'cilogon_skin' => 'Skin Name',
            'authntime'    => 'Authn Time'
        );

        $remoteaddr = static::getServerVar('REMOTE_ADDR');
        $remotehost = gethostbyaddr($remoteaddr);
        $mailfrom = 'From: alerts@cilogon.org' . "\r\n" .
                    'X-Mailer: PHP/' . phpversion();
        $mailsubj = 'CILogon Service on ' . php_uname('n') .
                    ' - ' . $summary;
        $mailmsg  = '
CILogon Service - ' . $summary . '
-----------------------------------------------------------
' . $detail . '

Session Variables
-----------------
Timestamp     = ' . date(DATE_ATOM) . '
Server Host   = ' . static::getHN() . '
Remote Address= ' . $remoteaddr . '
' . (($remotehost !== false) ? "Remote Host   = $remotehost" : '') . '
';

        foreach ($sessionvars as $svar => $sname) {
            if (strlen($val = static::getSessionVar($svar)) > 0) {
                $mailmsg .= sprintf("%-14s= %s\n", $sname, $val);
            }
        }

        mail($mailto, $mailsubj, $mailmsg, $mailfrom);
    }

    /**
     * getFirstAndLastName
     *
     * This function attempts to get the first and last name of a user
     * extracted from the 'full name' (displayName) of the user.
     * Simply pass in all name info (full, first, and last) and the
     * function first tries to break up the full name into first/last.
     * If this is not sufficient, the function checks first and last
     * name. Finally, if either first or last is blank, the function
     * duplicates first <=> last so both names have the same value.
     * Note that even with all this, you still need to check if the
     * returned (first,last) names are blank.
     *
     * @param string $full The 'full name' of the user
     * @param string $first (Optional) The 'first name' of the user
     * @param string $last (Optional) The 'last name' of the user
     * @return array An array 'list(firstname,lastname)'
     */
    public static function getFirstAndLastName($full, $first = '', $last = '')
    {
        $firstname = '';
        $lastname = '';

        # Try to split the incoming $full name into first and last names
        if (strlen($full) > 0) {
            $names = preg_split('/\s+/', $full, 2);
            $firstname = @$names[0];
            $lastname =  @$names[1];
        }

        # If either first or last name blank, then use incoming $first and $last
        if (strlen($firstname) == 0) {
            $firstname = $first;
        }
        if (strlen($lastname) == 0) {
            $lastname = $last;
        }

        # Finally, if only a single name, copy first name <=> last name
        if (strlen($lastname) == 0) {
            $lastname = $firstname;
        }
        if (strlen($firstname) == 0) {
            $firstname = $lastname;
        }

        # Return both names as an array (i.e., use list($first,last)=...)
        return array($firstname,$lastname);
    }

    /**
     * getHN
     *
     * This function calculates and returns the 'hostname' for the
     * server. It first checks HTTP_HOST. If not set, it returns
     * DEFAULT_HOSTNAME. This is needed by command line scripts.
     *
     * @return string The 'Hostname' for the web server.
     */
    public static function getHN()
    {
        $thehostname = static::getServerVar('HTTP_HOST');
        if (strlen($thehostname) == 0) {
            $thehostname = DEFAULT_HOSTNAME;
        }
        return $thehostname;
    }

    /**
     * getDN
     *
     * This function calculates and returns the 'domainname' for the
     * server. It uses the hostname value calculated by getHN() and
     * uses the last two segments.
     *
     * @return string The 'Domainname' for the web server.
     */
    public static function getDN()
    {
        $thedomainname = static::getHN();
        if (preg_match('/[^\.]+\.[^\.]+$/', $thedomainname, $matches)) {
            $thedomainname = $matches[0];
        }
        return $thedomainname;
    }

    /**
     * getAuthzUrl
     *
     * This funtion takes in the name of an IdP (e.g., 'Google') and
     * returns the assoicated OAuth2 authorization URL.
     *
     * @param string $idp The name of an OAuth2 Identity Provider.
     * @return string The authorization URL for the given IdP.
     */
    public static function getAuthzUrl($idp)
    {
        $url = null;
        $idptourl = array(
            'Google' => 'https://accounts.google.com/o/oauth2/auth',
            'GitHub' => 'https://github.com/login/oauth/authorize',
            'ORCID'  => 'https://orcid.org/oauth/authorize',
        );
        if (array_key_exists($idp, $idptourl)) {
            $url = $idptourl[$idp];
        }
        return $url;
    }

    /**
     * getAuthzIdP
     *
     * This function takes in the OAuth2 authorization URL and returns
     * the associated pretty-print name of the IdP.
     *
     * @param string $url The authorization URL of an OAuth2 Identity Provider.
     * @return string The name of the IdP.
     */
    public static function getAuthzIdP($url)
    {
        $idp = null;
        $urltoidp = array(
            'https://accounts.google.com/o/oauth2/auth' => 'Google',
            'https://github.com/login/oauth/authorize'  => 'GitHub',
            'https://orcid.org/oauth/authorize'         => 'ORCID',
        );
        if (array_key_exists($url, $urltoidp)) {
            $idp = $urltoidp[$url];
        }
        return $idp;
    }

    /**
     * gotUserAttributes
     *
     * This function returns true if the PHP session contains all of the
     * necessary user/IdP attributes to fetch an X.509 certificate. This
     * means that at least one of (remoteuser, ePPN, ePTID, openidID,
     * oidcID) must be set, as well as idp (entityId), idpname, firstname,
     * lastname, and emailaddr. Also, the emailaddr must conform to valid
     * email formatting.
     *
     * @return bool True if all user/IdP attributes necessary to form the
     *              distinguished name (DN) for X.509 certificates are
     *              present in the PHP session. False otherwise.
     */
    public static function gotUserAttributes()
    {
        $retval = false;  // Assume we don't have all user attributes
        if (
            ((strlen(Util::getSessionVar('remoteuser')) > 0) ||
                (strlen(Util::getSessionVar('ePPN')) > 0) ||
                (strlen(Util::getSessionVar('ePTID')) > 0) ||
                (strlen(Util::getSessionVar('openidID')) > 0) ||
                (strlen(Util::getSessionVar('oidcID')) > 0)) &&
            (strlen(Util::getSessionVar('idp')) > 0) &&
            (strlen(Util::getSessionVar('idpname')) > 0)  &&
            (strlen(Util::getSessionVar('firstname')) > 0) &&
            (strlen(Util::getSessionVar('lastname')) > 0) &&
            (strlen(Util::getSessionVar('emailaddr')) > 0) &&
            (filter_var(Util::getSessionVar('emailaddr'), FILTER_VALIDATE_EMAIL))
        ) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * saveUserToDataStore
     *
     * This function is called when a user logs on to save identity
     * information to the datastore. As it is used by both Shibboleth
     * and OpenID Identity Providers, some parameters passed in may
     * be blank (empty string). If the function verifies that the minimal
     * sets of parameters are valid, the dbservice servlet is called
     * to save the user info. Then various session variables are set
     * for use by the program later on. In case of error, an email
     * alert is sent showing the missing parameters.
     *
     * @param mixed $args Variable number of paramters ordered as follows:
     *     remoteuser -The REMOTE_USER from HTTP headers
     *     idp - The provider IdP Identifier / URL endpoint
     *     idpname - The pretty print provider IdP name
     *     firstname - The user's first name
     *     lastname - The user's last name
     *     displayname - The user's display name
     *     emailaddr-  The user's email address
     *     loa - The level of assurance (e.g., openid/basic/silver)
     *     ePPN - User's ePPN (for SAML IdPs)
     *     ePTID - User's ePTID (for SAML IdPs)
     *     openidID - User's OpenID 2.0 Identifier (Google deprecated)
     *     oidcID - User's OpenID Connect Identifier
     *     affiliation - User's affiliation
     *     ou - User's organizational unit (OU)
     *     memberof - User's isMemberOf group info
     *     acr - Authentication Context Class Ref
     *     entitlement - User's entitlement
     *     itrustuin - User's univerity ID number
     */
    public static function saveUserToDataStore(...$args)
    {
        $dbs = new DBService();

        // Save the passed-in variables to the session for later use
        // (e.g., by the error handler in handleGotUser). Then get these
        // session variables into local vars for ease of use.
        static::setUserAttributeSessionVars(...$args);
        $remoteuser  = static::getSessionVar('remoteuser');
        $idp         = static::getSessionVar('idp');
        $idpname     = static::getSessionVar('idpname');
        $firstname   = static::getSessionVar('firstname');
        $lastname    = static::getSessionVar('lastname');
        $displayname = static::getSessionVar('displayname');
        $emailaddr   = static::getSessionvar('emailaddr');
        $loa         = static::getSessionVar('loa');
        $ePPN        = static::getSessionVar('ePPN');
        $ePTID       = static::getSessionVar('ePTID');
        $openidID    = static::getSessionVar('openidID');
        $oidcID      = static::getSessionVar('oidcID');
        $affiliation = static::getSessionVar('affiliation');
        $ou          = static::getSessionVar('ou');
        $memberof    = static::getSessionVar('memberof');
        $acr         = static::getSessionVar('acr');
        $entitlement = static::getSessionVar('entitlement');
        $itrustuin   = static::getSessionVar('itrustuin');

        static::setSessionVar('submit', static::getSessionVar('responsesubmit'));

        // Make sure parameters are not empty strings, and email is valid
        // Must have at least one of remoteuser/ePPN/ePTID/openidID/oidcID
        if (static::gotUserAttributes()) {
            // For the new Google OAuth 2.0 endpoint, we want to keep the
            // old Google OpenID endpoint URL in the database (so user does
            // not get a new certificate subject DN). Change the idp
            // and idpname to the old Google OpenID values.
            if (
                ($idpname == 'Google+') ||
                ($idp == static::getAuthzUrl('Google'))
            ) {
                $idpname = 'Google';
                $idp = 'https://www.google.com/accounts/o8/id';
            }

            // In the database, keep a consistent ProviderId format: only
            // allow 'http' (not 'https') and remove any 'www.' prefix.
            if ($loa == 'openid') {
                $idp = preg_replace('%^https://(www\.)?%', 'http://', $idp);
            }

            $result = $dbs->getUser(
                $remoteuser,
                $idp,
                $idpname,
                $firstname,
                $lastname,
                $displayname,
                $emailaddr,
                $ePPN,
                $ePTID,
                $openidID,
                $oidcID,
                $affiliation,
                $ou,
                $memberof,
                $acr,
                $entitlement,
                $itrustuin
            );
            static::setSessionVar('uid', $dbs->user_uid);
            static::setSessionVar('dn', $dbs->distinguished_name);
            static::setSessionVar('status', $dbs->status);
            if (!$result) {
                static::sendErrorAlert(
                    'dbService Error',
                    'Error calling dbservice action "getUser" in ' .
                    'saveUserToDatastore() method.'
                );
            }
        } else { // Missing one or more required attributes
            static::setSessionVar(
                'status',
                DBService::$STATUS['STATUS_MISSING_PARAMETER_ERROR']
            );
        }

        // If 'status' is not STATUS_OK*, then send an error email
        $status = static::getSessionVar('status');
        if ($status & 1) { // Bad status codes are odd
            // For missing parameter errors, log an error message
            if (
                $status ==
                DBService::$STATUS['STATUS_MISSING_PARAMETER_ERROR']
            ) {
                $log = new Loggit();
                $log->error('STATUS_MISSING_PARAMETER_ERROR', true);
            }

            // For other dbservice errors OR for any error involving
            // LIGO (e.g., missing parameter error), send email alert.
            if (
                ($status !=
                    DBService::$STATUS['STATUS_MISSING_PARAMETER_ERROR']) ||
                (preg_match('/ligo\.org/', $idp))
            ) {
                $mailto = 'alerts@cilogon.org';

                // CIL-205 - Notify LIGO about IdP login errors.
                // Set DISABLE_LIGO_ALERTS to true in the top-level
                // config.php file to stop LIGO failures
                // from being sent to 'alerts@cilogon.org', but still
                // sent to 'cilogon-alerts@ligo.org'.
                if (preg_match('/ligo\.org/', $idp)) {
                    if (DISABLE_LIGO_ALERTS) {
                        $mailto = '';
                    }
                    $mailto .= ((strlen($mailto) > 0) ? ',' : '') .
                        'cilogon-alerts@ligo.org';
                }

                static::sendErrorAlert(
                    'Failure in ' .
                        (($loa == 'openid') ? '' : '/secure') . '/getuser/',
                    'Remote_User   = ' . ((strlen($remoteuser) > 0) ?
                        $remoteuser : '<MISSING>') . "\n" .
                    'IdP ID        = ' . ((strlen($idp) > 0) ?
                        $idp : '<MISSING>') . "\n" .
                    'IdP Name      = ' . ((strlen($idpname) > 0) ?
                        $idpname : '<MISSING>') . "\n" .
                    'First Name    = ' . ((strlen($firstname) > 0) ?
                        $firstname : '<MISSING>') . "\n" .
                    'Last Name     = ' . ((strlen($lastname) > 0) ?
                        $lastname : '<MISSING>') . "\n" .
                    'Display Name  = ' . ((strlen($displayname) > 0) ?
                        $displayname : '<MISSING>') . "\n" .
                    'Email Address = ' . ((strlen($emailaddr) > 0) ?
                        $emailaddr : '<MISSING>') . "\n" .
                    'ePPN          = ' . ((strlen($ePPN) > 0) ?
                        $ePPN : '<MISSING>') . "\n" .
                    'ePTID         = ' . ((strlen($ePTID) > 0) ?
                        $ePTID : '<MISSING>') . "\n" .
                    'OpenID ID     = ' . ((strlen($openidID) > 0) ?
                        $openidID : '<MISSING>') . "\n" .
                    'OIDC ID       = ' . ((strlen($oidcID) > 0) ?
                        $oidcID : '<MISSING>') . "\n" .
                    'Affiliation   = ' . ((strlen($affiliation) > 0) ?
                        $affiliation : '<MISSING>') . "\n" .
                    'OU            = ' . ((strlen($ou) > 0) ?
                        $ou : '<MISSING>') . "\n" .
                    'MemberOf      = ' . ((strlen($memberof) > 0) ?
                        $memberof : '<MISSING>') . "\n" .
                    'ACR           = ' . ((strlen($acr) > 0) ?
                        $acr : '<MISSING>') . "\n" .
                    'Entitlement   = ' . ((strlen($entitlement) > 0) ?
                        $entitlement : '<MISSING>') . "\n" .
                    'iTrustUIN     = ' . ((strlen($itrustuin) > 0) ?
                        $itrustuin : '<MISSING>') . "\n" .
                    'Database UID  = ' . ((strlen(
                        $i = static::getSessionVar('uid')
                    ) > 0) ?  $i : '<MISSING>') . "\n" .
                    'Status Code   = ' . ((strlen(
                        $i = array_search(
                            $status,
                            DBService::$STATUS
                        )
                    ) > 0) ?  $i : '<MISSING>'),
                    $mailto
                );
            }
            static::unsetSessionVar('authntime');
        } else { // status is okay, set authntime
            static::setSessionVar('authntime', time());
        }

        static::unsetSessionVar('responsesubmit');
        static::unsetSessionVar('requestsilver');

        static::getCsrf()->setCookieAndSession();
    }

    /**
     * setUserAttributeSessionVars
     *
     * This method is called by saveUserToDatastore to put the passsed-in
     * variables into the PHP session for later use.
     *
     * @param mixed $args Variable number of user attribute paramters
     *        ordered as shown in the $attrs array below.
     */
    public static function setUserAttributeSessionVars(...$args)
    {
        $attrs = array('remoteuser', 'idp', 'idpname', 'firstname',
                       'lastname', 'displayname', 'emailaddr',
                       'loa', 'ePPN', 'ePTID', 'openidID', 'oidcID',
                       'affiliation', 'ou', 'memberof', 'acr',
                       'entitlement', 'itrustuin');
        $numargs = count($args);
        for ($i = 0; $i < $numargs; $i++) {
            static::setSessionVar($attrs[$i], $args[$i]);
        }

        // CACC-238 - Set loa to "silver" if the following are true:
        // (1) loa contains  https://refeds.org/assurance/profile/cappuccino
        // (2) acr is either https://refeds.org/profile/sfa or
        //                   https://refeds.org/profile/mfa
        if (
            (preg_match('%https://refeds.org/assurance/profile/cappuccino%', static::getSessionVar('loa'))) &&
            (preg_match('%https://refeds.org/profile/[ms]fa%', static::getSessionVar('acr')))
        ) {
            static::setSessionVar('loa', 'http://incommonfederation.org/assurance/silver');
        }
    }

    /**
     * unsetClientSessionVars
     *
     * This function removes all of the PHP session variables related to
     * the client session.
     */
    public static function unsetClientSessionVars()
    {
        static::unsetSessionVar('submit');

        // Specific to 'Download Certificate' page
        static::unsetSessionVar('p12');
        static::unsetSessionVar('p12lifetime');
        static::unsetSessionVar('p12multiplier');

        // Specific to OAuth 1.0a flow
        static::unsetSessionVar('portalstatus');
        static::unsetSessionVar('callbackuri');
        static::unsetSessionVar('successuri');
        static::unsetSessionVar('failureuri');
        static::unsetSessionVar('portalname');
        static::unsetSessionVar('tempcred');

        // Specific to OIDC flow
        static::unsetSessionVar('clientparams');
    }

    /**
     * unsetUserSessionVars
     *
     * This function removes all of the PHP session variables related to
     * the user's session.  This will force the user to log on (again)
     * with their IdP and call the 'getuser' script to repopulate the PHP
     * session.
     */
    public static function unsetUserSessionVars()
    {
        // Needed for verifyCurrentUserSession
        static::unsetSessionVar('idp');
        static::unsetSessionVar('idpname');
        static::unsetSessionVar('status');
        static::unsetSessionVar('uid');
        static::unsetSessionVar('dn');
        static::unsetSessionVar('authntime');

        // Variables set by getuser
        static::unsetSessionVar('firstname');
        static::unsetSessionVar('lastname');
        static::unsetSessionVar('displayname');
        static::unsetSessionVar('emailaddr');
        static::unsetSessionVar('loa');
        static::unsetSessionVar('ePPN');
        static::unsetSessionVar('ePTID');
        static::unsetSessionVar('openidID');
        static::unsetSessionVar('oidcID');
        static::unsetSessionVar('affiliation');
        static::unsetSessionVar('ou');
        static::unsetSessionVar('memberof');
        static::unsetSessionVar('acr');
        static::unsetSessionVar('entitlement');
        static::unsetSessionVar('itrustuin');

        // Current skin
        static::unsetSessionVar('cilogon_skin');
    }

    /**
     * unsetAllUserSessionVars
     *
     * This is a convenience method to clear all session variables related
     * to the client and the user.
     */
    public static function unsetAllUserSessionVars()
    {
        static::unsetClientSessionVars();
        static::unsetUserSessionVars();
    }

    /**
     * verifySessionAndCall
     *
     * This function is a convenience method called by several cases in the
     * main 'switch' call at the top of the index.php file. I noticed
     * a pattern where verifyCurrentUserSession() was called to verify the
     * current user session. Upon success, one or two functions were called
     * to continue program, flow. Upon failure, cookies and session
     * variables were cleared, and the main Logon page was printed. This
     * function encapsulates that pattern. If the user's session is valid,
     * the passed-in $func is called, possibly with parameters passed in as
     * an array. The function returns true if the session is verified, so
     * that other functions may be called upon return.
     *
     * @param function $func The function to call if the current session is
     *        successfully verified.
     * @param array $params (Optional) An array of parameters to pass to the
     *        function. Defaults to empty array, meaning zero parameters.
     */
    public static function verifySessionAndCall($func, $params = array())
    {
        $retval = false;
        if (Content::verifyCurrentUserSession()) { // Verify PHP session is valid
            $retval = true;
            call_user_func_array($func, $params);
        } else {
            printLogonPage(true); // Clear cookies and session vars too
        }
        return $retval;
    }

    /**
     * isEduGAINAndGetCert
     *
     * This function checks to see if the current session IdP is an
     * eduGAIN IdP (i.e., not Registered By InCommon) and the IdP does not
     * have both the REFEDS R&S and SIRTFI extensions in metadata. If so,
     * check to see if the transaction could be used to fetch a
     * certificate. (The only time the transaction is not used to fetch
     * a cert is during OIDC without the 'getcert' scope.) If all that is
     * true, then return true. Otherwise return false.
     *
     * @param string $idp (optional) The IdP entityID. If empty, read value
     *        from PHP session.
     * @param string $idpname (optional) The IdP display name. If empty,
     *        read value from PHP session.
     * @return bool True if the current IdP is an eduGAIN IdP without
     *         both REFEDS R&S and SIRTFI, AND the session could be
     *         used to get a certificate.
     */
    public static function isEduGAINAndGetCert($idp = '', $idpname = '')
    {
        $retval = false; // Assume not eduGAIN IdP and getcert

        // If $idp or $idpname not passed in, get from current session.
        if (strlen($idp) == 0) {
            $idp = static::getSessionVar('idp');
        }
        if (strlen($idpname) == 0) {
            $idpname = static::getSessionVar('idpname');
        }

        // Check if this was an OIDC transaction, and if the
        // 'getcert' scope was requested.
        $oidcscopegetcert = false;
        $oidctrans = false;
        $clientparams = json_decode(static::getSessionVar('clientparams'), true);
        if (isset($clientparams['scope'])) {
            $oidctrans = true;
            if (
                preg_match(
                    '/edu\.uiuc\.ncsa\.myproxy\.getcert/',
                    $clientparams['scope']
                )
            ) {
                $oidcscopegetcert = true;
            }
        }

        // First, make sure $idp was set and is not an OAuth2 IdP.
        $idplist = static::getIdpList();
        if (
            ((strlen($idp) > 0) &&
            (strlen($idpname) > 0) &&
            (!in_array($idpname, static::$oauth2idps))) &&
                (
                // Next, check for eduGAIN without REFEDS R&S and SIRTFI
                ((!$idplist->isRegisteredByInCommon($idp)) &&
                       ((!$idplist->isREFEDSRandS($idp)) ||
                        (!$idplist->isSIRTFI($idp))
                       )
                ) &&
                // Next, check if user could get X509 cert,
                // i.e., OIDC getcert scope, or a non-OIDC
                // transaction such as PKCS12, JWS, or OAuth 1.0a
                ($oidcscopegetcert || !$oidctrans)
                )
        ) {
            $retval = true;
        }
        return $retval;
    }
}
