<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use Log;

/**
 * Loggit
 */
class Loggit
{
    /**
     * @var Log $logger The Log object to write log info with.
     */
    protected $logger;

    /**
     * __construct
     *
     * Default constructor. This functions creates a new internal
     * $logger object to be utilized to write out log messages to the
     * intended destination (the first parameter) using the PHP Pear Log
     * module. You can log to the console (i.e., STDOUT), the system
     * syslog, or a file. The default is DEFAULT_LOGTYPE (defined in the
     * top-level config.php file) If logtype is 'file', the second
     * parameter is the file name. If running in a browser session,
     * the SERVER_NAME and REQUEST_URI are includedin all log events.
     *
     * Example usage:
     *     // Log info message to syslog
     *     $sysloggit = new Loggit('syslog');
     *     $sysloggit->info('This is an info message.');
     *
     * @param string $logtype (Optional) The log type, can be one of
     *        'console', 'syslog', or 'file'.
     * @param string $name (Optional) The name of the log file.
     */
    public function __construct($logtype = DEFAULT_LOGTYPE, $logname = '')
    {
        $ident = Util::getServerVar('SERVER_NAME') .
                 Util::getServerVar('REQUEST_URI');

        if (($logtype == 'syslog') && (strlen($logname) == 0)) {
            $logname = 'LOG_SYSLOG';
        }
        $this->logger = Log::singleton($logtype, $logname, $ident);
    }

    /**
     * info
     *
     * This function writes a message to a "log" using the PHP Pear Log
     * module. Several server variables and cookies (if they are set)
     * are automatically appended to the message to be logged.  These
     * are found in the $envs and $cookies array in the code below.
     * Also, all PHP session variables are logged.
     *
     * @param string $message The message string to be logged.
     * @param bool $missing (Optional) If true, print some missing user
     *        session variables. Defaults to false.
     * @param int $level (Optional) The PHP Pear-Log level for the message.
     *        Defaults to PEAR_LOG_INFO.
     */
    public function info($message, $missing = false, $level = PEAR_LOG_INFO)
    {
        // Don't log messages from monit/nagios hosts, the
        // same ones configured in /etc/httpd/conf.httpd.conf
        $dontlog = array('141.142.148.8',   // nagios-sec
                         '141.142.148.108', // nagios2-sec
                         '141.142.234.38',  // falco
                         '192.249.7.62'     // fozzie
                        );
        if (
            (isset($_SERVER['REMOTE_ADDR'])) &&
            (in_array($_SERVER['REMOTE_ADDR'], $dontlog))
        ) {
            return;
        }

        // Always print out certain HTTP headers, if available
        $envs    = array('REMOTE_ADDR',
                         'REMOTE_USER',
                         'HTTP_SHIB_IDENTITY_PROVIDER',
                         'HTTP_SHIB_SESSION_ID'
                        );
        // Always print out certain cookies, if available
        $cookies = array('providerId',
                        );

        $envstr = ' ';
        foreach ($envs as $value) {
            if ((isset($_SERVER[$value])) && (strlen($_SERVER[$value]) > 0)) {
                $envstr .= $value . '="' . $_SERVER[$value] . '" ';
            }
        }

        foreach ($cookies as $value) {
            if ((isset($_COOKIE[$value])) && (strlen($_COOKIE[$value]) > 0)) {
                $envstr .= $value . '="' . $_COOKIE[$value] . '" ';
            }
        }

        /* NEED TO CHANGE THIS WHEN USING HTTP_Session2 */
        if (session_id() != '') {
            foreach ($_SESSION as $key => $value) {
                $envstr .= $key . '="' .
                    (is_array($value) ? 'Array' : $value) . '" ';
            }
        }

        if ($missing) { // Output any important missing user session vars
            foreach (DBService::$user_attrs as $value) {
                if (!isset($_SESSION[$value])) {
                    $envstr .= $value . '="MISSING" ';
                }
            }
        }

        $this->logger->log($message . ' ' . $envstr, $level);
    }

    /**
     * warn
     *
     * This function writes a warning message message to the log.
     *
     */
    public function warn($message, $missing = false)
    {
        $this->info($message, $missing, PEAR_LOG_WARNING);
    }

    /**
     * Function  : error
     *
     * This function writes an error message message to the log.
     *
     * @param string $message The message string to be logged.
     * @param bool $missing (Optional) If true, print some missing user
     *        session variables. Defaults to false.
     */
    public function error($message, $missing = false)
    {
        $this->info($message, $missing, PEAR_LOG_ERR);
    }

    /**
     * alert
     *
     * This function writes an alert message message to the log.
     *
     * @param string $message The message string to be logged.
     * @param bool $missing (Optional) If true, print some missing user
     *        session variables. Defaults to false.
     */
    public function alert($message, $missing = false)
    {
        $this->info($message, $missing, PEAR_LOG_ALERT);
    }
}
