<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use League\OAuth2\Client\Provider;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\Google;
use CILogon\OAuth2\Client\Provider\ORCID;

/**
 * OAuth2Provider
 */
class OAuth2Provider
{
    /**
     * @var League\OAuth2\Client\Provider $provider Member variable for
     *      OAuth2 PHP provider object
     */
    public $provider = null;

    /**
     * @var array $authzUrlOpts An array of parameters to be passed to
     *      getAuthorizationUrl().
     */
    public $authzUrlOpts = array();

    /**
     * __construct
     *
     * Class constructor. Initializes the class variables using the passed-in
     * Identity Provider ($idp). Sets the class variables 'provider' (the
     * OAuth2 Client library provider object) and 'authzUrlOpts' (for use
     * with getAuthorizationUrl()).
     *
     * @param string|null $idp The Identity Provider to use for OAuth2
     *        connection.
     */
    public function __construct($idp)
    {
        if (is_null($idp)) {
            $idp = Util::getSessionVar('idpname');
        }
        $idp = strtolower($idp);

        $classname = '';
        $extraparams = array();

        // Set the client id and secret for the $idp
        $client_id     = constant(strtoupper($idp) . '_OAUTH2_CLIENT_ID');
        $client_secret = constant(strtoupper($idp) . '_OAUTH2_CLIENT_SECRET');

        if ((strlen($client_id) > 0) && (strlen($client_secret) > 0)) {
            // Set options on a per-IdP basis
            if ($idp == 'google') {
                $classname     = 'League\OAuth2\Client\Provider\Google';
                $this->authzUrlOpts = ['scope' => ['openid','email','profile']];
                $extraparams = array('accessType' => 'offline');
            } elseif ($idp == 'github') {
                $classname     = 'League\OAuth2\Client\Provider\Github';
                $this->authzUrlOpts = ['scope' => ['user:email']];
            } elseif ($idp == 'orcid') {
                $classname     = 'CILogon\OAuth2\Client\Provider\ORCID';
            }

            $this->provider = new $classname(array_merge(array(
                'clientId'     => $client_id,
                'clientSecret' => $client_secret,
                'redirectUri'  => 'https://' . Util::getHN() . '/getuser/'
            ), $extraparams));
        }
    }
}
