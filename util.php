<?php

/* NOTE: Look at the bottom of this file to see that it calls           *
 * startPHPSession().  Thus you simply need to require_once(util.php)   *
 * at the top of your PHP code to start a PHP session.  Also there is   *
 * a "define()" for HOSTNAME at the bottom of this file which allows    *
 * you to manually change the hostname used in various URLs.            */

/************************************************************************
 * Function  : getServerVar                                             *
 * Parameter : The $_SERVER variable to query.                          *
 * Returns   : The value of the $_SERVER variable or empty string if    *
 *             that variable is not set.                                *
 * This function queries a given $_SERVER variable (which is set by     *
 * the Apache server) and returns the value.                            *
 ************************************************************************/
function getServerVar($serv) {
    $retval = '';
    if (isset($_SERVER[$serv])) {
        $retval = $_SERVER[$serv];
    }
    return $retval;
}

/************************************************************************
 * Function  : getGetVar                                                *
 * Parameter : The $_GET variable to query.                             *
 * Returns   : The value of the $_GET variable or empty string if       *
 *             that variable is not set.                                *
 * This function queries a given $_GET parameter (which is set in the   *
 * URL via a "?parameter=value" parameter) and returns the value.       *
 ************************************************************************/
function getGetVar($get) 
{ 
    $retval = '';
    if (isset($_GET[$get])) {
        $retval = $_GET[$get];
    }
    return $retval;
}

/************************************************************************
 * Function  : getPostVar                                               *
 * Parameter : The $_POST variable to query.                            *
 * Returns   : The value of the $_POST variable or empty string if      *
 *             that variable is not set.                                *
 * This function queries a given $_POST variable (which is set when     *
 * the user submits a form, for example) and returns the value.         *
 ************************************************************************/
function getPostVar($post) 
{ 
    $retval = '';
    if (isset($_POST[$post])) {
        $retval = $_POST[$post];
    }
    return $retval;
}

/************************************************************************
 * Function  : getCookieVar                                             *
 * Parameter : The $_COOKIE variable to query.                          *
 * Returns   : The value of the $_COOKIE variable or empty string if    *
 *             that variable is not set.                                *
 * This function returns the value of a given cookie.                   *
 ************************************************************************/
function getCookieVar($cookie) 
{ 
    $retval = '';
    if (isset($_COOKIE[$cookie])) {
        $retval = $_COOKIE[$cookie];
    }
    return $retval;
}

/************************************************************************
 * Function  : getSessionVar                                            *
 * Parameter : The $_SESSION variable to query.                         *
 * Returns   : The value of the $_SESSION variable or empty string if   *
 *             that variable is not set.                                *
 * This function returns the value of a given PHP Session variable.     *
 ************************************************************************/
function getSessionVar($sess) 
{ 
    $retval = '';
    if (isset($_SESSION[$sess])) {
        $retval = $_SESSION[$sess];
    }
    return $retval;
}

/************************************************************************
 * Function  : unsetSessionVar                                          *
 * Parameter : The $_SESSION variable to erase.                         *
 * This function clears the given PHP session variable by first setting *
 * it to null and then unsetting it entirely.                           *
 ************************************************************************/
function unsetSessionVar($sess) 
{
    if (isset($_SESSION[$sess])) {
        $_SESSION[$sess] = null;
        unset($_SESSION[$sess]);
    }
}

/************************************************************************
 * Function   : setOrUnsetSessionVar                                    *
 * Parameters : (1) The name of the PHP session variable to set or      *
 *                  unset.                                              *
 *              (2) The value of the PHP session variable (to set),     *
 *                  or empty string (to unset).  Defaults to empty      *
 *                  string (implies unset the session variable).        *
 * Returns    : True if the PHP session variable was set, else false.   *
 * This function can set or unset a given PHP session variable.         *
 * The first parameter is the PHP session variable to set/unset.  If    *
 * the second parameter is the empty string, then the session variable  *
 * is unset.  Otherwise, the session variable is set to the second      *
 * parameter.  The function returns true if the session variable was    *
 * set, false otherwise.  Normally, the return value can be ignored.    *
 ************************************************************************/
