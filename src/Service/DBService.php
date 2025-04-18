<?php

namespace CILogon\Service;

use CILogon\Service\Util;

/**
 * DBService
 *
 * This class is a wrapper for the dbService servlet.  The dbService
 * servlet acts as a frontend to the database that stores info on users,
 * portal parameters, and IdPs. This was created to allow for fast
 * access to the database by keeping a connection open.  This class is a
 * rework of the old store.php class.
 *
 * Example usage:
 *     // For authentication, we have a bunch of attributes from an
 *     // identity provider. Thus get the database uid for the user
 *     // by using the multi-parameter version of getUser().
 *     $uid = '';
 *     $dbservice = new DBService();
 *     $dbservice->getUser('jsmith@illinois.edu',
 *                         'urn:mace:incommon:uiuc.edu',
 *                         'University of Illinois at Urbana-Champaign',
 *                         'John','Smith','John Smith,
 *                          'jsmith@illinois.edu');
 *     if (!($dbservice->status & 1)) { // OK status codes are even
 *         $uid = $dbservice->user_uid;
 *     }
 *
 *     // Later in the code, re-fetch the user using this uid
 *     // and print out the stored attributes.
 *     if (strlen($uid) > 0) {
 *         $dbservice->getUser($uid);
 *         echo 'Name = ' . $dbservice->first_name . ' ' .
 *                          $dbservice->last_name  . "\n";
 *         echo 'DN = '   . $dbservice->distinguished_name . "\n";
 *     }
 *
 *     // For getting/setting the Shibboleth-based IdPs, use the
 *     // getIdps()/setIdps() methods.  These methods utilize the
 *     // class member array $idp_uids for reading/writing. Two
 *     // convenience methods (setIdpsFromKeys($array) and
 *     // setIdpsFromValues($array)) are provided to populate the
 *     // $idp_uids array from the passed-in $array.
 *     $dbservice->getIdps();
 *     foreach($dbservice->idp_uids as $value) {
 *         echo "$value\n";
 *     }
 *
 *     $idps = array('urn:mace:incommon:ucsd.edu',
 *                   'urn:mace:incommon:uiuc.edu');
 *     $dbservice->setIdpsFromValues($idps);
 *     //   --- OR ---
 *     $idps = array('urn:mace:incommon:ucsd.edu' => 1,
 *                   'urn:mace:incommon:uiuc.edu' => 1);
 *     $dbservice->setIdpsFromKeys($idps);
 */

class DBService
{
    /**
     * @var array $STATUS The various STATUS_* constants, originally from
     *      Store.pm. See cilogon2-server-loader-oauth2/src/main/java/org/cilogon/oauth2/servlet/impl/DBService2.java
     *      in the https://github.com/cilogon/cilogon-java/ repo for the
     *      definitive list of oauth2 return status codes.
     *      The keys of the array are strings corresponding to the
     *      constant names. The values of the array are the integer (hex)
     *      values. For example, DBService::$STATUS['STATUS_OK'] = 0;
     *      Use 'array_search($this->status,DBService::$STATUS)' to look
     *      up the STATUS_* name given the status integer value.
     */
    public static $STATUS = array(
        'STATUS_OK'                        => 0x0,
        'STATUS_ACTION_NOT_FOUND'          => 0x1,
        'STATUS_NEW_USER'                  => 0x2,
        'STATUS_USER_UPDATED'              => 0x4,
        'STATUS_USER_NOT_FOUND'            => 0x6,
        'STATUS_USER_EXISTS'               => 0x8,
        'STATUS_IDP_UPDATED'               => 0xA,     //      10
        'STATUS_USER_EXISTS_ERROR'         => 0xFFFA1, // 1048481
        'STATUS_USER_NOT_FOUND_ERROR'      => 0xFFFA3, // 1048483
        'STATUS_TRANSACTION_NOT_FOUND'     => 0xFFFA5, // 1048485
        'STATUS_IDP_SAVE_FAILED'           => 0xFFFA7, // 1048487
        'STATUS_DUPLICATE_PARAMETER_FOUND' => 0xFFFF1, // 1048561
        'STATUS_INTERNAL_ERROR'            => 0xFFFF3, // 1048563
        'STATUS_SAVE_IDP_FAILED'           => 0xFFFF5, // 1048565
        'STATUS_MALFORMED_INPUT_ERROR'     => 0xFFFF7, // 1048567
        'STATUS_MISSING_PARAMETER_ERROR'   => 0xFFFF9, // 1048569
        'STATUS_NO_REMOTE_USER'            => 0xFFFFB, // 1048571
        'STATUS_NO_IDENTITY_PROVIDER'      => 0xFFFFD, // 1048573
        'STATUS_CLIENT_NOT_FOUND'          => 0xFFFFF, // 1048575
        'STATUS_EPTID_MISMATCH'            => 0x100001,// 1048577
        'STATUS_PAIRWISE_ID_MISMATCH'      => 0x100003,// 1048579
        'STATUS_SUBJECT_ID_MISMATCH'       => 0x100005,// 1048581
        'STATUS_QDL_ERROR'                 => 0x100007,// 1048583
        'STATUS_QDL_RUNTIME_ERROR'         => 0x100009,// 1048585
        'STATUS_TRANSACTION_NOT_FOUND'     => 0x10001, //   65537
        'STATUS_EXPIRED_TOKEN'             => 0x10003, //   65539
        'STATUS_CREATE_TRANSACTION_FAILED' => 0x10005, //   65541
        'STATUS_UNKNOWN_CALLBACK'          => 0x10007, //   65543
        'STATUS_MISSING_CLIENT_ID'         => 0x10009, //   65545
        'STATUS_NO_REGISTERED_CALLBACKS'   => 0x1000B, //   65547
        'STATUS_UNKNOWN_CLIENT'            => 0x1000D, //   65549
        'STATUS_UNAPPROVED_CLIENT'         => 0x1000F, //   65551
        'STATUS_NO_SCOPES'                 => 0x10011, //   65553
        'STATUS_MALFORMED_SCOPE'           => 0x10013, //   65555
    );

