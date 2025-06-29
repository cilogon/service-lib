<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use CILogon\Service\Content;

/**
 * PortalCookie
 *
 * This class is used by the 'CILogon Delegate Service'
 * and the CILogon OIDC 'authorize' endpoint to keep track of
 * user-selected attributes such as if the user clicked the 'Always Allow'
 * button to remember the allowed delegation upon future accesses.
 * The information related to 'remember' the settings is stored in a single
 * cookie. Since the data is actually a two dimensional array (first element
 * is the name of the portal, the second element is an array of the various
 * attributes), the stored cookie is actually a base64-encoded
 * serialization of the 2D array. This class provides methods to
 * read/write the cookie, and to get/set the values for a given portal.
 *
 * Example usage:
 *    require_once 'PortalCookie.php';
 *    $pc = new PortalCookie();  // Automatically reads the cookie
 *    // Assume the callbackuri or redirect_uri for the portal has
 *    // been set in the PHP session.
 *    $lifetime = $pc->get('lifetime');
 *    if ($lifetime < 1) {
 *        $lifetime = 1;
 *    } elseif ($lifetime > 240) {
 *        $lifetime = 240;
 *    }
 *    $pc->set('remember',1);
 *    $pc->write();  // Must be done before any HTML output
 */
class PortalCookie
{
    /**
     * @var string COOKIENAME The token name is const to be accessible from
     *      removeTheCookie.
     */
    public const COOKIENAME = "portalparams";

    /**
     * @var array $portalarray An array of arrays. First index is portal name.
     */
    public $portalarray = array();

    /**
     * __construct
     *
     * Default constructor.  This reads the current portal cookie into
     * the class $portalarray arary.
     */
    public function __construct()
    {
        $this->read();
    }

    /**
     * read
     *
     * This method reads the portal cookie, decodes the base64 string,
     * decrypts the AES-128-CBC string, and unserializes the 2D array.
     * This is stored in the class $portalarray array.
     */
    public function read()
    {
        if (isset($_COOKIE[static::COOKIENAME])) {
            $cookievar = $_COOKIE[static::COOKIENAME];
            $serial = $cookievar;

            // Attempt to un-base64 and decrypt portal array from cookie
            if (defined('OPENSSL_KEY') && (!empty(OPENSSL_KEY))) {
                $b64 = base64_decode($cookievar);
                if ($b64 !== false) {
                    $iv = substr($b64, 0, 16); // IV prepended to encrypted data
                    $b64a = substr($b64, 16);  // IV is 16 bytes, rest is data
                    if ((strlen($iv) > 0) && (strlen($b64a) > 0)) {
                        $serial = openssl_decrypt(
                            $b64a,
                            'AES-128-CBC',
                            OPENSSL_KEY,
                            OPENSSL_RAW_DATA,
                            $iv
                        );
                    }
                }
            }

            // Unserialize the cookie data back into the portalarray
            if (strlen($serial) > 0) {
                $unserial = unserialize($serial);
                if ($unserial !== false) {
                    $this->portalarray = $unserial;
                }
            }
        }
    }

    /**
     * write
     *
     * This method writes the class $portalarray to a cookie.  In
     * order to store the 2D array as a cookie, the array is first
     * serialized, then encrypted with AES-128-CBC, and then base64-
     * encoded.
     */
    public function write()
    {
        if (!empty($this->portalarray)) {
            $this->set('ut', time()); // Save update time
            $serial = serialize($this->portalarray);
            // Special check: If the serialization of the cookie is
            // more than 2500 bytes, the resulting base64-encoded string
            // may be too big (>4K). So scan through all portal entries
            // and delete the oldest one until the size is small enough.
            while (strlen($serial) > 2500) {
                $smallvalue = 5000000000; // Unix time = Jun 11, 2128
                $smallportal = '';
                foreach ($this->portalarray as $k => $v) {
                    if (isset($v['ut'])) {
                        if ($v['ut'] < $smallvalue) {
                            $smallvalue = $v['ut'];
                            $smallportal = $k;
                        }
                    } else { // 'ut' not set, delete it
                        $smallportal = $k;
                        break;
                    }
                }
                if (strlen($smallportal) > 0) {
                    unset($this->portalarray[$smallportal]);
                } else {
                    break; // Should never get here, but just in case
                }
                $serial = serialize($this->portalarray);
            }
            $cookievar = $serial;

            // Attempt to encrypt and base64 the serialized portal array
            if (defined('OPENSSL_KEY') && (!empty(OPENSSL_KEY))) {
                $iv = openssl_random_pseudo_bytes(16);  // IV is 16 bytes
                if (strlen($iv) > 0) {
                    $data = openssl_encrypt(
                        $cookievar,
                        'AES-128-CBC',
                        OPENSSL_KEY,
                        OPENSSL_RAW_DATA,
                        $iv
                    );
                    if (strlen($data) > 0) {
                        $b64 = base64_encode($iv . $data); // Prepend IV to data
                        if ($b64 !== false) {
                            $cookievar = $b64;
                        }
                    }
                }
            }
            Util::setCookieVar(static::COOKIENAME, $cookievar);
        }
    }

