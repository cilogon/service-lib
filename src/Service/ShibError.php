<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use CILogon\Service\Content;
use CILogon\Service\Loggit;

/**
 * ShibError
 * This class handles errors passed by the Shibboleth SP
 * software when /etc/shibboleth/shibboleth2.xml is
 * configured as follows:
 *     <Errors redirectErrors="https://cilogon.org"/>
 * Note that with v.2.4.x of the Shibboleth SP software,
 * the 'redirectErrors' attribute must be an absolute URL,
 * meaning that the shibboleth2.xml file will be different
 * for https://test.cilogon.org/.  V.2.5 of the SP
 * software should allow relative URLS.
 *
 * To handle errors from the Shibboleth SP software,
 * create a new ShibError instance at the root index.php
 * file. The constructor looks for various 'GET'
 * parameters that get passed by the SP software. If there
 * is an error, show an error page with the info provided
 * by the SP software.
 *
 * Example usage:
 *     require_once 'ShibError.php';
 *     // The class constructor does ALL of the work
 *     $shiberror = new ShibError();
 */
class ShibError
{
    /**
     * @var array $errorarray Holds the values of the various shibboleth
     *      error parameters
     */
    private $errorarray;

    /**
     * @var array $errorparams Shibboleth error parameters passed to the
     * redirectErrors handler:
     * https://wiki.shibboleth.net/confluence/display/SHIB2/NativeSPErrors
     */
    private static $errorparams = array(
        'requestURL',    // The URL associated with the request
        'errorType',     // The general type of error
        'errorText',     // The actual error message
        'entityID',      // Name of identity provider, if known
        'now',           // Current date and time
        'statusCode',    // SAML status code causing error, sent by IdP
        'statusCode2',   // SAML sub-status code causing error, sent by IdP
        'statusMessage', // SAML status message, sent by IdP
        'contactName',   // A support contact name for the IdP
                         //     provided by that site's metadata.
        'contactEmail',  // A contact email address for the IdP contact
                         //     provided by that site's metadata.
        'errorURL',      // The URL of an error handling page for the IdP
                         //     provided by that site's metadata.
    );

    /**
     * __construct
     *
     * Default constructor. This method attempts to read in the
     * various Shibboleth SP error parameters that would have been
     * passed as parameters to the 'redirectErrors' handler URL, i.e.
     * in the $_GET global variable.
     *
     * @return ShibError A new ShibError object.
     */
    public function __construct()
    {
        $this->errorarray = array();
        foreach (self::$errorparams as $param) {
            if (isset($_GET[$param])) {
                $this->errorarray[$param] = rtrim($_GET[$param]);
            }
        }

        if ($this->isError()) {
            // CIL-410 Temporary fix for /secure/testidp Shibboleth error.
            // Check for error and redirect to /testidp .
            if (
                (isset($this->errorarray['errorType'])) &&
                ($this->errorarray['errorType'] == 'shibsp::ConfigurationException') &&
                (isset($this->errorarray['errorText'])) &&
                ($this->errorarray['errorText'] ==
                    'None of the configured SessionInitiators handled the request.') &&
                (preg_match('%/secure/testidp%', $this->errorarray['requestURL']))
            ) {
                header('Location: https://' . Util::getDN() . '/testidp/');
            // CIL-480 Check for user IdP login failure and OAuth transaction
            // and redirect appropriately
            } elseif (
                (isset($this->errorarray['errorType'])) &&
                ($this->errorarray['errorType'] == 'opensaml::FatalProfileException') &&
                (isset($this->errorarray['errorText'])) &&
                ($this->errorarray['errorText'] == 'SAML response reported an IdP error.') &&
                (isset($this->errorarray['statusCode2'])) &&
                ($this->errorarray['statusCode2'] == 'urn:oasis:names:tc:SAML:2.0:status:AuthnFailed')
            ) {
                $clientparams = json_decode(Util::getSessionVar('clientparams'), true); // OAuth 2.0
                $failureuri = Util::getSessionVar('failureuri'); // OAuth 1.0a
                if (array_key_exists('redirect_uri', $clientparams)) {
                    Util::unsetAllUserSessionVars();
                    header('Location: ' . $clientparams['redirect_uri'] .
                        (preg_match('/\?/', $clientparams['redirect_uri']) ? '&' : '?') .
                        'error=access_denied&error_description=' .
                        'User%20denied%20authorization%20request' .
                        ((array_key_exists('state', $clientparams)) ?
                            '&state=' . $clientparams['state'] : ''));
                } elseif (strlen($failureuri) > 0) {
                    Util::unsetAllUserSessionVars();
                    header('Location: ' . $failureuri . '?reason=cancel');
                } else {
                    $this->printError();
                }
            } else {
                $this->printError();
            }
            exit; // No further processing!!!
        }
    }