    /**
     * @var array $STATUS_TEXT The human-readable error messages
     *      corresponding to the STATUS_* constants.
     * @see statusText()
     */
    public static $STATUS_TEXT = array(
        'STATUS_OK'                        => 'Status OK.',
        'STATUS_ACTION_NOT_FOUND'          => 'Action not found.',
        'STATUS_NEW_USER'                  => 'New user created.',
        'STATUS_USER_UPDATED'              => 'User data updated.',
        'STATUS_USER_NOT_FOUND'            => 'User not found.',
        'STATUS_USER_EXISTS'               => 'User exists.',
        'STATUS_USER_EXISTS_ERROR'         => 'User already exists.',
        'STATUS_IDP_UPDATED'               => 'User IdP entityID updated.',
        'STATUS_USER_NOT_FOUND_ERROR'      => 'User not found.',
        'STATUS_TRANSACTION_NOT_FOUND'     => 'Transaction not found.',
        'STATUS_IDP_SAVE_FAILED'           => 'Could not save IdPs.',
        'STATUS_DUPLICATE_PARAMETER_FOUND' => 'Duplicate parameter.',
        'STATUS_INTERNAL_ERROR'            => 'Internal error.',
        'STATUS_SAVE_IDP_FAILED'           => 'Could not save IdP.',
        'STATUS_MALFORMED_INPUT_ERROR'     => 'Malformed input.',
        'STATUS_MISSING_PARAMETER_ERROR'   => 'Missing parameter.',
        'STATUS_NO_REMOTE_USER'            => 'Missing Remote User.',
        'STATUS_NO_IDENTITY_PROVIDER'      => 'Missing IdP.',
        'STATUS_CLIENT_NOT_FOUND'          => 'Missing client.',
        'STATUS_EPTID_MISMATCH'            => 'EPTID mismatch.',
        'STATUS_PAIRWISE_ID_MISMATCH'      => 'Pairwise ID mismatch.',
        'STATUS_SUBJECT_ID_MISMATCH'       => 'Subject ID mismatch.',
        'STATUS_QDL_ERROR'                 => 'General QDL script error.',
        'STATUS_QDL_RUNTIME_ERROR'         => 'Runtime QDL script error.',
        'STATUS_TRANSACTION_NOT_FOUND'     => 'Transaction not found.',
        'STATUS_EXPIRED_TOKEN'             => 'Expired token.',
        'STATUS_CREATE_TRANSACTION_FAILED' => 'Failed to initialize OIDC flow.',
        'STATUS_UNKNOWN_CALLBACK'          => 'The redirect_uri does not match a registered callback URI.',
        'STATUS_MISSING_CLIENT_ID'         => 'Missing client_id parameter.',
        'STATUS_NO_REGISTERED_CALLBACKS'   => 'No registered callback URIs.',
        'STATUS_UNKNOWN_CLIENT'            => 'Unknown client_id.',
        'STATUS_UNAPPROVED_CLIENT'         => 'Client has not been approved.',
        'STATUS_NO_SCOPES'                 => 'Missing or empty scope parameter.',
        'STATUS_MALFORMED_SCOPE'           => 'Malformed scope parameter.',
    );

    /**
     * @var array $CLIENT_ERRORS An array of integer/hex values of
     *      client-intiated errors that should NOT generate an email alert.
     */
    public static $CLIENT_ERRORS = array(
        0xFFFF1,  // STATUS_DUPLICATE_PARAMETER_FOUND
        0xFFFF7,  // STATUS_MALFORMED_INPUT_ERROR
        0xFFFF9,  // STATUS_MISSING_PARAMETER_ERROR
        0xFFFFF,  // STATUS_CLIENT_NOT_FOUND
        0x10001,  // STATUS_TRANSACTION_NOT_FOUND
        0x10003,  // STATUS_EXPIRED_TOKEN
        0x10007,  // STATUS_UNKNOWN_CALLBACK
        0x10009,  // STATUS_MISSING_CLIENT_ID
        0x1000D,  // STATUS_UNKNOWN_CLIENT
        0x1000F,  // STATUS_UNAPPROVED_CLIENT
        0x10011,  // STATUS_NO_SCOPES
        0x10013,  // STATUS_MALFORMED_SCOPE
        0x100007, // STATUS_QDL_ERROR
    );