function setOrUnsetSessionVar($key,$value='') 
{
    $retval = false;  // Assume we want to unset the session variable
    if (strlen($key) > 0) {  // Make sure session variable name was passed in
        if (strlen($value) > 0) {
            $_SESSION[$key] = $value;
            $retval = true;
        } else {
            unsetSessionVar($key);
        }
    }
    return $retval;
}

/************************************************************************
 * Function  : startPHPSession                                          *
 * This function starts a secure PHP session and should be called at    *
 * at the beginning of each script before any HTML is output.  It also  *
 * does a trick of setting a 'lastaccess' time so that the $_SESSION    *
 * variable does not expire without warning.                            *
 ************************************************************************/
function startPHPSession()
{
    ini_set('session.cookie_secure',true);
    if (session_id() == "") session_start();
    if ((!isset($_SESSION['lastaccess']) || 
        (time() - $_SESSION['lastaccess']) > 60)) {
        $_SESSION['lastaccess'] = time();
    }
}

/************************************************************************
 * Function  : getScriptDir                                             *
 * Parameters: (1) (Optional) Boolean to prepend "http(s)://" to the    *
 *                 script name.  Defaults to false.                     *
 *             (2) (Optional) Boolean to strip off the trailing         *
 *                 filename (e.g. index.php) from the path.  Defaults   *
 *                 to true (i.e. defaults to directory only without    *
 *                 the trailing filename).                              *
 * Return    : The directory or url of the current script, with or      *
 *             without the trailing .php filename.                      *
 * This function returns the directory (or full url) of the script that *
 * is currently running.  The returned directory/url is terminated by   *
 * a '/' character (unless the second parameter is set to true).  This  *
 * function is useful for those scripts named index.php were we don't   *
 * want to actually see "index.php" in the address bar (again, unless   *
 * the second parameter is set to true).                                *
 ************************************************************************/
function getScriptDir($prependhttp=false,$stripfile=true) {
    $retval = getServerVar('SCRIPT_NAME');
    if ($stripfile) {
        $retval = dirname($retval);
    }
    if ($retval == '.') {
        $retval = '';
    }
    if ((strlen($retval) == 0) || 
        ($stripfile && ($retval[strlen($retval)-1] != '/'))) {
        $retval .= '/';  // Append a slash if necessary
    }
    if ($prependhttp) {  // Prepend http(s)://hostname
        $retval = 'http' . 
                  ((strtolower(getServerVar('HTTPS')) == 'on') ? 's' : '') .
                  '://' . getServerVar('HTTP_HOST') . $retval;
    }
    return $retval;
}

/************************************************************************
 * Function  : readArrayFromFile                                        *
 * Parameter : The name of the file to read.                            *
 * Return    : An array containing the contents of the file.            *
 * This function reads in the contents of a file into an array. It is   *
 * assumed that the file contains lines of the form:                    *
 *     key value                                                        *
 * where "key" and "value" are separated by whitespace.  The "key"      *
 * portion of the string may not contain any whitespace, but the        *
 * "value" part of the line may contain whitespace. Any empty lines     *
 * in the file are skipped.  Note that this assumes that each "key"     *
 * in the file is unique.  If there is any problem reading the file,    *
 * the resulting array will be empty.                                   *
 ************************************************************************/
function readArrayFromFile($filename) {
    $retarray = array();
    if (is_readable($filename)) {
        $lines = file($filename,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line_num => $line) {
            $values = preg_split('/\s+/',$line,2);
            $retarray[$values[0]] = $values[1];
        }
    }

    return $retarray;
}

