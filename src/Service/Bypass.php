<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use PEAR;
use DB;

/**
 * Bypass
 *
 * This class handles three configuration arrays which can be stored in the
 * top-level config.php file or in the ciloa2.bypass database table.
 *
 * ALLOW_BYPASS_ARRAY / ciloa2.bypass table, 'type' = 'allow'
 * BYPASS_IDP_ARRAY   / ciloa2.bypass table, 'type' = 'idp'
 * FORCE_SKIN_ARRAY   / ciloa2.bypass table, 'type' = 'skin'
 *
 * When an *_ARRAY is defined in config.php, the corresponding database
 * table 'type' is ignored, i.e., file configuration and database
 * configuration are not merged.
 *
 * To create the ciloa2.bypass table:
 *
 * CREATE TABLE ciloa2.bypass (
 *     type ENUM('allow', 'idp', 'skin') NOT NULL DEFAULT 'allow',
 *     regex VARCHAR(255) NOT NULL DEFAULT '%%',
 *     value VARCHAR(255) DEFAULT NULL,
 *     PRIMARY KEY(type,regex)
 * );
 * GRANT ALL PRIVILEGES ON `ciloa2`.`bypass` TO 'cilogon'@'localhost';
 *
 * 'type' is one of 'allow', 'idp', or 'skin'.
 *
 * 'regex' is a Perl Compatible Regular Expression (PCRE) (see
 * https://www.php.net/manual/en/pcre.pattern.php for details). It should
 * match a client_id or a redirect_uri. '%' (percent) is a good choice for
 * delimiter so that slashes do not need to be escaped. Note that period '.'
 * matches any character, so if you want to match a dot, prefix with a
 * backslash, e.g., '\.' . However, in practice this unnecessary since dots
 * appear mainly in the FQDN.
 *
 * 'value' depends on the 'type':
 * For 'type'='allow', 'value' is NULL (or empty string).
 * For 'type'='idp',   'value' is an IdP entityId (from cilogon.org/idplist/).
 * For 'type'='skin',  'value' is the name of a skin (from the 'skins' table).
 *
 * There are three class methods which return each of the three
 * configuration arrays. These three methods first check if the *_ARRAY is
 * defined in the top-level config.php. If not, the corresponding database
 * array is returned. In other words, code should not reference any
 * of the *_ARRAY constants. Instead, call getAllowBypassArray(),
 * getBypassIdPArray(), and getForceSkinArray() to get the appropriate
 * array values.
 */
class Bypass
{
    /**
     * @var array $bypassarray Array containing the 'bypass' database table.
     */
    protected $bypassarray = [];

    /**
     *  __construct
     *
     * Default constructor. Calls init() to do the actual work.
     *
     * @return Bypass A new Bypass object.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * init
     *
     * This function is called by the constructor, and can also be called to
     * force a re-read of the database. It attempts to read the bypass table
     * which has three columns: type, regex, value. Type is one of 'allow',
     * 'idp', or 'skin'. Regex is a Perl Compatible Regular Expression
     * (https://www.php.net/manual/en/pcre.pattern.php) which matches either
     * a client_id or a redirect_uri. Value is one of (1) NULL (for 'allow'
     * since we just need to know that the regex client_id/redirect_uri
     * honors the 'idphint' parameter to auto-select that IdP), (2) entityId
     * (for 'idp' to auto-redirect to that IdP), or (3) skin name (for a
     * skin that should be applied automatically). After the bypass table is
     * read in, it is reformatted as a multi-dimensional array:
     *     [type][regex] = value
     * The result is stored in the class $bypassarray variable.
     *
     * @return bool True if the database was read in and not empty.
     *              False if there was a database read error, or if the
     *              bypass database table was empty.
     */
    public function init()
    {
        $readin = false; // Did we read the 'bypass' table from the database?
        $this->bypassarray = []; // Reset the class bypassarray to empty

        $db = Util::getDB(true);
        if (!is_null($db)) {
            $data = $db->getAssoc(
                'SELECT * FROM bypass',
                true,
                array(),
                DB_FETCHMODE_ASSOC,
                true
            );
            if ((!DB::isError($data)) && (!empty($data))) {
                $readin = true;
                // Convert indexed arrays to associative arrays
                foreach ($data as $key => $valarr) {
                    foreach ($valarr as $val) {
                        $this->bypassarray[$key][$val['regex']] = $val['value'];
                    }
                }
            }
            $db->disconnect();
        }

        return $readin;
    }