    /**
     * @var array $user_attrs An array of all the user attributes that
     *      get passed to the getUser function. This is available to other
     *      function since these user attributes are set frequently
     *      throughout the code.
     */
    public static $user_attrs = [
        'remote_user',
        'idp',
        'idp_display_name',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'loa',
        'eppn',
        'eptid',
        'open_id',
        'oidc',
        'subject_id',
        'pairwise_id',
        'affiliation',
        'ou',
        'member_of',
        'acr',
        'amr',
        'preferred_username',
        'entitlement',
        'itrustuin',
        'eduPersonOrcid',
        'uidNumber'
    ];

    /**
     * @var int|null $status The returned status code from dbService calls
     */
    public $status;

    /**
     * @var string|null $call_input The input parameters passed to the
     *      dbService in the call() function.
     */
    public $call_input;

    /**
     * @var string|null $call_output The output returned by curl_exec() in
     *      the call() function.
     */
    public $call_output;

    /**
     * @var string|null $user_uid The CILogon UID
     */
    public $user_uid;

    /**
     * @var string|null $remote_user The HTTP session REMOTE_USER
     */
    public $remote_user;

    /**
     * @var string|null $idp The Identity Provider's entityId
     */
    public $idp;

    /**
     * @var string|null $idp_display_name The Identity Provider's name
     */
    public $idp_display_name;

    /**
     * @var string|null $first_name User's given name
     */
    public $first_name;

    /**
     * @var string|null $last_name User's family name
     */
    public $last_name;

    /**
     * @var string|null $display_name User's full name
     */
    public $display_name;

    /**
     * @var string|null $email User's email address
     */
    public $email;

    /**
     * @var string|null $loa Level of Assurance (Note: not saved in database)
     */
    public $loa;

    /**
     * @var string|null $distinguished_name X.509 DN + email address
     */
    public $distinguished_name;

    /**
     * @var string|null $eppn eduPersonPrincipalName
     */
    public $eppn;

    /**
     * @var string|null $eptid eduPersonTargetedID
     */
    public $eptid;

    /**
     * @var string|null $open_id Old Google OpenID 2.0 identifier
     */
    public $open_id;

    /**
     * @var string|null $oidc OpenID Connect identifier
     */
    public $oidc;

    /**
     * @var string|null $affiliation eduPersonScopedAffiliation
     */
    public $affiliation;

    /**
     * @var string|null $ou Organizational Unit
     */
    public $ou;

    /**
     * @var string|null $member_of isMemberOf group information
     */
    public $member_of;

    /**
     * @var string|null $acr Authentication Context Class Ref
     */
    public $acr;

    /**
     * @var string|null $amr Authentication Method Reference from ORCID
     */
    public $amr;

    /**
     * @var string|null $preferred_username The GitHub login name
     */
    public $preferred_username;

    /**
     * @var string|null $entitlement eduPersonEntitlement
     */
    public $entitlement;

    /**
     * @var string|null $itrustuin Person's univeristy ID number
     */
    public $itrustuin;

    /**
     * @var string|null $eduPersonOrcid ORCID identifier
     */
    public $eduPersonOrcid;

    /**
     * @var string|null $uidNumber Person's user ID number
     */
    public $uidNumber;

    /**
     * @var string|null $subject_id Person's univeristy subject identifier
     */
    public $subject_id;

    /**
     * @var string|null $pairwise_id Person's univeristy pairwise identifier
     */
    public $pairwise_id;

    /**
     * @var string|null $serial_string CILogon serial string (e.g., A34201)
     */
    public $serial_string;

    /**
     * @var string|null $create_time Time user entry was created
     */
    public $create_time;

    /**
     * @var string|null $oauth_token OAuth 2.0 token
     */
    public $oauth_token;

    /**
     * @var string|null $cilogon_callback OAuth 1.0a callback URL
     */
    public $cilogon_callback;

    /**
     * @var string|null $cilogon_success OAuth 1.0a success URL
     */
    public $cilogon_success;

    /**
     * @var string|null $cilogon_failure OAuth 1.0a failure URL
     */
    public $cilogon_failure;

    /**
     * @var string|null $cilogon_portal_name OAuth client name
     */
    public $cilogon_portal_name;

    /**
     * @var string|null $client_id OAuth 2.0 client_id
     */
    public $client_id;

    /**
     * @var string|null $user_code OAuth 2.0 Device Authz Grant flow user_code
     */
    public $user_code;

    /**
     * @var string|null $scope Space-separated list of OAuth 2.0 scopes
     *      associated with the user_code
     */
    public $scope;

