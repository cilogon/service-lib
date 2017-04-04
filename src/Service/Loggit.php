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
     * module.  End-user documentation can be found at:
     *     http://www.indelible.org/php/Log/guide.html
     * You can log either to the system syslog, or send a 'log' message
     * to an email address.  Default is 'syslog' if you don't specify
     * the first parameter.  If first parameter is 'mail', the default
     * for the second parameter is help@cilogon.org.  If running in a
     * browser session, the SERVER_NAME and REQUEST_URI are included
     * in all log events.
     *
     * Example usage:
     *     // Log info message to syslog
     *     $sysloggit = new Loggit();
     *     $sysloggit->info('This is an info message.');
     *     // Send alert to email address
     *     $mailloggit = new Loggit('mail','help@google.com');
     *     $mailloggit->alert('There is a problem!');
     *
     * @param string $logtype (Optional) The log type, can be 'syslog' or
     *        'mail'. Defaults to 'syslog'.
     * @param string $email (Optional) The destination email address if
     *        $logtype is 'mail'. Defaults to 'help@cilogon.org'.
     * @return Loggit A new Loggit object
     */
    public function __construct($logtype = 'syslog', $email = 'help@cilogon.org')
    {
        $ident = Util::getServerVar('SERVER_NAME') .
                 Util::getServerVar('REQUEST_URI');

        $this->logger = Log::singleton(
            $logtype,
            ($logtype == 'syslog' ? 'LOG_SYSLOG' : $email),
            $ident
        );
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
        if ((isset($_SERVER['REMOTE_ADDR'])) &&
            (in_array($_SERVER['REMOTE_ADDR'], $dontlog))) {
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
                         'CSRFProtection'
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
            $uservars = array('ePPN','ePTID','openidID','oidcID','firstname',
                'lastname','displayname','emailaddr','affiliation');
            foreach ($uservars as $uv) {
                if (!isset($_SESSION[$uv])) {
                    $envstr .= $uv . '="MISSING" ';
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