    /**
     * getAllowBypassArray
     *
     * This function returns an array of client_ids or redirect_uris which
     * are allowed to bypass the 'Select an Identity Provider' page by
     * passing the 'idphint'/'selected_idp' query parameter. It first checks
     * for an ALLOW_BYPASS_ARRAY defined in the top-level config.php file.
     * If not defined, then entries from the 'bypass' database table (where
     * 'type='allow') is returned instead. If neither are defined, then an
     * empty array is returned.
     *
     * @return array An array where keys are PCREs matching either
     *         client_ids or redirect_uris, and values are null or empty
     *         string.
     */
    public function getAllowBypassArray()
    {
        $retarr = array();

        if (defined('ALLOW_BYPASS_ARRAY')) {
            $retarr = ALLOW_BYPASS_ARRAY;
        } elseif (
            (!empty($this->bypassarray)) &&
            (array_key_exists('allow', $this->bypassarray))
        ) {
            $retarr = $this->bypassarray['allow'];
        }

        return $retarr;
    }

    /**
     * getBypassIdPArray
     *
     * This function returns an array of client_ids or redirect_uris which
     * are allowed to bypass the 'Select an Identity Provider' page by
     * redirecting to a specific IdP entityId. It first checks for a
     * BYPASS_IDP_ARRAY defined in the top-level config.php file.
     * If not defined, then entries from the 'bypass' database table (where
     * 'type='idp') is returned instead. If neither are defined, then an
     * empty array is returned.
     *
     * @return array An array where keys are PCREs matching either
     *         client_ids or redirect_uris, and values are IdP entityIds.
     */
    public function getBypassIdPArray()
    {
        $retarr = array();

        if (defined('BYPASS_IDP_ARRAY')) {
            $retarr = BYPASS_IDP_ARRAY;
        } elseif (
            (!empty($this->bypassarray)) &&
            (array_key_exists('idp', $this->bypassarray))
        ) {
            $retarr = $this->bypassarray['idp'];
        }

        return $retarr;
    }

    /**
     * getForceSkinArray
     *
     * This function returns an array of client_ids or redirect_uris which
     * are forced to use a specific skin. It first checks for a
     * FORCE_SKIN_ARRAY defined in the top-level config.php file.
     * If not defined, then entries from the 'bypass' database table (where
     * 'type='skin') is returned instead. If neither are defined, then an
     * empty array is returned.
     *
     * @return array An array where keys are PCREs matching either
     *         client_ids or redirect_uris, and values are skin names.
     */
    public function getForceSkinArray()
    {
        $retarr = array();

        if (defined('FORCE_SKIN_ARRAY')) {
            $retarr = FORCE_SKIN_ARRAY;
        } elseif (
            (!empty($this->bypassarray)) &&
            (array_key_exists('skin', $this->bypassarray))
        ) {
            $retarr = $this->bypassarray['skin'];
        }

        return $retarr;
    }

    /**
     * getSSOAdminArray
     *
     * This function returns an array of admin_ids which should
     * allow Single Sign On (SSO). If the current session IdP matches
     * the previously used IdP, then bypass the 'Select an
     * Identity Provider' page and use the current session IdP.
     * This function first checks for a SSO_ADMIN_ARRAY defined in
     * the top-level config.php file. If not defined, then entries
     * from the 'bypass' database table (where type='sso') is returned
     * instead. If neither are defined, then an empty array is returned.
     *
     * @return array An array where keys are admin_ids, and values are
     *         CO (VO) names (e.g., 'ACCESS').
     */
    public function getSSOAdminArray()
    {
        $retarr = array();

        if (defined('SSO_ADMIN_ARRAY')) {
            $retarr = SSO_ADMIN_ARRAY;
        } elseif (
            (!empty($this->bypassarray)) &&
            (array_key_exists('sso', $this->bypassarray))
        ) {
            $retarr = $this->bypassarray['sso'];
        }

        return $retarr;
    }
}