    /**
     * @var string|null $grant The authorization grant returned by
     *      checkUserCode which can be used as the 'code' for
     *      setTransactionState to associate an authenticated user_uid with
     *      the device flow transaction.
     */
    public $grant;

    /**
     * @var array $idp_uids IdPs stored in the 'values' of the array
     */
    public $idp_uids;

    /**
     * @var string|null $error OAuth2/OIDC authentication error response
     *      code
     */
    public $error;

    /**
     * @var string|null $error_description Optional OAuth2/OIDC
     *      authentication error response human-readable description
     */
    public $error_description;

    /**
     * @var string|null $error_uri Optional OAuth2/OIDC authentication error
     *      response web page of additional error information
     */
    public $error_uri;

    /**
     * @var string|null $custom_error_uri A non-OAuth2-spec redirect URI
     *      when a QDL script determines user authz error (CIL-1342).
     */
    public $custom_error_uri;

    /**
     * @var string|null $dbservice URL The URL to use for the dbService
     */
    private $dbserviceurl;

    /**
     * __construct
     *
     * Default constructor.  All of the various class members are
     * initialized to 'null' or empty arrays.
     *
     * @param string $serviceurl (Optional) The URL of the database service
     *        servlet
     */
    public function __construct($serviceurl = DEFAULT_DBSERVICE_URL)
    {
        $this->clear();
        $this->setDBServiceURL($serviceurl);
    }

    /**
     * getDBServiceURL
     *
     * Returns the full URL of the database servlet used by the call()
     * function.
     *
     * @return string The URL of the database service servlet
     */
    public function getDBServiceURL()
    {
        return $this->dbserviceurl;
    }

    /**
     * setDBServiceURL
     *
     * Set the private variable $dbserviceurl to the full URL of the
     * database servlet, which is used by the call() function.
     *
     * @param string $serviceurl The URL of the database service servlet.
     */
    public function setDBServiceURL($serviceurl)
    {
        $this->dbserviceurl = $serviceurl;
    }

    /**
     * clear
     *
     * Set all of the class members to 'null' or empty arrays.
     */
    public function clear()
    {
        $this->clearUser();
        $this->clearPortal();
        $this->clearUserCode();
        $this->clearIdps();
        $this->clearErrorResponse();
    }

    /**
     * clearUser
     *
     * Set all of the class member variables associated with getUser()
     * to 'null'.
     */
    public function clearUser()
    {
        foreach (static::$user_attrs as $value) {
            $this->$value = null;
        }
        $this->status = null;
        $this->call_input = null;
        $this->call_output = null;
        $this->user_uid = null;
        $this->distinguished_name = null;
        $this->serial_string = null;
        $this->create_time = null;
    }

    /**
     * clearPortal
     *
     * Set all of the class member variables associated with
     * getPortalParameters() to 'null'.
     */
    public function clearPortal()
    {
        $this->status = null;
        $this->call_input = null;
        $this->call_output = null;
        $this->oauth_token = null;
        $this->cilogon_callback = null;
        $this->cilogon_success = null;
        $this->cilogon_failure = null;
        $this->cilogon_portal_name = null;
    }

    /**
     * clearUserCode
     *
     * Set the class member variables associated with
     * checkUserCode() to 'null'
     */
    public function clearUserCode()
    {
        $this->status = null;
        $this->call_input = null;
        $this->call_output = null;
        $this->user_code = null;
        $this->client_id = null;
        $this->scope = null;
        $this->grant = null;
    }

    /**
     * clearIdps
     *
     * Set the class member variable $idp_uids to an empty array.
     */
    public function clearIdps()
    {
        $this->status = null;
        $this->call_input = null;
        $this->call_output = null;
        $this->idp_uids = array();
    }

    /**
     * clearErrorResponse
     *
     * Set the class member variables associated with setTransactionState()
     * to 'null'.
     */
    public function clearErrorResponse()
    {
        $this->status = null;
        $this->call_input = null;
        $this->call_output = null;
        $this->error = null;
        $this->error_description = null;
        $this->error_uri = null;
        $this->custom_error_uri = null;
    }

