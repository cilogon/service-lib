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
use Aws\DynamoDb\SessionHandler;
use PEAR;
use DB;
use DateTime;
use Exception;

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
     * @var Bypass $bypass A 'global' Bypass object so the 'bypass' database
     *      table is read in only once.
     */
    public static $bypass = null;

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
     * @var array $oauth2idps An array of OAuth2 Identity Providers and
     * their associated "authorization URLs" stored in the database. Notice
     * that these URLs are all "http://" without any leading "www.". This
     * was done because the Google URL was the the old OAuth 1.0a URL, which
     * had http (instead of the now standard https).
     */
    public static $oauth2idps = [
        'Google'    => 'http://google.com/accounts/o8/id',
        'GitHub'    => 'http://github.com/login/oauth/authorize',
        'ORCID'     => 'http://orcid.org/oauth/authorize',
        'Microsoft' => 'http://login.microsoftonline.com/common/oauth2/v2.0/authorize',
    ];


    /**
     * getBypass
     *
     * This function initializes the class $bypass object (if not yet
     * created) and returns it. This allows for a single 'global'
     * $bypass to be used by other classes (since we want to read the
     * bypass table from the database only once).
     *
     * @return Bypass|null The class instantiated Bypass object.
     **/
    public static function getBypass()
    {
        if (is_null(static::$bypass)) {
            static::$bypass = new Bypass();
        }
        return static::$bypass;
    }

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
     * getDB
     *
     * This function initializes a PEAR DB connection object returned
     * by DB::connect() suitable for future DB calls. If there is a
     * problem, the returned object is null.
     *
     * @param $readonly Should we use the read-only database endpoint
     *        (if configured)? Defaults to false.
     * @return DB A PEAR DB object connected to a database, or null
     *         on error connecting to database.
     */
    public static function getDB($readonly = false)
    {
        $retval = null;

        // CIL-1769 Enable selection of read-only database endpoint
        $hostspec = DB_HOSTSPEC;
        if (($readonly) && defined('DB_HOSTSPEC_RO')) {
            $hostspec = DB_HOSTSPEC_RO;
        }

        $db_const = new DB(); // So constants defined in DB.php are read in
        $dsn = array(
            'phptype'  => DB_TYPE,
            'username' => DB_USERNAME,
            'password' => DB_PASSWORD,
            'hostspec' => $hostspec,
            'database' => DB_DATABASE
        );

        $opts = array(
            'persistent'  => true,
            'portability' => DB_PORTABILITY_ALL
        );

        $retval = DB::connect($dsn, $opts);
        if (PEAR::isError($retval)) {
            $retval = null;
        }
        return $retval;
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
        setcookie($cookie, $value, array(
            'expires' => $exp,
            'path' => '/',
            'domain' => '.' . static::getDN(),
            'secure' => true,
            'httponly' => false,
            'samesite' => 'None'
        ));
        $_COOKIE[$cookie] = $value;
        static::dedupeCookies();
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
        setcookie($cookie, '', array(
            'expires' => 1,
            'path' => '/',
            'domain' => '.' . static::getDN(),
            'secure' => true,
            'httponly' => false,
            'samesite' => 'None'
        ));
        unset($_COOKIE[$cookie]);
        static::dedupeCookies();
    }

    /**
     * dedupeCookies
     *
     * This function scans the list of 'Set-Cookie' headers and removes
     * any duplicate entries, keeping only the most recent cookies.
     * This function was adapted from code found at
     * https://stackoverflow.com/a/43638878/12381604 .
     */
    public static function dedupeCookies()
    {
        if (!headers_sent()) {
            $cookie_set = []; // Array to store the most recent cookies
            $dedupe_cookies = false; // Multiple values detected for same cookie name?

            foreach (headers_list() as $header_string) {
                if (stripos($header_string, 'Set-Cookie:') !== false) {
                    list($set_cookie, $cookie_string) = explode(':', $header_string, 2);
                    // $set_cookie = 'Set-Cookie'
                    // $cookie_string = ' CSRF=deleted; expires=Thu, 01-Jan-1970 00:00:01 GMT;
                    //                   Max-Age=0; path=/; domain=.cilogon.org; secure'
                    list ($cookie_name, $cookie_rest) = explode('=', trim($cookie_string), 2);
                    // $cookie_name = 'CSRF'
                    // $cookie_rest = 'deleted; expires=Thu, 01-Jan-1970 00:00:01 GMT;
                    //                 Max-Age=0; path=/; domain=.cilogon.org; secure'
                    if (array_key_exists($cookie_name, $cookie_set)) {
                        $dedupe_cookies = true;
                    }
                    $cookie_set[$cookie_name] = $cookie_rest; // Keeps the most recent
                }
            }

            if ($dedupe_cookies) {
                header_remove('Set-Cookie'); // Removes ALL cookies
                foreach ($cookie_set as $name => $rest) {
                    list ($value, $param_string) = explode(';', $rest, 2);
                    // $value = 'deleted'
                    // $param_string = ' expires=Thu, 01-Jan-1970 00:00:01 GMT;
                    //                  Max-Age=0; path=/; domain=.cilogon.org; secure'
                    // Put each cookie parameter in an array
                    $params = explode(';', $param_string);
                    $expires = 0;
                    $path = '';
                    $domain = '';
                    $secure = false;
                    $httponly = false;
                    $samesite = 'None';
                    foreach ($params as $param) {
                        if (preg_match('/^\s*Expires=(.*)/i', $param, $matches)) {
                            try {
                                $date = new DateTime($matches[1]);
                                $expires = $date->format('U'); // Unix timestamp
                            } catch (\Exception $e) {
                                $log = new Loggit();
                                $log->error('DateTime exception ' . $e->getMessage());
                            }
                        } elseif (preg_match('/^\s*Path=(.*)/i', $param, $matches)) {
                            $path = $matches[1];
                        } elseif (preg_match('/^\s*Domain=(.*)/i', $param, $matches)) {
                            $domain = $matches[1];
                        } elseif (preg_match('/^\s*Secure$/i', $param, $matches)) {
                            $secure = true;
                        } elseif (preg_match('/^\s*HttpOnly$/i', $param, $matches)) {
                            $httponly = true;
                        } elseif (preg_match('/^\s*SameSite$/i', $param, $matches)) {
                            $samesite = $matches[1];
                        }
                    }

                    setrawcookie($name, $value, array(
                        'expires' => $expires,
                        'path' => $path,
                        'domain' => $domain,
                        'secure' => $secure,
                        'httponly' => $httponly,
                        'samesite' => $samesite
                    ));
                }
            }
        }
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
     * cilogonInit
     *
     * This function calls any necessary start up functions, e.g.,
     * starting the PHP session.
     */
    public static function cilogon_init()
    {
        static::startPHPSession();
        static::setLanguage();
    }

    /**
     * startPHPSession
     *
     * This function starts a secure PHP session and should be called
     * at the beginning of each script before any HTML is output. It
     * does a trick of setting a 'lastaccess' time so that the
     * $_SESSION variable does not expire without warning.
     */
    public static function startPHPSession()
    {
        // If PHPSESSIONS_STORAGE is not defined in config.php, default to
        // saving PHP sessions to file.
        if (!defined('PHPSESSIONS_STORAGE')) {
            define('PHPSESSIONS_STORAGE', 'file');
        }

        if (PHPSESSIONS_STORAGE == 'database') {
            $sessionmgr = new SessionMgr();
        } elseif (PHPSESSIONS_STORAGE == 'dynamodb') {
            $dynamoDb = new \Aws\DynamoDb\DynamoDbClient([
                'region' => DYNAMODB_REGION,
                'credentials' => [
                    'key' => DYNAMODB_PHPSESSIONS_ACCESSKEY,
                    'secret' => DYNAMODB_PHPSESSIONS_SECRETACCESSKEY,
                ],
            ]);
            $sessionHandler = SessionHandler::fromClient($dynamoDb, [
                'table_name' => DYNAMODB_PHPSESSIONS_TABLE,
            ]);
            $sessionHandler->register();
        } else { // Default to saving PHP sessions to file.
            // If storing PHP sessions to file, check if an optional directory
            // for storage has been set. If so, create it if necessary.
            if ((defined('PHPSESSIONS_DIR')) && (!empty(PHPSESSIONS_DIR))) {
                if (!is_dir(PHPSESSIONS_DIR)) {
                    mkdir(PHPSESSIONS_DIR, 0770, true);
                }

                if (is_dir(PHPSESSIONS_DIR)) {
                    ini_set('session.save_path', PHPSESSIONS_DIR);
                }
            }
        }

        // CIL-1879 Set options for PHPSESSID cookie
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
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
     * setLanguage
     *
     * This function sets the language to be used for text output by
     * gettext() (shorthand _() ). It checks if the skin has a
     * 'languages' configuration with at least one 'lang'. Next, it
     * checks for a 'lang' cookie. If the cookie matches one of the
     * skin's langs, then it attempts to get the locale.
     */
    public static function setLanguage()
    {
        // Check if languages are enabled in the skin
        $languages = $skin->getConfigOption('languages');

        if ((!is_null($languages)) && (!empty($languages->lang))) {
            // One or more languages configured.
            // Save them to a cookie, separated by spaces.
            $availlang = '';
            foreach ($languages->lang as $lang) {
                $availlang .= (string)$lang . ' ';
            }
            $availlang = trim($availlang);
            static::setCookieVar('langsavailable', $availlang);

            $setlang = ''; // The language to set

            // Check if there is a "lang" cookie
            $cookielang = static::getCookieVar('lang');
            if (strlen($cookielang) > 0) {
                foreach (explode(' ', $availlang) as $lang) {
                    if ($lang == $cookielang) {
                        $setlang = $lang;
                    }
                }
            } else { // No cookie? Check if skin has a default language
                $defaultlanguage = $skin->getConfigOptions('defaultlanguage');
                if (!is_null($defaultlanguage)) {
                    $setlang = (string)$defaultlanguage;
                }
            }

            // If we found a language to set, then try to set the locale
            if (strlen($setlang) > 0) {
                if (setlocale(LC_ALL, $setlang) !== false) {
                    putenv('LC_ALL=' . $setlang);
                    define('TEXT_DOMAIN', 'cilogon');
                    bindtextdomain(TEXT_DOMAIN, $_SERVER['DOCUMENT_ROOT'] . '/locale');
                    bind_textdomain_codeset(TEXT_DOMAIN, 'UTF-8');
                    textdomain(TEXT_DOMAIN);
                }
            }
        } else {
            // No languages configured in skin.
            // Delete available languges cookie which would be read by
            // JavaScript to create the languages pulldown menu.
            static::unsetCookieVar('langsavailable');
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
                            @exec('/usr/bin/env /usr/bin/shred -u -z ' . $dir . "/" . $object);
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
            'preferred_username' => 'Preferred Username',
            'entitlement'        => 'Entitlement',
            'itrustuin'          => 'iTrustUIN',
            'eduPersonOrcid'     => 'eduPersonOrcid',
            'uidNumber'          => 'uidNumber',
            'cilogon_skin'       => 'Skin Name',
            'authntime'          => 'Authn Time'
        );

        $remoteaddr = static::getServerVar('REMOTE_ADDR');
        $remotehost = gethostbyaddr($remoteaddr);
        $mailfrom = 'From: ' . EMAIL_ALERTS . "\r\n" .
                    'X-Mailer: PHP/' . phpversion();
        $mailsubj = 'CILogon Service on ' . static::getHN() .
                    ' (' . php_uname('n') . ') - ' . $summary;
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
     * server. It returns DEFAULT_DOMAINNAME, defined in the top-level
     * config.php file. If not defined, the last two segments of the
     * hostname (from getHN()) is returned.
     *
     * @return string The 'Domainname' for the web server.
     */
    public static function getDN()
    {
        $thedomainname = '';
        if ((defined('DEFAULT_DOMAINNAME')) && (!empty(DEFAULT_DOMAINNAME))) {
            $thedomainname = DEFAULT_DOMAINNAME;
        } else {
            $thedomainname = static::getHN();
            if (preg_match('/[^\.]+\.[^\.]+$/', $thedomainname, $matches)) {
                $thedomainname = $matches[0];
            }
        }
        return $thedomainname;
    }

    /**
     * getOAuth2Url
     *
     * This funtion takes in the name of an IdP (e.g., 'Google') and
     * returns the assoicated OAuth2 authorization URL.
     *
     * @param string $idp The name of an OAuth2 Identity Provider.
     * @return string The authorization URL for the given IdP.
     */
    public static function getOAuth2Url($idp)
    {
        $url = null;
        if (array_key_exists($idp, static::$oauth2idps)) {
            $url = static::$oauth2idps[$idp];
        }
        return $url;
    }

    /**
     * getOAuth2IdP
     *
     * This function takes in the OAuth2 authorization URL and returns
     * the associated pretty-print name of the IdP.
     *
     * @param string $url The authorization URL of an OAuth2 Identity Provider.
     * @return string The name of the IdP.
     */
    public static function getOAuth2IdP($url)
    {
        $idp = null;
        $url = static::normalizeOAuth2IdP($url);
        if (in_array($url, static::$oauth2idps)) {
            $idp = array_search($url, static::$oauth2idps);
        }
        return $idp;
    }

    /**
     * normalizeOAuth2IdP
     *
     * This function takes in a URL for one of the CILogon-supported OAuth2
     * issuers (i.e., Google, GitHub, ORCID, Microsoft) and transforms it
     * into a URL used by CILogon as shown in the 'Select an Identity
     * Provider' list.
     *
     * @param string An OAuth2 issuer string (i.e., 'iss') for one of the
     *        OAuth2 IdPs supported by CILogon.
     * @return string The input string transformed to a URL to be used in
     *         the 'Select an Identity Provider' list. If the incoming
     *         string does not match one of the OAuth2 issuers, the string
     *         is returned unmodified.
     */
    public static function normalizeOAuth2IdP($idp)
    {
        if (
            (!preg_match('%^https?://accounts.google.com/o/saml2%', $idp)) &&
            (preg_match('%^https?://(accounts.)?google.com%', $idp))
        ) {
            $idp = static::getOAuth2Url('Google');
        } elseif (preg_match('%^https?://github.com%', $idp)) {
            $idp = static::getOAuth2Url('GitHub');
        } elseif (preg_match('%^https?://orcid.org%', $idp)) {
            $idp = static::getOAuth2Url('ORCID');
        } elseif (preg_match('%^https?://login.microsoftonline.com%', $idp)) {
            $idp = static::getOAuth2Url('Microsoft');
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
        // In case of STATUS_EPTID_MISMATCH or STATUS_PAIRWISE_ID_MISMATCH,
        // call dbService again setting those parameters to empty strings
        $try_without_eptid_or_pairwise_id = false;
        $dbs = new DBService();
        $log = new Loggit();

        // Save the passed-in variables to the session for later use
        // (e.g., by the error handler in handleGotUser). Then get these
        // session variables into local vars for ease of use.
        static::setUserAttributeSessionVars(...$args);

        // This bit of trickery sets local variables from the PHP session
        // that was just populated, using the names in the $user_attrs array.
        foreach (DBService::$user_attrs as $value) {
            $$value = static::getSessionVar($value);
        }

        // Call the dbService to get the user using IdP attributes.
        do {
            $try_without_eptid_or_pairwise_id = false;
            $remote_user = ''; // CIL-1968 Don't sent remote_user to dbService
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
                $preferred_username,
                $entitlement,
                $itrustuin,
                $eduPersonOrcid,
                $uidNumber
            );
            if ($result) {
                // CIL-1674 If STATUS_EPTID_MISMATCH, try again without eptid.
                // CIL-2243 If STATUS_PAIRWISE_ID_MISMATCH, try again without
                // pairwise_id.
                // To revert to old behavior of treating STATUS_EPTID_MISMATCH
                // or STATUS_PAIRWISE_ID_MISMATCH as an error, define
                // EPTID_MISMATCH_IS_WARNING as false in the top-level
                // config.php file.
                if (
                    (
                        ($dbs->status == DBService::$STATUS['STATUS_EPTID_MISMATCH']) ||
                        ($dbs->status == DBService::$STATUS['STATUS_PAIRWISE_ID_MISMATCH'])
                    ) &&
                    (
                        (!defined('EPTID_MISMATCH_IS_WARNING')) ||
                        (EPTID_MISMATCH_IS_WARNING)
                    )
                ) {
                    $eptid = '';
                    $pairwise_id = '';
                    $try_without_eptid_or_pairwise_id = true;
                    $log->warn(
                        'Warning in Util::saveUserToDataStore(): ' .
                        'DBService returned "' .
                        DBService::statusToStatusText($dbs->status) .
                        '". Trying again without eptid or pairwise_id.'
                    );
                } else {
                    static::setSessionVar('user_uid', $dbs->user_uid);
                    static::setSessionVar('distinguished_name', $dbs->distinguished_name);
                    static::setSessionVar('status', $dbs->status);
                }
            } else {
                $log->error(
                    'Error in Util::saveUserToDataStore(): ' .
                    'Error calling dbservice action "getUser".'
                );
                static::sendErrorAlert(
                    'dbService Error',
                    'Error calling dbservice action "getUser" in ' .
                    'saveUserToDatastore() method.'
                );
                static::unsetSessionVar('user_uid');
                static::unsetSessionVar('distinguished_name');
                static::setSessionVar('status', DBService::$STATUS['STATUS_INTERNAL_ERROR']);
            }
        } while ($try_without_eptid_or_pairwise_id);

        // If 'status' is not STATUS_OK*, then send an error email
        $status = static::getSessionVar('status');
        if ($status & 1) { // Bad status codes are odd
            // For missing parameter errors, log an error message
            if (
                $status ==
                DBService::$STATUS['STATUS_MISSING_PARAMETER_ERROR']
            ) {
                $log->error('Error in Util::saveUserToDataStore(): STATUS_MISSING_PARAMETER_ERROR', true);
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
                    'Pref Username = ' . ((strlen($preferred_username) > 0) ?
                        $preferred_username : '<MISSING>') . "\n" .
                    'Entitlement   = ' . ((strlen($entitlement) > 0) ?
                        $entitlement : '<MISSING>') . "\n" .
                    'iTrustUIN     = ' . ((strlen($itrustuin) > 0) ?
                        $itrustuin : '<MISSING>') . "\n" .
                    'eduPersonOrcid= ' . ((strlen($eduPersonOrcid) > 0) ?
                        $eduPersonOrcid : '<MISSING>') . "\n" .
                    'uidNumber     = ' . ((strlen($uidNumber) > 0) ?
                        $uidNumber : '<MISSING>') . "\n" .
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

        // Specific to OAuth 1.0a flow
        static::unsetSessionVar('portalstatus');
        static::unsetSessionVar('callbackuri');
        static::unsetSessionVar('successuri');
        static::unsetSessionVar('failureuri');
        static::unsetSessionVar('portalname');
        static::unsetSessionVar('tempcred');

        // Specific to OIDC flow
        static::unsetSessionVar('clientparams');

        // CIL-2348 Admin client ID and name
        static::unsetSessionVar('admin_id');
        static::unsetSessionVar('admin_name');
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
                static::setCookieVar($key, $value);
            } else { // If $value is empty, then UNset the 'normal' cookie
                static::unsetCookieVar($key);
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

        // CIL-1591 Log errors
        $log = new Loggit();

        if (strlen(@$clientparams['client_id']) > 0) {
            $db = static::getDB(true);
            if (!is_null($db)) {
                $data = $db->getRow(
                    'SELECT name,home_url,callback_uri,scopes from clients WHERE client_id = ?',
                    array($clientparams['client_id']),
                    DB_FETCHMODE_ASSOC
                );
                if ((!DB::isError($data)) && (!empty($data))) {
                    foreach ($data as $key => $value) {
                        $clientparams['client_' . $key] = $value;
                    }
                    $clientparams['clientstatus'] = DBService::$STATUS['STATUS_OK'];
                    $retval = true;
                } else {
                    $log->error('getOIDCClientParams: error reading data; ' .
                        $db->getMessage());
                }
                $db->disconnect();
            } else {
                $log->error('getOIDCClientParams: $db connect is null');
            }
        } else {
            $log->error('getOIDCClientParams: missing client_id');
        }
        return $retval;
    }

    /**
     * getAdminForClient
     *
     * Given a client_id, return the admin_id of the admin client which
     * created the client. If client_id was not created by an admin client,
     * return empty string.
     *
     * @param string $client_id The client_id to check.
     * @return array An associative array containing the 'admin_id' and
     *         'admin_name' of the admin client which created the client,
     *         or an empty array if no matching admin client was found.
     */
    public static function getAdminForClient($client_id)
    {
        $retval = array();
        // Keep track of the client_ids (and their admin_ids/admin names)
        // already searched for; limits the number of database calls.
        static $clienttoadminmap = array();

        if (strlen($client_id) > 0) {
            // If we already did a database search for $client_id,
            // return the previously matched admin (or empty array if
            // the client_id didn't have a matching admin_id)
            if (array_key_exists($client_id, $clienttoadminmap)) {
                $retval = $clienttoadminmap[$client_id];
            } else { // Search the database for the client_id's admin_id+name
                $db = static::getDB(true);
                if (!is_null($db)) {
                    $data = $db->getRow(
                        "SELECT admin_id,name FROM adminClients WHERE admin_id IN " .
                        "(SELECT admin_id FROM permissions WHERE client_id = ?)",
                        array($client_id),
                        DB_FETCHMODE_ASSOC
                    );
                    if (
                        (!DB::isError($data)) &&
                        (!empty($data)) &&
                        (strlen(@$data['admin_id']) > 0)
                    ) {
                        $retval['admin_id'] = (string)(@$data['admin_id']);
                        $retval['admin_name'] = (string)(@$data['name']);
                    }
                    $db->disconnect();
                    // Save this client_id's query results for next time
                    $clienttoadminmap[$client_id] = $retval;
                }
            }

            // CIL-2348 Put admin ID and name in session for logging
            if (array_key_exists('admin_id', $retval)) {
                static::setSessionVar('admin_id', $retval['admin_id']);
            } else {
                static::unsetSessionVar('admin_id');
            }
            if (array_key_exists('admin_name', $retval)) {
                static::setSessionVar('admin_name', $retval['admin_name']);
            } else {
                static::unsetSessionVar('admin_name');
            }
        }

        return $retval;
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
     * updateIdPList
     *
     * This function is called by the '/updateidplist/' endpoint to update
     * the CILogon idplist.xml and idplist.json files. These files are 'pared
     * down' versions of the IdP-specific InCommon-metadata.xml file,
     * extracting just the useful portions of XML for display on CILogon.
     * This endpoint downloads the InCommon metadata and creates both
     * idplist.xml and idplist.json. It then looks for existing
     * idplist.{json,xml} files and sees if there are any differences. If so,
     * it prints out the differences and sends email. It also checks for
     * newly added IdPs and sends email. Finally, it copies the newly created
     * idplist files to the old location.
     */
    public static function updateIdPList()
    {
        // Make sure there is at least one Metadata URL defined
        if ((!defined('DEFAULT_METADATA_URLS')) || (empty(DEFAULT_METADATA_URLS))) {
            return;
        }

        // Use a semaphore to prevent processes running at the same time
        $idplist_dir = dirname(DEFAULT_IDP_JSON);
        $check_filename = $idplist_dir . '/.last_checked';
        $key = ftok($check_filename, '1');
        if ($key == -1) {
            echo "<p>Another process is running.</p>\n";
            return;
        }
        $semaphore = sem_get($key, 1);
        if (($semaphore === false) || (sem_acquire($semaphore, 1) === false)) {
            echo "<p>Another process is running.</p>\n";
            return;
        }

        $mailto = EMAIL_ALERTS;
        $mailtoidp = defined('EMAIL_IDP_UPDATES') ?
            EMAIL_ALERTS . ',' . EMAIL_IDP_UPDATES : '';
        $mailfrom = 'From: ' . EMAIL_ALERTS . "\r\n" .
            'X-Mailer: PHP/' . phpversion();
        $httphost = static::getHN();

        // Get the HEAD of the metadata URLs and check Last-Modified
        $max_last_modified = 0;
        foreach (DEFAULT_METADATA_URLS as $url) {
            $head = get_headers($url, true);
            if (($head === false) || (!array_key_exists('Last-Modified', $head))) {
                $errmsg = "Error: Unable to fetch headers for $url ";
                echo "<p>$errmsg</p>\n";
                mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
                http_response_code(500);
                return;
            }

            // Convert last-modified header to Unix time
            $last_modified = strtotime($head['Last-Modified']);
            // Keep the most recently modified time
            if ($last_modified > $max_last_modified) {
                $max_last_modified = $last_modified;
            }
        }

        // CIL-2233 Check the modification time of the TEST_IDP_XML file
        $test_modified = 0;
        if (
            (defined('TEST_IDP_XML')) &&
            (!empty(TEST_IDP_XML)) &&
            (is_readable(TEST_IDP_XML)) &&
            (($test_modified = filemtime(TEST_IDP_XML)) !== false) &&
            ($test_modified > $max_last_modified)
        ) {
            $max_last_modified = $test_modified;
        }

        // Compare the most recent HEAD Last-Modified with the
        // previously saved timestamp
        $saved_modified = file_get_contents($check_filename);
        if (
            ($saved_modified !== false) &&
            (strcmp($max_last_modified, $saved_modified) == 0)
        ) {
            echo "<p>No change detected in metadata.</p>\n";
            return;
        }
        // Save the Last-Modified timestamp.
        file_put_contents($check_filename, $max_last_modified);

        // Download metadata to a new temporary directory.
        // Delete the temporary directory when the script exits.
        $tmpdir = static::tempDir(sys_get_temp_dir());
        register_shutdown_function(['CILogon\Service\Util','deleteDir'], $tmpdir);
        $tmpmetadata = array();
        foreach (DEFAULT_METADATA_URLS as $idx => $url) {
            $tmpmetadata[$idx] = "$tmpdir/$idx.xml";
            $xmldownloaded = false;
            if (($ch = curl_init()) !== false) {
                if (($fp = fopen($tmpmetadata[$idx], 'w')) !== false) {
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    $xmldownloaded = curl_exec($ch);
                    fflush($fp);
                    fclose($fp);
                }
                curl_close($ch);
            }

            if (!$xmldownloaded) {
                $errmsg = "Error: Unable to save metadata for $url to temporary directory.";
                echo "<p>$errmsg</p>\n";
                mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
                http_response_code(500);
                return;
            }
        }

        // Now, create new idplist.xml and idplist.json files from the
        // downloaded metadata files
        $tmpxml = $tmpdir . '/idplist.xml';
        $newidplist = new IdpList($tmpxml, $tmpmetadata, false, 'xml');
        $newidplist->create();
        if (!$newidplist->write('xml')) {
            $errmsg = "Error: Unable to create temporary idplist.xml file.";
            echo "<p>$errmsg</p>\n";
            mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
            http_response_code(500);
            return;
        }
        $tmpjson = $tmpdir . '/idplist.json';
        $newidplist->setFilename($tmpjson);
        if (!$newidplist->write('json')) {
            $errmsg = "Error: Unable to create temporary idplist.json file.";
            echo "<p>$errmsg</p>\n";
            mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
            http_response_code(500);
            return;
        }

        // Try to read in an existing idplist.xml file so we can 'diff' later
        $idpxml_filename = preg_replace('/\.json$/', '.xml', DEFAULT_IDP_JSON);
        $oldidplist = new IdpList($idpxml_filename, '', false, 'xml');

        // If we successfully read in an existing idplist.xml file,
        // check for differences, and also look for newly added IdPs.
        $oldidplistempty = true;
        $oldidplistdiff = false;
        $idpemail = '';
        if (!empty($oldidplist->idparray)) {
            $oldidplistempty = false;

            // CIL-1271 First, check for entityID differences
            $newkeys = array_keys($newidplist->idparray);
            $oldkeys = array_keys($oldidplist->idparray);
            $diffarray = array_merge(
                array_diff($newkeys, $oldkeys),
                array_diff($oldkeys, $newkeys)
            );

            if (empty($diffarray)) {
                // Next, check for IdP attribute differences using tricky
                // json_encode method found in a comment at
                // https://stackoverflow.com/a/42530586/12381604
                $newjson = array_map('json_encode', $newidplist->idparray);
                $oldjson = array_map('json_encode', $oldidplist->idparray);
                $diffarray = array_map(
                    'json_decode',
                    array_merge(
                        array_diff($newjson, $oldjson),
                        array_diff($oldjson, $newjson)
                    )
                );
            }

            if (!empty($diffarray)) {
                $oldidplistdiff = true;

                $oldIdPs = array();
                $newIdPs = array();
                $oldEntityIDs = $oldidplist->getEntityIDs();
                $newEntityIDs = $newidplist->getEntityIDs();

                // Check to see if any new IdPs were added to the metadata.
                if (!empty($oldEntityIDs)) {
                    foreach ($newEntityIDs as $value) {
                        if (!in_array($value, $oldEntityIDs)) {
                            $newIdPs[$value] = 1;
                        }
                    }
                }

                // Check to see if any old IdPs were removed.
                if (!empty($newEntityIDs)) {
                    foreach ($oldEntityIDs as $value) {
                        if (!in_array($value, $newEntityIDs)) {
                            $oldIdPs[$value] = 1;
                        }
                    }
                }

                // If new IdPs were added or old IdPs were removed, save them
                // in a string to be emailed to idp-updates@cilogon.org.
                if ((!empty($newIdPs)) || (!empty($oldIdPs))) {
                    // First, show any new IdPs added
                    if (empty($newIdPs)) {
                        $idpemail .= "No new Identity Providers were found in metadata.\n";
                    } else {
                        $plural = (count($newIdPs) > 1);
                        $idpemail .= ($plural ? 'New' : 'A new') . ' Identity Provider' .
                            ($plural ? 's were' : ' was') . ' found in metadata ' .
                            "and\nADDED to the list of available IdPs.\n" .
                            '--------------------------------------------------------------' .
                            "\n\n";
                        foreach ($newIdPs as $entityID => $value) {
                            $idpemail .= "EntityId               = $entityID\n";
                            $idpemail .= "Organization Name      = " .
                                $newidplist->getOrganizationName($entityID) . "\n";
                            $idpemail .= "Display Name           = " .
                                $newidplist->getDisplayName($entityID) . "\n";
                            if ($newidplist->isRegisteredByInCommon($entityID)) {
                                $idpemail .= "Registered by InCommon = Yes\n";
                            }
                            if ($newidplist->isInCommonRandS($entityID)) {
                                $idpemail .= "InCommon R & S         = Yes\n";
                            }
                            if ($newidplist->isREFEDSRandS($entityID)) {
                                $idpemail .= "REFEDS R & S           = Yes\n";
                            }
                            if ($newidplist->isSIRTFI($entityID)) {
                                $idpemail .= "SIRTFI                 = Yes\n";
                            }
                            $idpemail .= "\n";
                        }
                    }

                    // Then, show any old IdPs removed
                    if (!empty($oldIdPs)) {
                        $idpemail .= "\n" .
                            '==============================================================' .
                            "\n\n" .
                            'One or more Identity Providers were removed from ' .
                            "metadata and\n" .
                            "DELETED from the list of available IdPs.\n" .
                            '--------------------------------------------------------------' .
                            "\n\n";
                        foreach ($oldIdPs as $entityID => $value) {
                            $idpemail .= "EntityId               = $entityID\n";
                            $idpemail .= "Organization Name      = " .
                                $oldidplist->getOrganizationName($entityID) . "\n";
                            $idpemail .= "Display Name           = " .
                                $oldidplist->getDisplayName($entityID) . "\n";
                            if ($oldidplist->isRegisteredByInCommon($entityID)) {
                                $idpemail .= "Registered by InCommon = Yes\n";
                            }
                            if ($oldidplist->isInCommonRandS($entityID)) {
                                $idpemail .= "InCommon R & S         = Yes\n";
                            }
                            if ($oldidplist->isREFEDSRandS($entityID)) {
                                $idpemail .= "REFEDS R & S           = Yes\n";
                            }
                            if ($oldidplist->isSIRTFI($entityID)) {
                                $idpemail .= "SIRTFI                 = Yes\n";
                            }
                            $idpemail .= "\n";
                        }
                    }
                }
            }
        }

        // Copy temporary idplist.{json,xml} files to production directory.
        if ($oldidplistempty || $oldidplistdiff) {
            $idpdiff = `diff -u $idpxml_filename $tmpxml 2>&1`;
            // CIL-2254 PHP 'copy' might not be atomic. Use OS 'cp' instead.
            $output = null;
            $result_code = null;
            exec("cp $tmpxml $idplist_dir" . '/idplist.xml &>/dev/null', $output, $result_code);
            if ($result_code == 0) {
                @chmod($idpxml_filename, 0664);
                @chgrp($idpxml_filename, 'apache');
            } else {
                $errmsg = "Error: Unable to copy idplist.xml to destination.";
                echo "<p>$errmsg</p>\n";
                mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
                http_response_code(500);
                return;
            }
            // CIL-2254 Use OS's 'cp' for atomic copy operation.
            $output = null;
            $result_code = null;
            exec("cp $tmpjson $idplist_dir" . '/idplist.json &>/dev/null', $output, $result_code);
            if ($result_code == 0) {
                @chmod(DEFAULT_IDP_JSON, 0664);
                @chgrp(DEFAULT_IDP_JSON, 'apache');
            } else {
                $errmsg = "Error: Unable to copy idplist.json to destination.";
                echo "<p>$errmsg</p>\n";
                mail($mailto, "/updateidplist/ failed on $httphost", $errmsg, $mailfrom);
                http_response_code(500);
                return;
            }

            // If we found new IdPs, print them out and send email (if on prod).
            if (strlen($idpemail) > 0) {
                echo "<xmp>\n";
                echo $idpemail;
                echo "</xmp>\n";

                if (strlen($mailtoidp) > 0) {
                    // Send "New IdPs Added" email only from production server.
                    if (
                        ($httphost == 'cilogon.org') ||
                        ($httphost == 'polo1.cilogon.org')
                    ) {
                        mail(
                            $mailtoidp,
                            "CILogon Service on $httphost - New IdP Automatically Added",
                            $idpemail,
                            $mailfrom
                        );
                    }
                }
            }

            // If other differences were found, do an actual 'diff' and send email.
            if ($oldidplistdiff) {
                echo "<xmp>\n\n";
                echo $idpdiff;
                echo "</xmp>\n";

                mail(
                    $mailto,
                    "idplist.xml changed on $httphost",
                    "idplist.xml changed on $httphost\n\n" . $idpdiff,
                    $mailfrom
                );
            }

            if ($oldidplistempty) {
                echo "<h3>New idplist.{json,xml} files were created.</h3>\n";
            } else {
                echo "<h3>Existing idplist.{json,xml} files were updated.</h3>\n";
            }
        } else {
            echo "<p>No change detected in InCommon metadata.</p>\n";
        }

        @sem_release($semaphore);
    }

    /**
     * getLastSSOIdP
     *
     * CIL-1369 Special Single Sign-On (SSO) handling for OIDC clients.
     * This function checks if the current OIDC transaction $client_id
     * has an associated admin client, and that admin client is configured
     * to support SSO. If so, return the previously used IdP which can
     * be compared against the current IdP. If they are equal, then bypass
     * the "Select an Identity Provider" page. As a side effect, this
     * function also saves the current IdP for checking the next time
     * the user attempts to use an OIDC client from the same CO.
     *
     * This function should be called twice:
     * (1) before the IdP list is shown, to determine if we should use
     * the current session info and bypass IdP selection, and
     * (2) after the user has successfully logged on so the successful
     * IdP used can be saved to the session for next SSO calculation.
     *
     * @param bool $saveidp If true, save the current IdP to the session
     *        sso_idp_array for the next time. This is typically done after
     *        the user has chosen an IdP. Defaults to false.
     * @return string The previously used SSO IdP, or empty string if
     *         the transaction is not eligible for SSO.
     */
    public static function getLastSSOIdP($saveidp = false)
    {
        $last_sso_idp = '';
        $client_id = '';
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        if (isset($clientparams['client_id'])) {
            $client_id = $clientparams['client_id'];
        }

        if (strlen($client_id) > 0) {
            // Search for an admin client corresponding to the $client_id
            $admin = static::getAdminForClient($client_id);
            $admin_id = '';
            $admin_name = '';
            if (!empty($admin)) {
                $admin_id = @$admin['admin_id'];
                $admin_name = @$admin['admin_name'];
            }

            // Read in the SSO_ADMIN_ARRAY from config.php or the bypass
            // table (where type='sso'). This array has entries like:
            //    admin_id => CO_name
            // Then search the array for a matching $admin_id to get
            // the corresponding CO_name.
            $co_name = '';
            if (strlen($admin_id) > 0) {
                $sso_admin_array = static::getBypass()->getSSOAdminArray();
                if (
                    (!is_null($sso_admin_array)) &&
                    (array_key_exists($admin_id, $sso_admin_array))
                ) {
                    $co_name = $sso_admin_array[$admin_id];
                }
            }

            // Get the sso_idp_array session value. This array has
            // entries like:
            //     CO_name => idp_entity_id
            // Then search the array for a matching $co_name to get
            // the corresponding IdP. If this transaction is one worthy
            // of SSO, we will later update the $sso_idp_array with
            // the new entry and save it back to the sso_idp_array session
            // variable.
            if (strlen($co_name) > 0) {
                $sso_idp_array = static::getSessionVar('sso_idp_array');
                if (!is_array($sso_idp_array)) { // Doesn't exist yet!
                    $sso_idp_array = array();
                }
                if (
                    (!empty($sso_idp_array)) &&
                    (array_key_exists($co_name, $sso_idp_array))
                ) {
                    $last_sso_idp = $sso_idp_array[$co_name];
                }

                // Finally, make the decision if this transaction should use
                // SSO. If the $co_name matches the name of the current
                // $client_id's admin client, then allow SSO for this CO/VO,
                // Update the $sso_idp_array with the current session IdP if
                // the passed in $saveidp is true.
                if (preg_match("/^$co_name/", $admin_name)) {
                    if ($saveidp) {
                        $sso_idp_array[$co_name] = Util::getSessionVar('idp');
                        $_SESSION['sso_idp_array'] = $sso_idp_array;
                    }
                } else {
                    $last_sso_idp = ''; // No match - reset the return value
                }
            }
        }

        return $last_sso_idp;
    }

    /**
     * getRecentIdPs
     *
     * This function returns the IdPs in the 'recentidps' cookie formatted
     * as an array. If the incoming $entityID parameter is not emtpy, this
     * function also pushes the $entityID onto the front of the array and
     * saves the array to the 'recentidps' cookie formatted as a
     * comma-separated string of IdPs.
     *
     * @param string $entityID If not empty, save this IdP to the
     *        'recentidps' cookie. This is typically done after
     *        the user has selected an IdP. If $entityID is empty,
     *        simply return the 'recentidps' cookie as an array.
     * @return array An array of recently used IdPs.
     */
    public static function getRecentIdPs($entityID = '')
    {
        $idps = static::getCookieVar('recentidps');

        // CIL-2268 If no recent IdPs, then use ORCID, Google, Microsoft,
        // and GitHub (in that order) as recent IdPs so they appear at top, or
        // CIL-2272 get the list of initial recent IdPs from the skin config.
        if (strlen($idps) == 0) {
            // Check skin for initial recent IdP list
            $skin = static::getSkin();
            $skinrecentidps = $skin->getConfigOption('initialrecentidps');
            if (!is_null($skinrecentidps)) {
                $idps = (string)$skinrecentidps;
            } else { // Default to ORCID, Google, Microsoft, and GitHub
                $idps = 'http://orcid.org/oauth/authorize,' .
                    'http://google.com/accounts/o8/id,' .
                    'http://login.microsoftonline.com/common/oauth2/v2.0/authorize,' .
                    'http://github.com/login/oauth/authorize';
            }
        }

        // Transform the cookie into an array
        $idparray = explode(',', $idps);
        // Make sure the user didn't mess with the cookie's IdPs
        filter_var_array($idparray, FILTER_SANITIZE_URL);

        if (strlen($entityID) > 0) {
            // If entityID is in the array, delete it
            $idparray = array_values(array_filter($idparray, fn ($m) => $m != $entityID));
            // Push the entityID onto the front of the array
            array_unshift($idparray, $entityID);
            // Keep only 10 IdPs
            while (count($idparray) > 10) {
                array_pop($idparray);
            }
            // Special check: If the resulting cookie string length is
            // more than 4000 bytes, chop off IdPs from the end until
            // the length is short enough
            $shortenough = false;
            while (!$shortenough) {
                $totallength = 0;
                foreach ($idparray as $value) {
                    $totallength += strlen($value) + 1;  // Add 1 for comma
                }
                if ($totallength > 4000) {
                    array_pop($idparray);
                } else {
                    $shortenough = true;
                }
            }
            // Transform the array back to a string and save the cookie
            static::setCookieVar('recentidps', implode(',', $idparray));
        }

        return $idparray;
    }

    /**
     * isOutputExtra
     *
     * Used for CIL-1643 to determine if we should output additional HTML
     * and JavaScript on certain pages, e.g., for the ACCESS navigation bar.
     *
     * This is a convenience function which checks the skin's <extrapage>
     * config option. If <extrapage> is set and the current page matches
     * one of the configured (space-separated) values, then return true.
     * This means that the calling function can check the <extrahtml> and
     * <extrascript> config options as well. If the current page does not
     * match one of the configured <extrapage> values, then there's no need
     * to check <extrahtml> and <extrascript> since we shouldn't output
     * anytyhing extra. Note that a blank/unconfigured <extrapage> option
     * means that the extra HTML and JavaScript should appear on ALL pages.
     *
     * @return bool True if current page matches a value in the skin's
     *         <extrapage> config option. False otherwise.
     */
    public static function isOutputExtra()
    {
        $retval = true; // True when <extrapage> is blank/not configured
        $skin = static::getSkin();
        $skinextrapage = $skin->getConfigOption('extrapage');
        if (!is_null($skinextrapage)) {
            $extrapages = explode(' ', ((string)$skinextrapage));
            $request_uri = Util::getServerVar('REQUEST_URI');
            // Remove leading slash
            $request_uri = ltrim($request_uri, '/');
            // Strip anything after a trailing /, ?, or #
            $request_uri = preg_replace('%[/?#].*$%', '', $request_uri);
            // If request_uri is empty, then we're on the main landing page
            if (strlen($request_uri) == 0) {
                $request_uri = 'main';
            }
            if (!in_array($request_uri, $extrapages)) {
                $retval = false;
            }
        }
        return $retval;
    }

    public static function setLang()
    {
        // Check if skin option <languages> has been configured. If not, then
        // there's no need to set a language since gettext calls such as
        // _() will simply output the text in the function call when text
        // domain is not set.
        $skin = static::getSkin();
        $languages = $skin->getConfigOption('languages');
        if ((is_null($languages)) || (empty($languages->lang))) {
            return; // No languages configured
        }

        // If we made it here, then we need to set a locale.
        // Check if the 'lang' cookie has been set. If so, verify
        // the cookie is one of the configured languages.
        $langcookie = static::getCookieVar('lang');
        $langcookieverified = false;
        if (strlen($langcookie) > 0) {
            foreach ($languages->lang as $availlang) {
                if ($langcookie == ((string)$availlang)) {
                    $langcookieverified = true;
                    break;
                }
            }
        }

        // If langcookie not set or not one of the configured languages,
        // set it to the default language, or the first configured language
        // if default language was not configured.
        if (!$langcookieverified) {
            $defaultlanguage = $skin->getConfigOption('defaultlanguage');
            if (is_null($defaultlanguage)) {
                $languages->rewind();
                $defaultlanguage = $languages->current();
            }
            $langcookie = (string)$defaultlanguage;
            static::setCookie('lang', $langcookie);
        }

        // Set the language domain - Need to install locales in Docker image
        if (setlocale(LC_ALL, $langcookie) !== false) {
            putenv('LC_ALL=' . $langcookie);
            bindtextdomain('cilogon', static::getServerVar('DOCUMENT_ROOT') . '/locale');
            bind_textdomain_codeset('cilogon', 'UTF-8');
            textdomain('cilogon');
        }

        // Maybe need to put all available languages in a cookie so that
        // JavaScript can populate a pop-up menu.
    }
}
