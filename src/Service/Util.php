<?php

namespace CILogon\Service;

require_once 'DB.php';

use CILogon\Service\CSRF;
use CILogon\Service\Loggit;
use CILogon\Service\IdpList;
use CILogon\Service\DBService;
use CILogon\Service\SessionMgr;
use CILogon\Service\Skin;
use CILogon\Service\TimeIt;
use CILogon\Service\PortalCookie;
use PEAR;
use DB;

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
     * @return IdPList|null The class instantiated IdPList object.
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
     * @return CSRF|null The class instantiated CSRF object.
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
     * @return Skin|null The class instantiated Skin object.
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
     * getPortalOrCookieVar
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
    public static function getPortalOrCookieVar($cookie)
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
        if (is_null($storetype)) {
            if (defined('STORAGE_PHPSESSIONS')) {
                $storetype = STORAGE_PHPSESSIONS;
            } else {
                $storetype = 'file';
            }
        }

        if (preg_match('/^mysql/', $storetype)) {
            // If STORAGE_PHPSESSIONS == 'mysqli', create a sessionmgr().
            $sessionmgr = new SessionMgr();
        } elseif ($storetype == 'file') {
            // If storing PHP sessions to file, check if an optional directory
            // for storage has been set. If so, create it if necessary.
            if ((defined('STORAGE_PHPSESSIONS_DIR')) && (!empty(STORAGE_PHPSESSIONS_DIR))) {
                if (!is_dir(STORAGE_PHPSESSIONS_DIR)) {
                    mkdir(STORAGE_PHPSESSIONS_DIR, 0770, true);
                }

                if (is_dir(STORAGE_PHPSESSIONS_DIR)) {
                    ini_set('session.save_path', STORAGE_PHPSESSIONS_DIR);
                }
            }
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
     *        Defaults to EMAIL_ALERTS (defined in the top-level
     *        config.php file as 'alerts@' . DEFAULT_HOSTNAME).
     */
    public static function sendErrorAlert(
        $summary,
        $detail,
        $mailto = EMAIL_ALERTS
    ) {
        $sessionvars = array(
            'idp'                => 'IdP ID',
            'idp_display_name'   => 'IdP Name',
            'user_uid'           => 'User UID',
            'distinguished_name' => 'Cert DN',
            'first_name'         => 'First Name',
            'last_name'          => 'Last Name',
            'display_name'       => 'Display Name',
            'eppn'               => 'ePPN',
            'eptid'              => 'ePTID',
            'open_id'            => 'OpenID ID',
            'oidc'               => 'OIDC ID',
            'subject_id'         => 'Subject ID',
            'pairwise_id'        => 'Pairwise ID',
            'loa'                => 'LOA',
            'affiliation'        => 'Affiliation',
            'ou'                 => 'OU',
            'member_of'          => 'MemberOf',
            'acr'                => 'AuthnContextClassRef',
            'amr'                => 'AuthnMethodRef',
            'entitlement'        => 'Entitlement',
            'itrustuin'          => 'iTrustUIN',
            'cilogon_skin'       => 'Skin Name',
            'authntime'          => 'Authn Time'
        );

        $remoteaddr = static::getServerVar('REMOTE_ADDR');
        $remotehost = gethostbyaddr($remoteaddr);
        $mailfrom = 'From: ' . EMAIL_ALERTS . "\r\n" .
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
     * getHN
     *
     * This function calculates and returns the 'hostname' for the
     * server. It first checks HTTP_HOST. If not set OR if not a
     * FQDN (with at least one '.'), it returns DEFAULT_HOSTNAME.
     * This is needed by command line scripts.
     *
     * @return string The 'Hostname' for the web server.
     */
    public static function getHN()
    {
        $thehostname = static::getServerVar('HTTP_HOST');
        if (
            (strlen($thehostname) == 0) ||
            (strpos($thehostname, '.') === false)
        ) {
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
     * @param mixed $args Variable number of parameters, the same as those
     *        in DBService::$user_attrs
     */
    public static function saveUserToDataStore(...$args)
    {
        $dbs = new DBService();

        // Save the passed-in variables to the session for later use
        // (e.g., by the error handler in handleGotUser). Then get these
        // session variables into local vars for ease of use.
        static::setUserAttributeSessionVars(...$args);

        // This bit of trickery sets local variables from the PHP session
        // that was just populated, using the names in the $user_attrs array.
        foreach (DBService::$user_attrs as $value) {
            $$value = static::getSessionVar($value);
        }

        // For the new Google OAuth 2.0 endpoint, we want to keep the
        // old Google OpenID endpoint URL in the database (so user does
        // not get a new certificate subject DN). Change the idp
        // and idp_display_name to the old Google OpenID values.
        if (
            ($idp_display_name == 'Google+') ||
            ($idp == static::getAuthzUrl('Google'))
        ) {
            $idp_display_name = 'Google';
            $idp = 'https://www.google.com/accounts/o8/id';
        }

        // In the database, keep a consistent ProviderId format: only
        // allow 'http' (not 'https') and remove any 'www.' prefix.
        if ($loa == 'openid') {
            $idp = preg_replace('%^https://(www\.)?%', 'http://', $idp);
        }

        // Call the dbService to get the user using IdP attributes.
        $result = $dbs->getUser(
            $remote_user,
            $idp,
            $idp_display_name,
            $first_name,
            $last_name,
            $display_name,
            $email,
            $loa,
            $eppn,
            $eptid,
            $open_id,
            $oidc,
            $subject_id,
            $pairwise_id,
            $affiliation,
            $ou,
            $member_of,
            $acr,
            $amr,
            $entitlement,
            $itrustuin
        );
        if ($result) {
            static::setSessionVar('user_uid', $dbs->user_uid);
            static::setSessionVar('distinguished_name', $dbs->distinguished_name);
            static::setSessionVar('status', $dbs->status);
        } else {
            static::sendErrorAlert(
                'dbService Error',
                'Error calling dbservice action "getUser" in ' .
                'saveUserToDatastore() method.'
            );
            static::unsetSessionVar('user_uid');
            static::unsetSessionVar('distinguished_name');
            static::setSessionVar('status', DBService::$STATUS['STATUS_INTERNAL_ERROR']);
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
                $mailto = EMAIL_ALERTS;

                // CIL-205 - Notify LIGO about IdP login errors.
                // Set DISABLE_LIGO_ALERTS to true in the top-level
                // config.php file to stop LIGO failures
                // from being sent to EMAIL_ALERTS, but still
                // sent to 'cilogon-alerts@ligo.org'.
                if (preg_match('/ligo\.org/', $idp)) {
                    if (defined('DISABLE_LIGO_ALERTS') && DISABLE_LIGO_ALERTS) {
                        $mailto = '';
                    }
                    $mailto .= ((strlen($mailto) > 0) ? ',' : '') .
                        'cilogon-alerts@ligo.org';
                }

                static::sendErrorAlert(
                    'Failure in ' .
                        (($loa == 'openid') ? '' : '/secure') . '/getuser/',
                    'Remote_User   = ' . ((strlen($remote_user) > 0) ?
                        $remote_user : '<MISSING>') . "\n" .
                    'IdP ID        = ' . ((strlen($idp) > 0) ?
                        $idp : '<MISSING>') . "\n" .
                    'IdP Name      = ' . ((strlen($idp_display_name) > 0) ?
                        $idp_display_name : '<MISSING>') . "\n" .
                    'First Name    = ' . ((strlen($first_name) > 0) ?
                        $first_name : '<MISSING>') . "\n" .
                    'Last Name     = ' . ((strlen($last_name) > 0) ?
                        $last_name : '<MISSING>') . "\n" .
                    'Display Name  = ' . ((strlen($display_name) > 0) ?
                        $display_name : '<MISSING>') . "\n" .
                    'Email Address = ' . ((strlen($email) > 0) ?
                        $email : '<MISSING>') . "\n" .
                    'LOA           = ' . ((strlen($loa) > 0) ?
                        $loa : '<MISSING>') . "\n" .
                    'ePPN          = ' . ((strlen($eppn) > 0) ?
                        $eppn : '<MISSING>') . "\n" .
                    'ePTID         = ' . ((strlen($eptid) > 0) ?
                        $eptid : '<MISSING>') . "\n" .
                    'OpenID ID     = ' . ((strlen($open_id) > 0) ?
                        $open_id : '<MISSING>') . "\n" .
                    'OIDC ID       = ' . ((strlen($oidc) > 0) ?
                        $oidc : '<MISSING>') . "\n" .
                    'Subject ID    = ' . ((strlen($subject_id) > 0) ?
                        $subject_id : '<MISSING>') . "\n" .
                    'Pairwise ID   = ' . ((strlen($pairwise_id) > 0) ?
                        $pairwise_id : '<MISSING>') . "\n" .
                    'Affiliation   = ' . ((strlen($affiliation) > 0) ?
                        $affiliation : '<MISSING>') . "\n" .
                    'OU            = ' . ((strlen($ou) > 0) ?
                        $ou : '<MISSING>') . "\n" .
                    'MemberOf      = ' . ((strlen($member_of) > 0) ?
                        $member_of : '<MISSING>') . "\n" .
                    'ACR           = ' . ((strlen($acr) > 0) ?
                        $acr : '<MISSING>') . "\n" .
                    'AMR           = ' . ((strlen($amr) > 0) ?
                        $amr : '<MISSING>') . "\n" .
                    'Entitlement   = ' . ((strlen($entitlement) > 0) ?
                        $entitlement : '<MISSING>') . "\n" .
                    'iTrustUIN     = ' . ((strlen($itrustuin) > 0) ?
                        $itrustuin : '<MISSING>') . "\n" .
                    'User UID      = ' . ((strlen(
                        $i = static::getSessionVar('user_uid')
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
        } else {
            // Success! We need to overwrite current session vars with values
            // returned by the DBService, e.g., in case attributes were set
            // previously but not this time. Skip 'idp' since the PHP code
            // transforms 'https://' to 'http://' for database consistency.
            // Also skip 'loa' since that is not saved in the database.
            foreach (DBService::$user_attrs as $value) {
                if (($value != 'idp') && ($value != 'loa')) {
                    static::setSessionVar($value, $dbs->$value);
                }
            }
        }
    }

    /**
     * setUserAttributeSessionVars
     *
     * This method is called by saveUserToDatastore to put the passsed-in
     * variables into the PHP session for later use.
     *
     * @param mixed $args Variable number of user attribute paramters
     *        ordered as shown in the DBService::$user_attrs array.
     */
    public static function setUserAttributeSessionVars(...$args)
    {
        // Loop through the list of user_attrs. First, unset any previous
        // value for the attribute, then set the passed-in attribute value.
        $numattrs = count(DBService::$user_attrs);
        $numargs = count($args);
        for ($i = 0; $i < $numattrs; $i++) {
            static::unsetSessionVar(DBService::$user_attrs[$i]);
            if ($i < $numargs) {
                static::setSessionVar(DBService::$user_attrs[$i], $args[$i]);
            }
        }

        static::setSessionVar('status', '0');
        static::setSessionVar('submit', static::getSessionVar('responsesubmit'));
        static::setSessionVar('authntime', time());
        static::unsetSessionVar('responsesubmit');
        static::getCsrf()->setCookieAndSession();
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
        foreach (DBService::$user_attrs as $value) {
            static::unsetSessionVar($value);
        }
        static::unsetSessionVar('status');
        static::unsetSessionVar('user_uid');
        static::unsetSessionVar('distinguished_name');
        static::unsetSessionVar('authntime');
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
     * @param callable $func The function to call if the current session is
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
     * @param string $idp_display_name (optional) The IdP display name. If empty,
     *        read value from PHP session.
     * @return bool True if the current IdP is an eduGAIN IdP without
     *         both REFEDS R&S and SIRTFI, AND the session could be
     *         used to get a certificate.
     */
    public static function isEduGAINAndGetCert($idp = '', $idp_display_name = '')
    {
        $retval = false; // Assume not eduGAIN IdP and getcert

        // If $idp or $idp_display_name not passed in, get from current session.
        if (strlen($idp) == 0) {
            $idp = static::getSessionVar('idp');
        }
        if (strlen($idp_display_name) == 0) {
            $idp_display_name = static::getSessionVar('idp_display_name');
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
                    '/edu.uiuc.ncsa.myproxy.getcert/',
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
            (strlen($idp_display_name) > 0) &&
            (!in_array($idp_display_name, static::$oauth2idps))) &&
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

    /**
     * setPortalOrCookieVar
     *
     * This is a convenience function for a set of operations that is done
     * a few times in Content.php. It first checks if the name of the portal
     * in the PortalCookie is empty. If not, then it sets the PortalCookie
     * key/value pair. Otherwise, it sets the 'normal' cookie key/value
     * pair.
     *
     * @param PortalCookie $pc The PortalCookie to read/write. If the portal
     *        name is empty, then use the 'normal' cookie instead.
     * @param string $key The key of the PortalCookie or 'normal' cookie to
     *        set.
     * @param string $value The value to set for the $key.
     * @param bool $save (optional) If set to true, attempt to write the
     *        PortalCookie. Defaults to false.
     */
    public static function setPortalOrCookieVar($pc, $key, $value, $save = false)
    {
        $pn = $pc->getPortalName();
        // If the portal name is valid, then set the PortalCookie key/value
        if (strlen($pn) > 0) {
            $pc->set($key, $value);
            if ($save) {
                $pc->write();
            }
        } else { // If portal name is not valid, then use the 'normal' cookie
            if (strlen($value) > 0) {
                Util::setCookieVar($key, $value);
            } else { // If $value is empty, then UNset the 'normal' cookie
                Util::unsetCookieVar($key);
            }
        }
    }

    /**
     * getOIDCClientParams
     *
     * This function addresses CIL-618 and reads OIDC client information
     * directly from the database. It is a replacement for
     * $dbs->getClient($clientparams['client_id']) which calls
     * '/dbService?action=getClient&client_id=...'. This gives the PHP
     * '/authorize' endpoint access to additional OIDC client parameters
     * without having to rewrite the '/dbService?action=getClient' endpoint.
     *
     * @param array $clientparams An array of client parameters which gets
     *              stored in the PHP session. The keys of the array are
     *              the column names of the 'client' table in the 'ciloa2'
     *              database, prefixed by 'client_'.
     * @return bool True if database query was successful. False otherwise.
     */
    public static function getOIDCClientParams(&$clientparams)
    {
        $retval = false;
        if (strlen(@$clientparams['client_id']) > 0) {
            $dsn = array(
                'phptype'  => 'mysqli',
                'username' => MYSQLI_USERNAME,
                'password' => MYSQLI_PASSWORD,
                'database' => MYSQLI_DATABASE,
                'hostspec' => MYSQLI_HOSTSPEC
            );

            $opts = array(
                'persistent'  => true,
                'portability' => DB_PORTABILITY_ALL
            );

            $db = DB::connect($dsn, $opts);
            if (!PEAR::isError($db)) {
                $data = $db->getRow(
                    'SELECT name,home_url,callback_uri,scopes from clients WHERE client_id = ?',
                    array($clientparams['client_id']),
                    DB_FETCHMODE_ASSOC
                );
                if (!DB::isError($data)) {
                    if (!empty($data)) {
                        foreach ($data as $key => $value) {
                            $clientparams['client_' . $key] = $value;
                        }
                        $clientparams['clientstatus'] = DBService::$STATUS['STATUS_OK'];
                        $retval = true;
                    }
                }
                $db->disconnect();
            }
        }
        return $retval;
    }

    /**
     * getMinMaxLifetimes
     *
     * This function checks the skin's configuration to see if either or
     * both of minlifetime and maxlifetime in the specified config.xml
     * block have been set. If not, default to minlifetime of 1 (hour) and
     * the specified defaultmaxlifetime.
     *
     * @param string $section The XML section block from which to read the
     *        minlifetime and maxlifetime values. Can be one of the
     *        following: 'pkcs12' or 'delegate'.
     * @param int $defaultmaxlifetime Default maxlifetime (in hours) for the
     *        credential.
     * @return array An array consisting of two entries: the minimum and
     *         maximum lifetimes (in hours) for a credential.
     */
    public static function getMinMaxLifetimes($section, $defaultmaxlifetime)
    {
        $minlifetime = 1;    // Default minimum lifetime is 1 hour
        $maxlifetime = $defaultmaxlifetime;
        $skin = Util::getSkin();
        $skinminlifetime = $skin->getConfigOption($section, 'minlifetime');
        // Read the skin's minlifetime value from the specified section
        if ((!is_null($skinminlifetime)) && ((int)$skinminlifetime > 0)) {
            $minlifetime = max($minlifetime, (int)$skinminlifetime);
            // Make sure $minlifetime is less than $maxlifetime;
            $minlifetime = min($minlifetime, $maxlifetime);
        }
        // Read the skin's maxlifetime value from the specified section
        $skinmaxlifetime = $skin->getConfigOption($section, 'maxlifetime');
        if ((!is_null($skinmaxlifetime)) && ((int)$skinmaxlifetime) > 0) {
            $maxlifetime = min($maxlifetime, (int)$skinmaxlifetime);
            // Make sure $maxlifetime is greater than $minlifetime
            $maxlifetime = max($minlifetime, $maxlifetime);
        }

        return array($minlifetime, $maxlifetime);
    }

    /**
     * isLOASilver
     *
     * This function returns true if the 'loa' (level of assurance)
     * should be http://incommonfederation.org/assurance/silver .
     * As specified in CACC-238, this is when both of the following are true:
     * (1) loa contains  https://refeds.org/assurance/profile/cappuccino
     * (2) acr is either https://refeds.org/profile/sfa or
     *                   https://refeds.org/profile/mfa
     *
     * @return bool True if level of assurance is 'silver'.
     */
    public static function isLOASilver()
    {
        $retval = false;
        if (
            (preg_match('%https://refeds.org/assurance/profile/cappuccino%', static::getSessionVar('loa'))) &&
            (preg_match('%https://refeds.org/profile/[ms]fa%', static::getSessionVar('acr')))
        ) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * getLOA
     *
     * This function is a bit of a hack. Once upon a time, the level of
     * assurance (loa) was one of empty string (which implied 'basic
     * CA'), 'openid' (which implied 'openid CA'), or
     * 'http://incommonfederation.org/assurance/silver' (which implied
     * 'silver CA'). Then things got more complex when the silver
     * assurance was replaced by cappuccino (see CACC-238). But parts of the
     * PHP code still depeneded on the InCommon silver string.
     *
     * This function transforms the assurance attribute asserted by an IdP
     * (which is stored in the 'loa' session variable) into one of
     * empty string (for 'basic CA'), 'openid', or
     * 'http://incommonfederation.org/assurance/silver' for use by those
     * PHP functions which expect the 'loa' in this format.
     *
     * @return string One of empty string, 'openid', or
     *         'http://incommonfederation.org/assurance/silver'
     */
    public static function getLOA()
    {
        $retval = '';
        if (static::isLOASilver()) {
            $retval = 'http://incommonfederation.org/assurance/silver';
        } else {
            $retval = static::getSessionVar('loa');
        }
        return $retval;
    }

    /**
     * getLOAPort
     *
     * This function returns the port to be used for MyProxy based on the
     * level of assurance.
     *     Basic  CA = 7512
     *     Silver CA = 7514
     *     OpenID CA = 7516
     *
     * @return int The MyProxy port number to be used based on the 'level
     *         of assurance' (basic, silver, openid).
     */
    public static function getLOAPort()
    {
        $port = 7512; // Basic
        if (Util::isLOASilver()) {
            $port = 7514;
        } elseif (Util::getSessionVar('loa') == 'openid') {
            $port = 7516;
        }
        return $port;
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
            if (preg_match('/,/', $full)) { // Split on comma if present
                $names = preg_split('/,/', $full, 2);
                $lastname =  trim(@$names[0]);
                $firstname = trim(@$names[1]);
            } else {
                $names = preg_split('/\s+/', $full, 2);
                $firstname = trim(@$names[0]);
                $lastname =  trim(@$names[1]);
            }
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
     * cleanupPKCS12
     *
     * This function scans the DEFAULT_PKCS12_DIR and removes any
     * directories (and contained files) that are older than 10 minutes.
     * This function is used by the /cleancerts/ endpoint which can
     * be called by a cronjob.
     *
     * @return int The number of PKCS12 dirs/files removed.
     */
    public static function cleanupPKCS12()
    {
        $numdel = 0;

        $pkcs12dir = DEFAULT_PKCS12_DIR;
        if (is_dir($pkcs12dir)) {
            $files = scandir($pkcs12dir);
            foreach ($files as $f) {
                if (($f != '.') && ($f != '..')) {
                    $tempdir = $pkcs12dir . $f;
                    if ((filetype($tempdir) == 'dir') && ($f != '.git')) {
                        if (time() > (600 + filemtime($tempdir))) {
                            static::deleteDir($tempdir, true);
                            $numdel++;
                        }
                    }
                }
            }
        }
        return $numdel;
    }

    /**
     * logXSEDEUsage
     *
     * This function writes the XSEDE USAGE message to a CSV file. See
     * CIL-938 and CIL-507 for background. This function first checks if the
     * XSEDE_USAGE_DIR config value is not empty and that the referenced
     * directory exists on the filesystem. If so, a CSV file is created/
     * appended using today's date. If the CSV file is new, a header
     * line is written. Then the actual USAGE line is output in the
     * following format:
     *
     *     cilogon,GMT_date,client_name,email_address
     *
     * @param string $client The name of the client. One of 'ECP', 'PKCS12',
     *        or the name of the OAuth1/OAuth2/OIDC client/portal.
     * @param string $email The email address of the user.
     */
    public static function logXSEDEUsage($client, $email)
    {
        if (
            (defined('XSEDE_USAGE_DIR')) &&
            (!empty(XSEDE_USAGE_DIR)) &&
            (is_writable(XSEDE_USAGE_DIR))
        ) {
            $error = ''; // Was there an error to be reported?

            // Get the date strings for filename and CSV line output.
            // Filename uses local time zone; log lines use GMT.
            // Save the current default timezone and restore it later.
            $deftz = date_default_timezone_get();
            $now = time();
            $datestr = gmdate('Y-m-d\TH:i:s\Z', $now);
            if (defined('LOCAL_TIMEZONE')) {
                date_default_timezone_set(LOCAL_TIMEZONE);
            }
            $filename = date('Ymd', $now) . '.upload.csv';

            // Open and lock the file
            $fp = fopen(XSEDE_USAGE_DIR . DIRECTORY_SEPARATOR . $filename, 'c');
            if ($fp !== false) {
                if (flock($fp, LOCK_EX)) {
                    // Move file pointer to the end of the file.
                    if (fseek($fp, 0, SEEK_END) == 0) { // Note 0 = success
                        $endpos = ftell($fp);
                        // If the position is at the beginning of the file (0),
                        // then the file is new, so output the HEADER line.
                        if (($endpos !== false) && ($endpos == 0)) {
                            fwrite($fp, "USED_COMPONENT,USE_TIMESTAMP,USE_CLIENT,USE_USER\n");
                        }
                        // Write the actual USAGE data line
                        fwrite($fp, "cilogon,$datestr,$client,$email\n");
                        fflush($fp);
                    } else {
                        $error = 'Unable to seek to end of file.';
                    }
                    flock($fp, LOCK_UN);
                } else { // Problem writing file
                    $error = 'Unable to lock file.';
                }
                fclose($fp);
            } else {
                $error = 'Unable to open file.';
            }

            // Restore previous default timezone
            date_default_timezone_set($deftz);

            // If got an error while opening/writing file, log it.
            if (strlen($error) > 0) {
                $log = new Loggit();
                $log->error("Error writing XSEDE USAGE file $filename: $error");
            }
        }
    }
}