    /**
     * getUser
     *
     * This method calls the 'getUser' action of the servlet and sets
     * the class member variables associated with user info
     * appropriately.  If the servlet returns correctly (i.e. an HTTP
     * status code of 200), this method returns true.
     *
     * @param mixed $args Variable number of parameters: 1, or more.
     *        For 1 parameter : $uid (database user identifier)
     *        For more than 1 parameter, parameters can include
     *        any/all of the values in the $user_attrs class array.
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function getUser(...$args)
    {
        $retval = false;
        $this->clearUser();
        $this->setDBServiceURL(DEFAULT_DBSERVICE_URL);
        $numargs = count($args);
        if ($numargs == 1) {
            $retval = $this->call('action=getUser&user_uid=' .
                urlencode($args[0]));
        } elseif ($numargs > 1) {
            // Find 'idp' and 'loa' and save them for later
            $idp = $args[array_search('idp', static::$user_attrs)];
            $loa_pos = array_search('loa', static::$user_attrs);
            $loa = '';
            if ($numargs > $loa_pos) {
                $loa = $args[$loa_pos];
            }

            $cmd = 'action=getUser';
            $attr_arr = array();
            $ou_pos = array_search('ou', static::$user_attrs);
            for ($i = 0; $i < $numargs; $i++) {
                $arg = $args[$i];
                if (strlen($arg) > 0) {
                    if ($i > $ou_pos) {
                        // Put params after $ou into JSON object
                        $attr_arr[static::$user_attrs[$i]] = $arg;
                    } else {
                        // CIL-2178 For SAML-based IdPs, if OMIT_IDP is true,
                        // don't pass "idp" parameter to dbService
                        if (
                            (static::$user_attrs[$i] == 'idp') &&
                            (defined('OMIT_IDP')) &&
                            (OMIT_IDP === true) &&
                            (!in_array($idp, Util::$oauth2idps, true))
                        ) {
                            // Omit IdP from the dbService call - no-op
                        } else {
                            $cmd .= '&' . static::$user_attrs[$i] . '=' . urlencode($arg);
                        }
                    }
                }
            }
            // CIL-1738 Put $loa in database as eduPersonAssurance
            if ((strlen($loa) > 0) && ($loa != 'openid')) {
                $attr_arr['eduPersonAssurance'] = json_encode(
                    explode(';', $loa),
                    JSON_UNESCAPED_SLASHES
                );
            }
            // If any elements in $attr_arr, append converted JSON object
            if (count($attr_arr) > 0) {
                if (
                    ($attr_json = json_encode(
                        $attr_arr,
                        JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES
                    )
                    ) !== false
                ) {
                    $cmd .= '&attr_json=' . urlencode($attr_json);
                }
            }
            // Add 'us_idp' parameter for InCommon/Google (1) or eduGAIN (0)
            $us_idp = 0;
            if (
                (Util::getIdpList()->isRegisteredByInCommon($idp)) ||
                (in_array($idp, Util::$oauth2idps, true))
            ) {
                $us_idp = 1;
            }
            $cmd .= "&us_idp=$us_idp";

            $retval = $this->call($cmd);
        }
        return $retval;
    }

    /**
     * removeUser
     *
     * This method calls the 'removeUser' action of the servlet and
     * sets the class member variable $status appropriately.  If the
     * servlet returns correctly (i.e. an HTTP status code of 200),
     * this method returns true.
     *
     * @param string $uid The database user identifier
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function removeUser($uid)
    {
        $this->clearUser();
        $this->setDBServiceURL(DEFAULT_DBSERVICE_URL);
        return $this->call('action=removeUser&user_uid=' .
            urlencode($uid));
    }

    /**
     * getPortalParameters
     *
     * This method calls the 'getPortalParameter' action of the servlet
     * and sets the class member variables associated with the portal
     * parameters appropriately. If the servlet returns correctly (i.e.
     * an HTTP status code of 200), this method returns true.
     *
     * @param string $oauth_token The database OAuth identifier token
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function getPortalParameters($oauth_token)
    {
        $retval = false;

        $this->clearPortal();
        if (defined('OAUTH1_DBSERVICE_URL')) {
            $this->setDBServiceURL(OAUTH1_DBSERVICE_URL);
            $retval = $this->call('action=getPortalParameter&oauth_token=' .
                urlencode($oauth_token));
        }

        return $retval;
    }

    /**
     * getIdps
     *
     * This method calls the 'getAllIdps' action of the servlet and
     * sets the class member array $idp_uris to contain all of the
     * Idps in the database, stored in the 'values' of the array.  If
     * the servlet returns correctly (i.e. an HTTP status code of 200),
     * this method returns true.
     *
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function getIdps()
    {
        $this->clearIdps();
        $this->setDBServiceURL(DEFAULT_DBSERVICE_URL);
        return $this->call('action=getAllIdps');
    }

    /**
     * setIdps
     *
     * This method calls the 'setAllIdps' action of the servlet using
     * the class memeber array $idp_uris as the source for the Idps to
     * be stored to the database.  Note that if this array is empty,
     * an error code will be returned in the status since at least one
     * IdP should be saved to the database.  If you want to pass an
     * array of Idps to be saved, see the setIdpsFromKeys($array) and
     * setIdpsFromValues($array) methods.  If the servlet returns
     * correctly (i.e. an HTTP status code of 200), this method
     * returns true.
     *
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function setIdps()
    {
        $retval = false;
        $this->setDBServiceURL(DEFAULT_DBSERVICE_URL);
        $idpcount = count($this->idp_uids);
        $idpidx = 0;
        if ($idpcount > 0) {
            // Loop through the idp_uids in chunks of 50 to deal
            // with query parameter limit of http browsers/servers.
            while ($idpidx < $idpcount) { // Loop through all IdPs
                $fiftyidx = 0;
                $idplist = '';
                while (
                    ($fiftyidx < 50) && // Send 50 IdPs at a time
                       ($idpidx < $idpcount)
                ) {
                    $idplist .=  '&idp_uid=' .
                                 urlencode($this->idp_uids[$idpidx]);
                    $fiftyidx++;
                    $idpidx++;
                }
                $cmd = 'action=setAllIdps' . $idplist;
                $retval = $this->call($cmd);
            }
        }
        return $retval;
    }

    /**
     * setIdpsFromKeys
     *
     * This is a convenience method which calls setIdps using a
     * passed-in array of IdPs stored as the keys of the array.  It
     * first sets the class member array $idp_uids appropriately and
     * then calls the setIdps() method. If the servlet returns
     * correctly (i.e. an HTTP status code of 200), this method
     * returns true.  See also setIdpsFromValues().
     *
     * @param array $idps An array of IdPs to be saved, stored in the
     *       'keys' of the array.
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function setIdpsFromKeys($idps)
    {
        $this->clearIdps();
        foreach ($idps as $key => $value) {
            $this->idp_uids[] = $key;
        }
        return $this->setIdps();
    }

    /**
     * setIdpsFromValues
     *
     * This is a convenience method which calls setIdps using a
     * passed-in array of IdPs stored as the values of the array.  It
     * first sets the class member array $idp_uids appropriately and
     * then calls the setIdps() method. If the servlet returns
     * correctly (i.e. an HTTP status code of 200), this method
     * returns true.  See also setIdpsFromKeys().
     *
     * @param array $idps An array of IdPs to be saved, stored in the
     *        'values' of the array.
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function setIdpsFromValues($idps)
    {
        $this->clearIdps();
        foreach ($idps as $value) {
            $this->idp_uids[] = $value;
        }
        return $this->setIdps();
    }

    /**
     * setTransactionState
     *
     * This method calls the 'setTransactionState' action of the OAuth
     * 2.0 servlet to associate the OAuth 2.0 'code' with the database
     * user UID. This is necessary for the OAuth 2.0 server to be able
     * to return information about the user (name, email address) as
     * well as return a certificate for the user. If the servlet
     * returns correctly (i.e., an HTTP status code of 200), this method
     * returns true. Check the 'status' return value to verify that
     * the transaction state was set successfully.
     *
     * @param string $code The 'code' as returned by the OAuth 2.0 server.
     * @param string $uid The database user UID.
     * @param int $authntime The Unix timestamp of the user authentication.
     * @param string $loa (Optional) The Level of Assurance: '' = basic,
     *        'openid' =  OpenID Connect (e.g., Google),
     *        'http://incommonfederation.org/assurance/silver' = silver
     * @param string $myproxyinfo (Optional) the 'info:...' string to be
     *        passed to MyProxy.
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function setTransactionState(
        $code,
        $uid,
        $authntime,
        $loa = '',
        $myproxyinfo = ''
    ) {
        $retval = false;

        if (defined('OAUTH2_DBSERVICE_URL')) {
            $this->clearErrorResponse();
            $this->setDBServiceURL(OAUTH2_DBSERVICE_URL);
            $retval = $this->call(
                'action=setTransactionState' .
                '&code=' . urlencode($code) .
                '&user_uid=' . urlencode($uid) .
                '&auth_time=' . urlencode($authntime) .
                '&loa=' . urlencode($loa) .
                ((strlen($myproxyinfo) > 0) ?
                    ('&cilogon_info=' . urlencode($myproxyinfo)) : '')
            );
        }

        return $retval;
    }

    /**
     * checkUserCode
     *
     * This method calls the 'checkUserCode' action of the OAuth 2.0 servlet
     * to fetch a client_id associated with a user_code entered by the end
     * user as part of an OAuth2 Device Authorization Grant flow. If the
     * servlet returns correctly (i.e., an HTTP status code of 200), this
     * method returns true. Check the 'status' return value to verify that
     * the user_code is correct. The client_id and 'original' user_code
     * will be available if the input user_code was valid.
     *
     * @param string $user_code The OAuth 2.0 Device Authorization Grant
     *        flow code entered by the user.
     * @return bool True if the servlet returned correctly. client_id and
     *         originally generated user_code will be available.
     *        Return false if user_code was expired or not found.
     */
    public function checkUserCode($user_code)
    {
        $retval = false;

        if (defined('OAUTH2_DBSERVICE_URL')) {
            $this->setDBServiceURL(OAUTH2_DBSERVICE_URL);
            $retval = $this->call(
                'action=checkUserCode' .
                '&user_code=' . urlencode($user_code)
            );
        }

        return $retval;
    }

