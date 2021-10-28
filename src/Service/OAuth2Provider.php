<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use League\OAuth2\Client\Provider;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\Google;
use CILogon\OAuth2\Client\Provider\ORCID;
use TheNetworg\OAuth2\Client\Provider\Azure;

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
     * Identity Provider Display Name ($idpdn). Sets the class variables
     * 'provider' (the OAuth2 Client library provider object) and
     * 'authzUrlOpts' (for use with getAuthorizationUrl()).
     *
     * @param string|null $idpdn The Display Name of the Identity Provider
     *        use for OAuth2 connection.
     */
    public function __construct($idpdn)
    {
        if (is_null($idpdn)) {
            $idpdn = Util::getSessionVar('idp_display_name');
        }
        $idpdn = strtolower($idpdn);

        $classname = '';
        $extraparams = array();

        // Set the client id and secret for the $idpdn
        $client_id     = constant(strtoupper($idpdn) . '_OAUTH2_CLIENT_ID');
        $client_secret = constant(strtoupper($idpdn) . '_OAUTH2_CLIENT_SECRET');

        if ((strlen($client_id) > 0) && (strlen($client_secret) > 0)) {
            // Set options on a per-IdP basis
            if ($idpdn == 'google') {
                $classname     = 'League\OAuth2\Client\Provider\Google';
                $this->authzUrlOpts = ['scope' => ['openid','email','profile']];
                $extraparams = array('accessType' => 'offline');
            } elseif ($idpdn == 'github') {
                $classname     = 'League\OAuth2\Client\Provider\Github';
                $this->authzUrlOpts = ['scope' => ['user:email']];
            } elseif ($idpdn == 'orcid') {
                $classname     = 'CILogon\OAuth2\Client\Provider\ORCID';
                // CIL-799 Use Member API and fetch id_token in order to get 'amr' claim
                $this->authzUrlOpts = ['scope' => ['openid']];
                $extraparams = array('member' => 'true');
            } elseif ($idpdn == 'microsoft') {
                $classname     = 'TheNetworg\OAuth2\Client\Provider\Azure';
                $this->authzUrlOpts = [
                    'scope' => ['openid','email','profile'],
                    'defaultEndPointVersion' => '2.0',
                ];
            }

            $this->provider = new $classname(array_merge(array(
                'clientId'     => $client_id,
                'clientSecret' => $client_secret,
                'redirectUri'  => 'https://' . Util::getHN() . '/getuser/'
            ), $extraparams));
        }
    }
}