    /**
     * isError
     *
     * This method returns true if several Shibboleth error parameters
     * were passed in via the $_GET array. These parameters are sent
     * by the Shibboleth SP software when there is an error.
     *
     * @return bool True if several Shibboleth error parameters were
     *         passed to the handler URL. False otherwise.
     */
    public function isError()
    {
        return ((isset($this->errorarray['requestURL'])) &&
                (isset($this->errorarray['errorType'])) &&
                (isset($this->errorarray['errorText'])) &&
                (isset($this->errorarray['now'])) &&
                (strlen($this->errorarray['requestURL']) > 0) &&
                (strlen($this->errorarray['errorType']) > 0) &&
                (strlen($this->errorarray['errorText']) > 0) &&
                (strlen($this->errorarray['now']) > 0));
    }

    /**
     * printError
     *
     * This method prints out text for an error message page. It also
     * logs the error and sends an alert email with the shibboleth
     * error parameters. You should probably 'exit()' after you call
     * this so more HTML doesn't get output.
     */
    public function printError()
    {
        $errorstr1 = '';  // For logging - one line
        $errorstr2 = '';  // For HTML and email - multi-line
        $contactemail = '';
        $emailmsg = '';
        foreach ($this->errorarray as $key => $value) {
            $errorstr1 .= Util::htmlent($key . '="' . $value . '" ');
            $errorstr2 .= Util::htmlent(sprintf("%-14s= %s\n", $key, $value));
            if ($key == 'contactEmail') {
                $contactemail = $value;
            }
        }

        $log = new Loggit();
        $log->error('Shibboleth error: ' . $errorstr1);

        // CIL-1576 Enable user to email IdP Help on Shib errors
        if (strlen($contactemail) > 0) {
            $contactemail = preg_replace('/^mailto:/', '', $contactemail);
            $idp_display_name = Util::getSessionVar('idp_display_name');
            // Attempt to get the OAuth1/OIDC client name
            $portalname = Util::getSessionVar('portalname');
            if (strlen($portalname) == 0) {
                $portalname = @$clientparams['client_name'];
            }
            $emailmsg = 'mailto:' . $contactemail .
                '?subject=Problem Logging On To CILogon' .
                '&cc=' . EMAIL_HELP .
                '&body=Hello, I am having trouble logging on to https://' . DEFAULT_HOSTNAME . '/ ' .
                ((strlen($idp_display_name) > 0) ? "using the $idp_display_name Identity Provider (IdP) " : '') .
                ((strlen($portalname) > 0) ? 'for ' . strip_tags($portalname) . ' ' : '') .
                'due to a Shibboleth / SAML error:%0D%0A%0D%0A' .
                preg_replace('/\n/', '%0D%0A', $errorstr2) .
                '%0D%0AThank you for any help you can provide.';
        }

        Content::printHeader('Shiboleth Error');
        Content::printCollapseBegin('shiberror', 'Shibboleth Error', false);

        echo '
            <div class="card-body px-5">
              <div class="card-text my-2">
                ',
                _('The CILogon Service has encountered a Shibboleth error.'), '

              </div> <!-- end card-text -->
        ';

        Content::printErrorBox('<pre>' . $errorstr2 . '</pre>');

        echo '
              <div class="card-text my-2">
                ',
                _('This may be a temporary error. Please try again later, ' .
                ' or contact us at the email address at the bottom ' .
                'of the page.'), '
              </div> <!-- end card-text -->
        ';

        $skin = Util::getSkin();
        $forceauthn = $skin->getConfigOption('forceauthn');
        if ((!is_null($forceauthn)) && ((int)$forceauthn == 1)) {
            echo '
              <div class="card-text my-2">
                ',
                _('Note that this error may be due to your selected ' .
                'Identity Provider (IdP) not fully supporting ' .
                '&quot;forced reauthentication&quot;. This setting ' .
                'forces users to log in at the IdP every time, thus ' .
                'bypassing Single Sign-On (SSO).'), '
              </div> <!-- end card-text -->
            ';
        }

        Content::printFormHead('Error');

        echo '
              <div class="card-text my-2">
                <div class="form-group">
                  <div class="form-row align-items-center
                  justify-content-center">';

        // CIL-1576 Add "Request Help" button for Shib errors
        if (strlen($emailmsg) > 0) {
            echo '
                    <div class="col-auto">
                      <a class="btn btn-primary"
                      title="Request Help"
                      href="', $emailmsg, '">',
                      _('Request Help'),
                      '</a>
                    </div> <!-- end col-auto -->';
        }

        echo '
                    <div class="col-auto">
                      <input type="submit" name="submit"
                      class="btn btn-primary submit form-control"
                      title="', _('Proceed'), '"
                      value="', _('Proceed'), '" />
                    </div> <!-- end col-auto -->
                  </div> <!-- end form-row align-items-center -->
                </div> <!-- end form-group -->
              </div> <!-- end card-text -->
            </form>
            </div> <!-- end card-body -->';

        Content::printCollapseEnd();
        Content::printFooter();

        // CIL-1098 Don't send email alerts for IdP-generated errors
        // Util::sendErrorAlert('Shibboleth Error', $errorstr2);
    }
}