    /**
     * userCodeApproved
     *
     * This method calls the 'userCodeApproved' action of the OAuth 2.0
     * servlet to let the OA4MP code know that a user has approved a
     * user_code associated with a Device Authorization Grant transaction.
     * If the servlet returns correctly (i.e.,  an HTTP status code of 200),
     * this method returns true. Check the 'status' return value to verify
     * that the user_code is correct and is not expired.
     *
     * @param string $user_code The OAuth 2.0 Device Authorization Grant
     *        flow code entered by the user.
     * @param int $approved (Optional) =1 if the user_code has been approved
     *        by the user (default). =0 if the user clicks 'Cancel' to
     *        deny the user_code approval.
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function userCodeApproved($user_code, $approved = 1)
    {
        $retval = false;

        if (defined('OAUTH2_DBSERVICE_URL')) {
            $this->setDBServiceURL(OAUTH2_DBSERVICE_URL);
            $retval = $this->call(
                'action=userCodeApproved' .
                '&user_code=' . urlencode($user_code) .
                '&approved=' . $approved
            );
        }

        return $retval;
    }

    /**
     * call
     *
     * This method does the brunt of the work for calling the
     * dbService servlet.  The single parameter is a string of
     * 'key1=value1&key2=value2&...' containing all of the parameters
     * for the dbService.  If the servlet returns an HTTP status code
     * of 200, then this method will return true.  It parses the return
     * output for various 'key=value' lines and stores then in the
     * appropriate member variables, urldecoded of course.
     *
     * @param string $params A string containing 'key=value' pairs,
     *        separated by ampersands ('&') as appropriate for passing to a
     *        URL for a GET query.
     * @return bool True if the servlet returned correctly. Else false.
     */
    public function call($params)
    {
        $success = false;

        $attr_json = '';
        $ch = curl_init();
        if ($ch !== false) {
            $url = $this->getDBServiceURL() . '?' . $params;
            $this->call_input = $url;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $output = curl_exec($ch);
            $this->call_output = $output;
            if (curl_errno($ch)) { // Send alert on curl errors
                $log = new Loggit();
                $log->error('Error in DBService::call(): cUrl Error = ' . curl_error($ch) . ', URL Accessed = ' . $url);
                Util::sendErrorAlert(
                    'cUrl Error',
                    'cUrl Error    = ' . curl_error($ch) . "\n" .
                    "URL Accessed  = $url"
                );
            }
            if (!empty($output)) {
                $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpcode == 200) {
                    $success = true;
                    if (preg_match('/status=([^\r\n]+)/', $output, $matches)) {
                        $this->status = (int)(urldecode($matches[1]));
                    }
                    if (preg_match('/user_uid=([^\r\n]+)/', $output, $matches)) {
                        $this->user_uid = urldecode($matches[1]);
                    }
                    if (preg_match('/remote_user=([^\r\n]+)/', $output, $matches)) {
                        $this->remote_user = urldecode($matches[1]);
                    }
                    if (preg_match('/idp=([^\r\n]+)/', $output, $matches)) {
                        $this->idp = urldecode($matches[1]);
                    }
                    if (preg_match('/idp_display_name=([^\r\n]+)/', $output, $matches)) {
                        $this->idp_display_name = urldecode($matches[1]);
                    }
                    if (preg_match('/first_name=([^\r\n]+)/', $output, $matches)) {
                        $this->first_name = urldecode($matches[1]);
                    }
                    if (preg_match('/last_name=([^\r\n]+)/', $output, $matches)) {
                        $this->last_name = urldecode($matches[1]);
                    }
                    if (preg_match('/[^_]display_name=([^\r\n]+)/', $output, $matches)) {
                        $this->display_name = urldecode($matches[1]);
                    }
                    if (preg_match('/email=([^\r\n]+)/', $output, $matches)) {
                        $this->email = urldecode($matches[1]);
                    }
                    if (preg_match('/distinguished_name=([^\r\n]+)/', $output, $matches)) {
                        $this->distinguished_name = urldecode($matches[1]);
                    }
                    if (preg_match('/eppn=([^\r\n]+)/', $output, $matches)) {
                        $this->eppn = urldecode($matches[1]);
                    }
                    if (preg_match('/eptid=([^\r\n]+)/', $output, $matches)) {
                        $this->eptid = urldecode($matches[1]);
                    }
                    if (preg_match('/open_id=([^\r\n]+)/', $output, $matches)) {
                        $this->open_id = urldecode($matches[1]);
                    }
                    if (preg_match('/oidc=([^\r\n]+)/', $output, $matches)) {
                        $this->oidc = urldecode($matches[1]);
                    }
                    if (preg_match('/subject_id=([^\r\n]+)/', $output, $matches)) {
                        $this->subject_id = urldecode($matches[1]);
                    }
                    if (preg_match('/pairwise_id=([^\r\n]+)/', $output, $matches)) {
                        $this->pairwise_id = urldecode($matches[1]);
                    }
                    if (preg_match('/affiliation=([^\r\n]+)/', $output, $matches)) {
                        $this->affiliation = urldecode($matches[1]);
                    }
                    if (preg_match('/ou=([^\r\n]+)/', $output, $matches)) {
                        $this->ou = urldecode($matches[1]);
                    }
                    if (preg_match('/attr_json=([^\r\n]+)/', $output, $matches)) {
                        // Decode $attr_json into class members later
                        $attr_json = urldecode($matches[1]);
                    }
                    if (preg_match('/serial_string=([^\r\n]+)/', $output, $matches)) {
                        $this->serial_string = urldecode($matches[1]);
                    }
                    if (preg_match('/create_time=([^\r\n]+)/', $output, $matches)) {
                        $this->create_time = urldecode($matches[1]);
                    }
                    if (preg_match('/oauth_token=([^\r\n]+)/', $output, $matches)) {
                        $this->oauth_token = urldecode($matches[1]);
                    }
                    if (preg_match('/cilogon_callback=([^\r\n]+)/', $output, $matches)) {
                        $this->cilogon_callback = urldecode($matches[1]);
                    }
                    if (preg_match('/cilogon_success=([^\r\n]+)/', $output, $matches)) {
                        $this->cilogon_success = urldecode($matches[1]);
                    }
                    if (preg_match('/cilogon_failure=([^\r\n]+)/', $output, $matches)) {
                        $this->cilogon_failure = urldecode($matches[1]);
                    }
                    if (preg_match('/cilogon_portal_name=([^\r\n]+)/', $output, $matches)) {
                        $this->cilogon_portal_name = urldecode($matches[1]);
                    }
                    if (preg_match('/user_code=([^\r\n]+)/', $output, $matches)) {
                        $this->user_code = urldecode($matches[1]);
                    }
                    if (preg_match('/client_id=([^\r\n]+)/', $output, $matches)) {
                        $this->client_id = urldecode($matches[1]);
                    }
                    if (preg_match('/scope=([^\r\n]+)/', $output, $matches)) {
                        $this->scope = urldecode($matches[1]);
                    }
                    if (preg_match('/grant=([^\r\n]+)/', $output, $matches)) {
                        $this->grant = urldecode($matches[1]);
                    }
                    if (preg_match_all('/idp_uid=([^\r\n]+)/', $output, $matches)) {
                        foreach ($matches[1] as $value) {
                            $this->idp_uids[] = urldecode($value);
                        }
                    }
                    if (preg_match('/error=([^\r\n]+)/', $output, $matches)) {
                        $this->error = urldecode($matches[1]);
                    }
                    if (preg_match('/error_description=([^\r\n]+)/', $output, $matches)) {
                        $this->error_description = urldecode($matches[1]);
                    }
                    // CIL-1342 Redirect to custom URL upon QDL errors
                    if (preg_match('/custom_error_uri=([^\r\n]+)/', $output, $matches)) {
                        $this->custom_error_uri = urldecode($matches[1]);
                    } elseif (preg_match('/error_uri=([^\r\n]+)/', $output, $matches)) {
                        $this->error_uri = urldecode($matches[1]);
                    }
                }
            }
            curl_close($ch);
        }