    /**
     * getPortalName
     *
     * This method looks in the PHP session for one of 'callbackuri'
     * (in the OAuth 1.0a 'delegate' case) or various $clientparams
     * (in the OIDC 'authorize' case).This is used as the key for the
     * $portalarray. If neither of these session variables is set,
     * return empty string.
     *
     * @return string The name of the portal, which is either the
     *         OAuth 1.0a 'callbackuri' or the OIDC client info.
     */
    public function getPortalName()
    {
        // Check the OAuth 1.0a 'delegate' 'callbackuri'
        $retval = Util::getSessionVar('callbackuri');
        if (strlen($retval) == 0) {
            // Next, check the OAuth 2.0 'authorize' $clientparams[]
            $clientparams = json_decode(
                Util::getSessionVar('clientparams'),
                true
            );
            if (
                (isset($clientparams['client_id'])) &&
                (isset($clientparams['scope']))
            ) {
                // Use the first element of the idphint list as the selected_idp.
                $selected_idp = '';
                $idphintlist = Content::getIdphintList();
                if (!empty($idphintlist)) {
                    $selected_idp = $idphintlist[0];
                }
                $retval = $clientparams['client_id'] . ';' .
                    // redirect_uri is set for 'authorize' but not for
                    // 'device', so make it optional so the portal cookie
                    // can be set in both flows.
                    ((isset($clientparams['redirect_uri'])) ? $clientparams['redirect_uri'] . ';' : '') .
                    $clientparams['scope'] .
                    (empty($selected_idp) ? '' : ';' . $selected_idp);
            }
        }
        return $retval;
    }

    /**
     * removeTheCookie
     *
     * This method unsets the portal cookie in the user's browser.
     * This should be called before any HTML is output.
     */
    public static function removeTheCookie()
    {
        Util::unsetCookieVar(static::COOKIENAME);
    }

    /**
     * get
     *
     * This method is a generalized getter to fetch the value of a
     * parameter for a given portal.  In other words, this method
     * returns $this->portalarray[$param], where $param is something
     * like 'lifetime' or 'remember'. If the portal name is not set,
     * or the requested parameter is missing from the cookie, return
     * empty string.
     *
     * @param string $param The attribute of the portal to get.  Should be
     *        something like 'lifetime' or 'remember'.
     * @return string The value of the $param for the portal.
     */
    public function get($param)
    {
        $retval = '';
        $name = $this->getPortalName();
        if (strlen($name) > 0) {
            if (
                (isset($this->portalarray[$name])) &&
                (isset($this->portalarray[$name][$param]))
            ) {
                $retval = $this->portalarray[$name][$param];
            } elseif ($param == 'providerId') {
                // CIL-719 If there is no portal cookie set for this
                // particular 'portal name', then attempt to read the
                // 'providerId' value from the most recent portal cookie.
                $pa = $this->portalarray; // Make a copy of the portalarary
                // Ascending sort the array by 'ut'
                uasort($pa, function ($a, $b) {
                    return ($a['ut'] < $b['ut']) ? -1 : (($a['ut'] > $b['ut']) ? 1 : 0);
                });
                // Get the last (most recent) element of the array
                $name = @array_key_last($pa);
                if (
                    (strlen($name) > 0) &&
                    (isset($pa[$name])) &&
                    (isset($pa[$name][$param]))
                ) {
                    $retval = $pa[$name][$param];
                }
            }
        }
        return $retval;
    }

    /**
     * set
     *
     * This method sets a portal's parameter to a given value.  Note
     * that $value should be an integer or character value.
     *
     * @param string $param The parameter of the portal to set.  Should be
     *        something like 'lifetime' or 'remember'.
     * @param string $value The value to set for the parameter.
     */
    public function set($param, $value)
    {
        $name = $this->getPortalName();
        if (strlen($name) > 0) {
            $this->portalarray[$name][$param] = $value;
        }
    }

    /**
     * __toString
     *
     * This function returns a string representation of the object.
     * The format is 'portal=...,lifetime=...,remember=...'. Multiple
     * portals are separated by a newline character.
     *
     * @return string A 'pretty print' representation of the class
     *         portalarray.
     */
    public function __toString()
    {
        $retval = '';
        $first = true;
        foreach ($this->portalarray as $key => $value) {
            if (!$first) {
                $retval .= "\n";
            }
            $first = false;
            $retval .= 'portal=' . $key;
            ksort($value);
            foreach ($value as $key2 => $value2) {
                $retval .= ", $key2=$value2";
            }
        }
        return $retval;
    }
}