/************************************************************************
 * Function  : writeArrayToFile                                         *
 * Parameters: (1) The name of the file to write.                       *
 *             (2) The array to be written to the file.                 *
 * Return    : True if successfully wrote file, false otherwise.        *
 * This funtion writes an array (with key=>value pairs) to a file, each *
 * line will be of the form:                                            *
 *     key value                                                        *
 * The "key" and "value" strings are separated by a space. Note that    *
 * a "key" may not contain any whitespace (e.g. tabs), but a "value"    *
 * may contain whitespace.                                              *
 ************************************************************************/
function writeArrayToFile($filename,$thearray) {
    $retval = false;  // Assume write failed
    if ($fh = fopen($filename,'w')) {
        foreach ($thearray as $key => $value) {
            fwrite($fh,"$key $value\n");
        }
        fclose($fh);
        $retval = true;
    }

    return $retval;
}

/************************************************************************
 * Function  : parseGridShibConf                                        *
 * Parameter : (Optional) Full path location of gridshib-ca.conf file.  *
 *             Defaults to                                              *
 *             '/usr/local/gridshib-ca/conf/gridshib-ca.conf'.          *
 * Return    : An array containing the various configuration parameters *
 *             in the gridshib-ca.conf file.                            *
 * This function parses the gridshib-ca.conf file and returns an array  *
 * containing the various options.  It uses the PHP PEAR::Config        *
 * package to parse the config file.  The gridshib-ca.conf file is      *
 * MOSTLY an Apache-style config file.  However, each option has an     *
 * extra ' = ' prepended, so you will need to strip these off each      *
 * config option.  For example, to get the 'MaximumCredLifetime' value  *
 * which is in the 'CA' section, you would do the following:            *
 *     $gridshibconf = parseGridShibConf();                             */
//     $life = preg_replace('/^\s*=\s*/','',                            *
//             $gridshibconf['root']['CA']['MaximumCredLifetime']);     *
/************************************************************************/
function parseGridShibConf($conffile=
    '/usr/local/gridshib-ca/conf/gridshib-ca.conf') {
    require_once('Config.php');
    $conf = new Config;
    $root =& $conf->parseConfig($conffile,'Apache');
    $gridshibconf = array();
    if (!(PEAR::isError($root))) {
        $gridshibconf = $root->toArray();
    }
    return $gridshibconf;
}

/************************************************************************
 * Function  : tempDir                                                  *
 * Parameters: (1) The full path to the containing directory.           *
 *             (2) (Optional) A prefix for the new temporary directory. *
 *                 Defaults to nothing.                                 *
 *             (3) (Optional) Access permissions for the new temporary  *
 *                 directory. Defaults to 0755.                         *
 * Return    : Full path to the newly created temporary directory.      *
 * This function creates a temporary subdirectory within the specified  * 
 * subdirectory.  The new directory name is composed of 16 hexadecimal  *
 * letters, plus any prefix if you specify one.  The full path of the   *
 * the newly created directory is returned.                             *
/************************************************************************/
function tempDir($dir,$prefix='',$mode=0775) {
    if (substr($dir,-1) != '/') {
        $dir .= '/';
    }

    $path = '';
    do {
        $path = $dir . $prefix . sprintf("%08X%08X",mt_rand(),mt_rand());
    } while (!mkdir($path,$mode,true));

    return $path;
}

/************************************************************************
 * Function  : deleteDir                                                *
 * Parameters: The (possibly non-empty) directory to delete.            *
 * This function deletes a directory and all of its contents.           *
/************************************************************************/
function deleteDir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir") {
                    deleteDir($dir."/".$object);
                } else {
                    @unlink($dir."/".$object);
                }
            }
        }
        reset($objects);
        @rmdir($dir);
    }
}


/* Start a secure PHP session */
startPHPSession();

/* If HTTP_HOST is set, use that as the hostname.  Otherwise, set manually. */
$thehostname = getServerVar('HTTP_HOST');
define('HOSTNAME',((strlen($thehostname) > 0) ? 
    $thehostname : 'cilogon.org'));

// require_once('timeit.php');
// $timeit = new timeit(timeit::defaultFilename,true);
?>