        // Convert $attr_json into array and extract elements into class members
        if (strlen($attr_json) > 0) {
            $attr_arr = json_decode($attr_json, true);
            if (!is_null($attr_arr)) {
                if (isset($attr_arr['member_of'])) {
                    $this->member_of = $attr_arr['member_of'];
                }
                if (isset($attr_arr['acr'])) {
                    $this->acr = $attr_arr['acr'];
                }
                if (isset($attr_arr['amr'])) {
                    $this->amr = $attr_arr['amr'];
                }
                if (isset($attr_arr['preferred_username'])) {
                    $this->preferred_username = $attr_arr['preferred_username'];
                }
                if (isset($attr_arr['entitlement'])) {
                    $this->entitlement = $attr_arr['entitlement'];
                }
                if (isset($attr_arr['itrustuin'])) {
                    $this->itrustuin = $attr_arr['itrustuin'];
                }
                if (isset($attr_arr['eduPersonOrcid'])) {
                    $this->eduPersonOrcid = $attr_arr['eduPersonOrcid'];
                }
                if (isset($attr_arr['uidNumber'])) {
                    $this->uidNumber = $attr_arr['uidNumber'];
                }
            }
        }

        return $success;
    }

    /**
     * statusText
     *
     * This method returns the human-readable description of the current
     * $status (which corresponding to a STATUS_* (hex) number in the
     * $STATUS array).
     *
     * @return string A human-readable version of the $status, or empty
     *         string if no such $status is found in the $STATUS array.
     */
    public function statusText()
    {
        $retstr = '';

        if (!is_null($this->status)) {
            $retstr = static::statusToStatusText($this->status);
        }

        return $retstr;
    }

    /**
     * statusToStatusText
     *
     * This method returns a human-readable description of the passed-in
     * $status, which corresponds to a STATUS_* (hex) number in the $STATUS
     * array. If the incoming $status is blank, return empty string.
     *
     * @param string $status The status returned by a call to the dbService.
     * @return string A human-readable version of the $status, or empty
     *         string if no such $status is empty.
     */
    public static function statusToStatusText($status)
    {
        $retstr = '';

        if (strlen($status) > 0) {
            $status_value = array_search(((int)$status), static::$STATUS);
            if ($status_value !== false) {
                if (array_key_exists($status_value, static::$STATUS_TEXT)) {
                    $retstr = static::$STATUS_TEXT[$status_value];
                }
            }
        }

        return $retstr;
    }
}
