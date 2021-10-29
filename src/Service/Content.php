<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use CILogon\Service\MyProxy;
use CILogon\Service\PortalCookie;
use CILogon\Service\DBService;
use CILogon\Service\OAuth2Provider;
use CILogon\Service\Loggit;
use Net_LDAP2_Util;

/**
 * Content
 */
class Content
{
    /**
     * printHeader
     *
     * This function should be called to print out the main HTML header
     * block for each web page.  This gives a consistent look to the site.
     * Any style changes should go in the cilogon.css file.
     *
     * @param string $title The text in the window's titlebar
     * @param bool $csrfcookie Set the CSRF cookie. Defaults to true.
     */
    public static function printHeader($title = '', $csrfcookie = true)
    {
        if ($csrfcookie) {
            $csrf = Util::getCsrf();
            $csrf->setTheCookie();
        }

        // Find the 'Powered By CILogon' image if specified by the skin
        $poweredbyimg = "/images/poweredbycilogon.png";
        $skin = Util::getSkin();
        $skinpoweredbyimg = (string)$skin->getConfigOption('poweredbyimg');
        if (
            (strlen($skinpoweredbyimg) > 0) &&
            (is_readable('/var/www/html' . $skinpoweredbyimg))
        ) {
            $poweredbyimg = $skinpoweredbyimg;
        }

        echo '<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />

    <title>', $title, '</title>';

        // CIL-1068 Add config option for custom favicon
        $favicon = (string)$skin->getConfigOption('favicon');
        if (
            (strlen($favicon) > 0) &&
            (is_readable('/var/www/html' . $favicon))
        ) {
            $favicontype = (string)$skin->getConfigOption('favicontype');
            // Default file type is '.ico'
            if (strlen($favicontype) == 0) {
                $favicontype = 'image/x-icon';
            }
            echo '
    <link rel="shortcut icon" type="', $favicontype, '" href="', $favicon, '">';
        }

        echo '
    <!-- Font Awesome CSS -->
    <link rel="stylesheet"
          href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"
          integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN"
          crossorigin="anonymous" />
    <!-- Bootstrap CSS -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css"
          integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l"
          crossorigin="anonymous">
    <!-- Bootstrap-Select CSS -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css" />
    <!-- CILogon-specific CSS -->
    <link rel="stylesheet" href="/include/cilogon.css" />
    ';

        $skin->printSkinCSS();

        echo '
  </head>

  <body>
    <div class="skincilogonlogo">
      <a target="_blank" href="http://www.cilogon.org/faq/"><img
      src="', $poweredbyimg , '" alt="CILogon" title="CILogon Service" /></a>
    </div>

    <div class="logoheader">
       <h1><span>[CILogon Service]</span></h1>
    </div>

    <div class="mt-4 container-fluid"> <!-- Main Bootstrap Container -->
    ';

        static::printNoScript();

        // CIL-712 Add skin config option to display a info banner.
        $skinbanner = (string)$skin->getConfigOption('banner');
        if (strlen($skinbanner) > 0) {
            echo '
      <div class="alert alert-secondary alert-dismissible fade show" role="alert">
      ', $skinbanner , '
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
';
        }

        if ((defined('BANNER_TEXT')) && (!empty(BANNER_TEXT))) {
            echo '
      <div class="alert alert-warning alert-dismissible fade show" role="alert">
      ', BANNER_TEXT , '
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

';
        }
    }

    /**
     * printFooter
     *
     * This function should be called to print out the closing HTML block
     * for each web page.
     */
    public static function printFooter()
    {
        $footertext = '
      <p>For questions about this site, please see the <a target="_blank"
        href="http://www.cilogon.org/faq">FAQs</a> or send email to <a
        href="mailto:' . EMAIL_HELP . '">' . EMAIL_HELP . '</a>.</p>
      <p>Know <a target="_blank"
        href="http://ca.cilogon.org/responsibilities">your responsibilities</a>
        for using the CILogon Service.</p>
      <p>See <a target="_blank"
        href="http://ca.cilogon.org/acknowledgements">acknowledgements</a> of
        support for this site.</p>';

        // CIL-767 Allow skin to set footer text
        $skin = Util::getSkin();
        $skinfootertext = (string)$skin->getConfigOption('footertext');
        if (strlen($skinfootertext) > 0) {
            $footertext = $skinfootertext;
        }

        echo '
    </div> <!-- Close Main Bootstrap Container -->
    <footer class="footer">';
        echo $footertext;
        if ((defined('HOSTNAME_FOOTER')) && (HOSTNAME_FOOTER === true)) {
            echo '
      <p style="font-size:xx-small;color:#f5f5f5;color:rgba(245,245,245,0.0);">',
            gethostname(), '
      </p>';
        }
        echo '
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
            integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4="
            crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns"
            crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.18/dist/js/bootstrap-select.min.js"
            integrity="sha256-qo0Cam4XJ0QQ06XnCiCFYBh3GDXU45j3lpUp+em2yBU="
            crossorigin="anonymous"></script>
    <script>$(document).ready(function(){ $(\'[data-toggle="popover"]\').popover(); });</script>
    <script>$("#collapse-gencert").on(\'shown.bs.collapse\', function(){ $("#password1").focus() });</script>
    <script src="/include/cilogon.js"></script>
  </body>
</html>';

        session_write_close();
    }

    /**
     * printFormHead
     *
     * This function prints out the opening <form> tag for displaying
     * submit buttons.  The first parameter is used for the 'action' value
     * of the <form>.  If omitted, getScriptDir() is called to get the
     * location of the current script.  This function outputs a hidden csrf
     * field in the form block.
     *
     * @param string $action (Optional) The value of the form's 'action'
     *        parameter. Defaults to getScriptDir().
     * @param string $method (Optional) The <form> 'method', one of 'get' or
     *        'post'. Defaults to 'post'.
     */
    public static function printFormHead($action = '', $method = 'post')
    {
        static $formnum = 0;

        if (strlen($action) == 0) {
            $action = Util::getScriptDir();
        }

        echo '
          <form action="', $action , '" method="', $method , '"
          autocomplete="off" id="form', sprintf("%02d", ++$formnum), '"
          class="needs-validation" novalidate="novalidate">
        ';
        $csrf = Util::getCsrf();
        echo $csrf->hiddenFormElement();
    }

    /**
     * printWAYF
     *
     * This function prints the list of IdPs in a <select> form element
     * which can be printed on the main login page to allow the user to
     * select 'Where Are You From?'.  This function checks to see if a
     * cookie for the 'providerId' had been set previously, so that the
     * last used IdP is selected in the list.
     *
     * @param bool $showremember (Optional) Show the 'Remember this
     *        selection' checkbox? Defaults to true.
     * @param bool $showcancel (Optional) Force the display of the 'Cancel'
     *        button next to the logon button. Defaults to false, which
     *        means the Cancel button is shown based on the skin
     *        configuration.
     */
    public static function printWAYF($showremember = true, $showcancel = false)
    {
        $idps = static::getCompositeIdPList();
        $skin = Util::getSkin();

        // Check if the user had previously selected an IdP from the list.
        // First, check the portalcookie, then the 'normal' cookie.
        $keepidp = '';
        $providerId = '';
        $pc = new PortalCookie();
        $pn = $pc->getPortalName();
        if (strlen($pn) > 0) {
            $keepidp    = $pc->get('keepidp');
            $providerId = $pc->get('providerId');
        } else {
            $keepidp    = Util::getCookieVar('keepidp');
            $providerId = Util::getCookieVar('providerId');
        }

        // CIL-763 If there was no previously saved cookie for the initially
        // selected IdP, check for 'initialidp=...' query parameter. Note
        // that this value will be overridden by the 'idphint=...' query
        // parameter (if also present).
        if (strlen($providerId) == 0) {
            $initialidp = '';
            // Check clientparams first, then check GET parameters
            $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
            if (!empty(@$clientparams['initialidp'])) {
                $initialidp = $clientparams['initialidp'];
            } elseif (!empty(Util::getGetVar('initialidp'))) {
                $initialidp = Util::getGetVar('initialidp');
            }
            if (strlen($initialidp) > 0) {
                $providerId = static::normalizeOAuth2IdP($initialidp);
            }
        }

        // Make sure previously selected IdP is in list of available IdPs.
        if ((strlen($providerId) > 0) && (!isset($idps[$providerId]))) {
            $keepidp = '';
            $providerId = '';
        }

        // Check if an OIDC client selected an IdP for the transaction.
        // If so, verify that the IdP is in the list of available IdPs.
        $useselectedidp = false;
        $idphintlist = static::getIdphintList($idps);
        if (!empty($idphintlist)) {
            $useselectedidp = true;
            $providerId = $idphintlist[0];
            $newidps = array();
            // Update the IdP selection list to show just the idphintlist.
            foreach ($idphintlist as $value) {
                $newidps[$value] = $idps[$value];
            }
            $idps = $newidps;
            // Re-sort the $idps by Display_Name for correct alphabetization.
            uasort($idps, function ($a, $b) {
                return strcasecmp(
                    $a['Display_Name'],
                    $b['Display_Name']
                );
            });
        }

        // CIL-1080 If the providerId is in the HIDE_IDP_ARRAY, unset it.
        if (
            (defined('HIDE_IDP_ARRAY')) &&
            (in_array($providerId, HIDE_IDP_ARRAY))
        ) {
            $providerId = '';
        }

        $googleauthz = Util::getAuthzUrl('Google');
        $orcidauthz = Util::getAuthzUrl('ORCID');
        $githubauthz = Util::getAuthzUrl('GitHub');
        $microsoftauthz = Util::getAuthzUrl('Microsoft');

        // If no previous providerId, get from skin, or default
        // to Google, ORCID, or the first IdP in the list.
        if (strlen($providerId) == 0) {
            $initialidp = (string)$skin->getConfigOption('initialidp');
            if ((strlen($initialidp) > 0) && (isset($idps[$initialidp]))) {
                $providerId = $initialidp;
            } elseif (isset($idps[$orcidauthz])) {
                $providerId = Util::getAuthzUrl('ORCID');
            } elseif (isset($idps[$googleauthz])) {
                $providerId = Util::getAuthzUrl('Google');
            } else {
                $providerId = array_key_first($idps);
            }
        }

        $selecthelp = '<p>
            CILogon facilitates secure access to CyberInfrastructure (CI).
            In order to use the CILogon Service, you must first select
            an identity provider. An identity provider (IdP) is an
            organization where you have an account and can log on
            to gain access to online services.
        </p>
        <p>
            If you are a faculty, staff, or student member of a university
            or college, please select it for your identity provider.
            If your school is not listed, please contact <a
            href=\'mailto:' . EMAIL_HELP . '\'>' . EMAIL_HELP . '</a>,
            and we will try to add your school in the future.
        </p>
        ';

        if (isset($idps[$orcidauthz])) {
            $selecthelp .= '<p> If you have a <a target=\'_blank\'
            href=\'https://orcid.org/my-orcid\'>ORCID</a> account,
            you can select it for authenticating to the CILogon Service.</p>
            ';
        }
        if (isset($idps[$googleauthz])) {
            $selecthelp .= '<p>If you have a <a target=\'_blank\'
            href=\'https://myaccount.google.com\'>Google</a> account,
            you can select it for authenticating to the CILogon Service.</p>
            ';
        }
        if (isset($idps[$githubauthz])) {
            $selecthelp .= '<p> If you have a <a target=\'_blank\'
            href=\'https://github.com/settings/profile\'>GitHub</a> account,
            you can select it for authenticating to the CILogon Service.</p>
            ';
        }
        if (isset($idps[$microsoftauthz])) {
            $selecthelp .= '<p>If you have a <a target=\'_blank\'
            href=\'https://account.microsoft.com\'>Microsoft</a> account,
            you can select it for authenticating to the CILogon Service.</p>
            ';
        }

        echo '
      <div class="card text-center col-lg-6 offset-lg-3 col-md-8 offset-md-2 col-sm-10 offset-sm-1 mt-3">
        <h4 class="card-header">',
        ($useselectedidp ? 'Selected' : 'Select an'),
        ' Identity Provider</h4>
        <div class="card-body">
          <form action="', Util::getScriptDir(), '" method="post">
            <div class="form-group">
            <select name="providerId" id="providerId"
                autofocus="autofocus"
                class="selectpicker mw-100"
                data-size="20" data-width="fit"
                data-live-search="true"
                data-live-search-placeholder="Type to search"
                data-live-search-normalize="true">

                <option data-tokens="', $providerId , '" value="',
                $providerId , '" selected="selected">',
                Util::htmlent($idps[$providerId]['Display_Name']), '</option>

            </select>
            <a href="#" tabindex="0" data-trigger="hover click"
            class="helpcursor"
            data-toggle="popover" data-html="true"
            title="Selecting an Identity Provider"
            data-content="', $selecthelp, '"><i class="fa
            fa-question-circle"></i></a>
            </div> <!-- end div form-group -->
            ';

        if ($showremember) {
            $rememberhelp = 'Check this box to bypass the welcome page on ' .
                'subsequent visits and proceed directly to the selected ' .
                'identity provider. You will need to clear your browser\'s ' .
                'cookies to return here.';
            echo '
            <div class="form-group">
              <div class="form-check">
                <input class="form-check-input" type="checkbox"
                id="keepidp" name="keepidp" ',
                ((strlen($keepidp) > 0) ? 'checked="checked" ' : ''), ' />
                <label class="form-check-label"
                for="keepidp">Remember this selection</label>
                <a href="#" tabindex="0" data-trigger="hover click"
                class="helpcursor"
                data-toggle="popover" data-html="true"
                data-content="', $rememberhelp, '"><i class="fa
                fa-question-circle"></i></a>
              </div> <!-- end div form-check -->
            </div> <!-- end div form-group -->
            ';
        }

        echo Util::getCsrf()->hiddenFormElement();
        echo Util::getSkin()->hiddenFormElement();
        // Also need a hidden <input> element for "idphint" query parameter
        // since it is needed by Ajax 'GET' of the /idplist endpoint.
        if (!empty($idphintlist)) {
            echo '<input type="hidden" name="idphintlist" id="idphintlist"',
                 ' value="';
            $numhints = count($idphintlist);
            for ($i = 0; $i < $numhints; $i++) {
                echo urlencode($idphintlist[$i]);
                if ($i < ($numhints - 1)) {
                    echo ',';
                }
            }
            echo '" />';
        }
        echo '<input type="hidden" name="previouspage" value="WAYF" />';
        $lobtext = static::getLogOnButtonText();

        echo '
            <div class="form-group">
              <div class="form-row align-items-center justify-content-center">
                <div class="col-auto">
                  <input type="submit" name="submit"
                  class="btn btn-primary submit"
                  value="', $lobtext , '" id="wayflogonbutton" />
                </div> <!-- end col-auto -->
        ';

        $wayfcancelbutton = Util::getSkin()->getConfigOption('wayfcancelbutton');
        if (
            ($showcancel) ||
            ((!is_null($wayfcancelbutton)) && ((int)$wayfcancelbutton == 1))
        ) {
            echo '
                <div class="col-auto">
                  <input type="submit" name="submit"
                  class="btn btn-primary submit"
                  title="Cancel authentication."
                  value="Cancel" id="wayfcancelbutton" />
                </div>
            ';
        }

        echo '
              </div> <!-- end div form-row align-items-center -->
            </div> <!-- end div form-group -->
        ';

        $logonerror = Util::getSessionVar('logonerror');
        Util::unsetSessionVar('logonerror');
        if (strlen($logonerror) > 0) {
            echo '<div class="alert alert-danger" role="alert">', $logonerror, '</div>';
        }

        echo '
        <p class="privacypolicy">
        By selecting "', $lobtext , '", you agree to <a target="_blank"
        href="https://www.cilogon.org/privacy">CILogon\'s privacy
        policy</a>.
        </p>

          </form>

        </div> <!-- End card-body -->
      </div> <!-- End card -->
        ';
    }

    /**
     * printGetCertificate
     *
     * This function prints the 'Get New Certificate' box on the main page.
     * If the 'p12' PHP session variable is valid, it is read and a link for
     * the usercred.p12 file is presented to the user.
     */
    public static function printGetCertificate()
    {
        // CIL-624 If DISABLE_X509 is true, then don't even print out the
        // Get New Certificate box.
        if ((defined('DISABLE_X509')) && (DISABLE_X509 === true)) {
            return;
        }

        // Check if PKCS12 downloading is disabled. If so, print out message.
        $disabledmsg = '';
        $disabledbyconf = ((!defined('MYPROXY_LOGON')) || (empty(MYPROXY_LOGON)));
        $skin = Util::getSkin();
        $pkcs12disabled = $skin->getConfigOption('pkcs12', 'disabled');
        $disabledbyskin = ((!is_null($pkcs12disabled)) && ((int)$pkcs12disabled == 1));
        $dn = Util::getSessionVar('distinguished_name'); // Did we get user attributes for certs?
        $isEduGAINAndGetCert = Util::isEduGAINAndGetCert();

        if ($disabledbyconf || $disabledbyskin || (strlen($dn) == 0) || $isEduGAINAndGetCert) {
            if ($disabledbyconf) {
                $disabledmsg = 'Downloading PKCS12 certificates is disabled.';
            } elseif ($disabledbyskin) {
                $disabledmsg = $skin->getConfigOption('pkcs12', 'disabledmessage');
                if (!is_null($disabledmsg)) {
                    $disabledmsg = trim(html_entity_decode($disabledmsg));
                }
                if (strlen($disabledmsg) == 0) {
                    $disabledmsg = 'Downloading PKCS12 certificates is ' .
                        'restricted. Please try another method or log on ' .
                        'with a different Identity Provider.';
                }
            } elseif (strlen($dn) == 0) {
                $disabledmsg = 'Unable to generate a certificate. ' .
                    'Your identity provider has not provided CILogon ' .
                    'with all required information.';
            } elseif ($isEduGAINAndGetCert) {
                $disabledmsg = 'Unable to generate a certificate. ' .
                    'Your identity provider has not asserted support ' .
                    'for "Research and Scholarship" and "SIRTFI".';
            }

            echo '<div class="alert alert-danger" role="alert">';
            echo $disabledmsg;
            echo '</div>';
        } else { // PKCS12 downloading is okay
            $p12linktext = "Left-click this link to import the certificate " .
                "into your broswer / operating system. (Firefox users see " .
                "the FAQ.) Right-click this link and select 'Save As...' to " .
                "save the certificate to your desktop.";
            $passwordtext1 = 'Enter a password of at least 12 characters to protect your certificate.';
            $passwordtext2 = 'Re-enter your password for verification.';

            // Get the 'p12' session variable, which contains the time until
            // the "Download Certificate" link expires concatenated with the
            // download link (separated by a space). If either the time or
            // the link is blank, or the time is 0:00, then do not show the
            // link/time and unset the 'p12' session variable.
            $p12expire = '';
            $p12link = '';
            $p12linkisactive = false;
            $p12 = Util::getSessionVar('p12');
            if (preg_match('/([^\s]*)\s(.*)/', $p12, $match)) {
                $p12expire = $match[1];
                $p12link = $match[2];
            }

            if (
                (strlen($p12expire) > 0) &&
                (strlen($p12link) > 0) &&
                ($p12expire > 0) &&
                ($p12expire >= time())
            ) {
                $p12linkisactive = true;
                $expire = $p12expire - time();
                $minutes = floor($expire % 3600 / 60);
                $seconds = $expire % 60;
                $p12expire = 'Link Expires: ' .
                    sprintf("%02dm:%02ds", $minutes, $seconds);
                $p12link = '<a class="btn btn-primary" title="' .
                    $p12linktext . '" href="' . $p12link .
                    '">Download Your Certificate</a>';
            } else {
                $p12expire = '';
                $p12link = '';
                Util::unsetSessionVar('p12');
            }

            $p12lifetime = Util::getSessionVar('p12lifetime');
            if ((strlen($p12lifetime) == 0) || ($p12lifetime == 0)) {
                $p12lifetime = Util::getCookieVar('p12lifetime');
            }
            $p12multiplier = Util::getSessionVar('p12multiplier');
            if ((strlen($p12multiplier) == 0) || ($p12multiplier == 0)) {
                $p12multiplier = Util::getCookieVar('p12multiplier');
            }

            // Try to read the skin's intiallifetime if not yet set
            if ((strlen($p12lifetime) == 0) || ($p12lifetime <= 0)) {
                // See if the skin specified an initial value
                $skinlife = $skin->getConfigOption('pkcs12', 'initiallifetime', 'number');
                $skinmult = $skin->getConfigOption('pkcs12', 'initiallifetime', 'multiplier');
                if (
                    (!is_null($skinlife)) && (!is_null($skinmult)) &&
                    ((int)$skinlife > 0) && ((int)$skinmult > 0)
                ) {
                    $p12lifetime = (int)$skinlife;
                    $p12multiplier = (int)$skinmult;
                } else {
                    $p12lifetime = 13;      // Default to 13 months
                    $p12multiplier = 732;
                }
            }
            if ((strlen($p12multiplier) == 0) || ($p12multiplier <= 0)) {
                $p12multiplier = 732;   // Default to months
                if ($p12lifetime > 13) {
                    $p12lifetime = 13;
                }
            }

            // Make sure lifetime is within [minlifetime,maxlifetime]
            list($minlifetime, $maxlifetime) =
                Util::getMinMaxLifetimes('pkcs12', 9516);
            if (($p12lifetime * $p12multiplier) < $minlifetime) {
                $p12lifetime = $minlifetime;
                $p12multiplier = 1; // In hours
            } elseif (($p12lifetime * $p12multiplier) > $maxlifetime) {
                $p12lifetime = $maxlifetime;
                $p12multiplier = 1; // In hours
            }

            $lifetimetext = "Certificate lifetime is between $minlifetime " .
                "and $maxlifetime hours" .
                (($maxlifetime > 732) ?
                " ( = " . round(($maxlifetime / 732), 2) . " months)." : "."
                );

            $p12error = Util::getSessionVar('p12error');
            $expandcreatecert = (int)$skin->getConfigOption('expandcreatecert');

            static::printCollapseBegin(
                'gencert',
                'Create Password-Protected Certificate',
                !($p12linkisactive || (strlen($p12error) > 0) || $expandcreatecert)
            );

            echo '
          <div class="card-body col-lg-6 offset-lg-3 col-md-8 offset-md-2 col-sm-10 offset-sm-1">';

            static::printFormHead();

            if (strlen($p12error) > 0) {
                echo '<div class="alert alert-danger alert-dismissable fade show" role="alert">';
                echo $p12error;
                echo '
                      <button type="button" class="close" data-dismiss="alert"
                      aria-label="Close"><span aria-hidden="true">&times;</span>
                      </button>
                  </div>';
                Util::unsetSessionVar('p12error');
            }

            echo '
            <div class="form-group">
              <label for="password1">Enter Password</label>
              <div class="form-row">
                <div class="col-11">
                  <input type="password" name="password1" id="password1"
                  minlength="12" required="required"
                  autocomplete="new-password"
                  class="form-control" aria-describedby="password1help"
                  onkeyup="checkPassword()"/>
                  <div class="invalid-tooltip">
                    Please enter a password of at least 12 characters.
                  </div>
                </div>
                <div class="col">
                  <i id="pw1icon" class="fa fa-fw"></i>
                </div>
              </div>
              <div class="form-row">
                <div class="col-11">
                  <small id="password1help" class="form-text text-muted">',
                  $passwordtext1, '
                  </small>
                </div>
              </div>
            </div> <!-- end form-group -->

            <div class="form-group">
              <label for="password2">Confirm Password</label>
              <div class="form-row">
                <div class="col-11">
                  <input type="password" name="password2" id="password2"
                  minlength="12" required="required"
                  autocomplete="new-password"
                  class="form-control" aria-describedby="password2help"
                  onkeyup="checkPassword()"/>
                  <div class="invalid-tooltip">
                    Please ensure entered passwords match.
                  </div>
                </div>
                <div class="col">
                  <i id="pw2icon" class="fa fa-fw"></i>
                </div>
              </div>
              <div class="form-row">
                <div class="col-11">
                  <small id="password2help" class="form-text text-muted">',
                  $passwordtext2, '
                  </small>
                </div>
              </div>
            </div> <!-- end form-group -->

            <div class="form-row p12certificatelifetime">
              <div class="form-group col-8">
              <label for="p12lifetime">Lifetime</label>
                <input type="number" name="p12lifetime" id="p12lifetime" ',
                'value="', $p12lifetime, '" min="', $minlifetime, '" max="',
                $maxlifetime, '" class="form-control" required="required"
                aria-describedby="lifetime1help" />
                <div class="invalid-tooltip">
                  Please enter a valid lifetime for the certificate.
                </div>
                <small id="lifetime1help" class="form-text text-muted">',
                $lifetimetext, '
                </small>
              </div>
              <div class="form-group col-4">
                <label for="p12multiplier">&nbsp;</label>
                <select id="p12multiplier" name="p12multiplier"
                class="form-control">
                  <option value="1"',
                    (($p12multiplier == 1) ? ' selected="selected"' : ''),
                    '>hours</option>
                  <option value="24"',
                    (($p12multiplier == 24) ? ' selected="selected"' : ''),
                    '>days</option>
                  <option value="732"',
                    (($p12multiplier == 732) ? ' selected="selected"' : ''),
                    '>months</option>
                </select>
              </div>
            </div> <!-- end form-row -->

            <div class="form-group">
              <div class="form-row align-items-center">
                <div class="col text-center">
                  <input type="submit" name="submit"
                  class="btn btn-primary submit"
                  value="Get New Certificate" onclick="showHourglass(\'p12\')"/>
                  <div class="spinner-border"
                  style="width: 32px; height: 32px;"
                  role="status" id="p12hourglass">
                    <span class="sr-only">Generating...</span>
                  </div> <!-- spinner-border -->
                </div>
              </div>
            </div>

            <div class="form-group">
              <div class="form-row align-items-center">
                <div class="col text-center" id="p12value">
                ', $p12link, '
                </div>
              </div>
              <div class="form-row align-items-center">
                <div class="col text-center" id="p12expire">
                ', $p12expire, '
                </div>
              </div>
            </div>
            </form>
          </div> <!-- end card-body -->';
            static::printCollapseEnd();
        }
    }

    /**
     * printCertInfo
     *
     * This function prints information related to the X.509 certificate
     * such as DN (distinguished name) and LOA (level of assurance).
     */
    public static function printCertInfo()
    {
        // CIL-624 If DISABLE_X509 is true, then don't even print out the
        // Certificate Information box.
        if ((defined('DISABLE_X509')) && (DISABLE_X509 === true)) {
            return;
        }

        $dn = Util::getSessionVar('distinguished_name');
        static::printCollapseBegin('certinfo', 'Certificate Information');
        if (strlen($dn) > 0) {
            // Strip off the email address from the pseudo-DN.
            $dn = static::reformatDN(preg_replace('/\s+email=.+$/', '', $dn));
            echo '
              <div class="card-body">
                <table class="table table-striped table-sm">
                <tbody>
                  <tr>
                    <th>Certificate Subject:</th>
                    <td>', Util::htmlent($dn), '</td>
                  </tr>
                  <tr>
                    <th>Identity Provider:</th>
                    <td>', Util::getSessionVar('idp_display_name'), '</td>
                  </tr>
                  <tr>
                    <th><a target="_blank"
                      href="http://ca.cilogon.org/loa">Level of Assurance:</a></th>
                      <td>
            ';

            if (Util::getSessionVar('loa') == 'openid') {
                echo '<a href="http://ca.cilogon.org/policy/openid"
                      target="_blank">OpenID</a>';
            } elseif (Util::isLOASilver()) {
                echo '<a href="http://ca.cilogon.org/policy/silver"
                      target="_blank">Silver</a>';
            } else {
                echo '<a href="http://ca.cilogon.org/policy/basic"
                      target="_blank">Basic</a>';
            }

            echo '</td>
                  </tr>
                  </tbody>
                </table>';
        } else { // No DN? Show missing name(s) or email address.
            echo '
              <div class="card-body">
            ';
            static::printErrorBox(
                '
                <div class="card-text my-2">
                  Unable to generate a certificate. Your identity provider
                  has not provided CILogon with all required information.
                </div> <!-- end card-text -->'
            );
            $first_name   = Util::getSessionVar('first_name');
            $last_name    = Util::getSessionVar('last_name');
            $display_name = Util::getSessionVar('display_name');
            $email        = Util::getSessionVar('email');
            echo '
                <table class="table table-striped table-sm">
                <tbody>';
            if ((strlen($first_name) == 0) && (strlen($display_name) == 0)) {
                echo '
                  <tr>
                    <th class="w-50">First Name:</th>
                    <td>MISSING</td>
                  </tr>';
            }
            if ((strlen($last_name) == 0) && (strlen($display_name) == 0)) {
                echo '
                  <tr>
                    <th class="w-50">Last Name:</th>
                    <td>MISSING</td>
                  </tr>';
            }
            if (
                (strlen($display_name) == 0) &&
                ((strlen($first_name) == 0) || (strlen($last_name) == 0))
            ) {
                echo '
                  <tr>
                    <th class="w-50">Display Name:</th>
                    <td>MISSING</td>
                  </tr>';
            }
            $emailvalid = filter_var($email, FILTER_VALIDATE_EMAIL);
            if ((strlen($email) == 0) || (!$emailvalid)) {
                echo '
                  <tr>
                    <th class="w-50">Email Address:</th>
                    <td>' , ((strlen($email) == 0) ? 'MISSING' : 'INVALID') , '</td>
                  </tr>';
            }
            $idp     = Util::getSessionVar('idp');
            if (Util::isEduGAINAndGetCert()) {
                $idplist = Util::getIdpList();
                if (!$idplist->isREFEDSRandS($idp)) {
                    echo '
                      <tr>
                        <th class="w-50"><a target="_blank"
                        href="http://refeds.org/category/research-and-scholarship">Research
                        and Scholarship</a>:</th>
                        <td>MISSING</td>
                      </tr>';
                }
                if (!$idplist->isSIRTFI($idp)) {
                    echo '
                      <tr>
                        <th class="w-50"><a target="_blank"
                        href="https://refeds.org/sirtfi">SIRTFI</a>:</th>
                        <td>MISSING</td>
                      </tr>';
                }
            }
            echo '
                  </tbody>
                </table>';
        }
        echo '
              </div> <!-- end card-body -->';
        static::printCollapseEnd();
    }

    /**
     * printUserAttributes
     *
     * This function shows the user the attributes released by their
     * selected IdP and saved in the PHP session.
     */
    public static function printUserAttributes()
    {
        $idplist = Util::getIdpList();
        $idp = Util::getSessionVar('idp');
        $samlidp = ((!empty($idp)) && (!$idplist->isOAuth2($idp)));

        // Set various booleans for warning/error messages early so that we
        // can display a "general" warning/error icon on the card title.
        $warnings = array();
        $errors = array();

        // CIL-416 Show warning for missing ePPN
        if (($samlidp) && (empty(Util::getSessionVar('eppn')))) {
            if (empty(Util::getSessionVar('eptid'))) {
                $errors['no_eppn_or_eptid'] = true;
            } else {
                $warnings['no_eppn'] = true;
            }
        }

        if (empty($idp)) {
            $errors['no_entityID'] = true;
        } else {
            if ((!$samlidp) && (empty(Util::getSessionVar('oidc')))) {
                $errors['no_oidc'] = true;
            }
        }

        if ((empty(Util::getSessionVar('first_name'))) && (empty(Util::getSessionVar('display_name')))) {
            $errors['no_first_name'] = true;
        }

        if ((empty(Util::getSessionVar('last_name'))) && (empty(Util::getSessionVar('display_name')))) {
            $errors['no_last_name'] = true;
        }

        if (
            (empty(Util::getSessionVar('display_name'))) &&
            ((empty(Util::getSessionVar('first_name'))) ||
            (empty(Util::getSessionVar('last_name'))))
        ) {
            $errors['no_display_name'] = true;
        }

        $emailvalid = filter_var(Util::getSessionVar('email'), FILTER_VALIDATE_EMAIL);
        if ((empty(Util::getSessionVar('email'))) || (!$emailvalid)) {
            $errors['no_valid_email'] = true;
        }

        static::printCollapseBegin(
            'userattrs',
            'User Attributes ' .
            (
                (!empty($errors)) ? static::getIcon(
                    'fa-exclamation-circle',
                    'red',
                    'One or more missing attributes.'
                ) : ((@$warnings['no_eppn']) ?  static::getIcon(
                    'fa-exclamation-triangle',
                    'gold',
                    'Some CILogon clients (e.g., Globus) require ePPN.'
                ) : '')
            )
        );

        echo '
          <div class="card-body">
            <table class="table table-striped table-sm">
            <tbody>';

        // CIL-781 Show CILogon User Identifier (user_uid) when logged in
        $user_uid = Util::getSessionVar('user_uid');
        if (strlen($user_uid) > 0) {
            echo '
              <tr>
                <th>CILogon User Identifier:</th>
                <td>', $user_uid, '</td>
                <td> </td>
              </tr>';
        }

        echo '
              <tr>
                <th>Identity Provider (entityID):</th>
                <td>', $idp , '</td>
                <td>';

        if (@$errors['no_entityID']) {
            echo static::getIcon(
                'fa-exclamation-circle',
                'red',
                'Missing the entityID of the IdP.'
            );
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>ePTID:</th>
                <td>', Util::getSessionVar('eptid'), '</td>
                <td>';

        if (@$errors['no_eppn_or_eptid']) {
            echo static::getIcon(
                'fa-exclamation-circle',
                'red',
                'Must have either ePPN -OR- ePTID.'
            );
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>ePPN:</th>
                <td>', Util::getSessionVar('eppn'), '</td>
                <td>';

        if (@$errors['no_eppn_or_eptid']) {
            echo static::getIcon(
                'fa-exclamation-circle',
                'red',
                'Must have either ePPN -OR- ePTID.'
            );
        } elseif (@$warnings['no_eppn']) {
            echo static::getIcon(
                'fa-exclamation-triangle',
                'gold',
                'Some CILogon clients (e.g., Globus) require ePPN.'
            );
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>OpenID:</th>
                <td>', Util::getSessionVar('oidc'), '</td>
                <td>';

        if (@$errors['no_oidc']) {
            echo static::getIcon(
                'fa-exclamation-circle',
                'red',
                'Missing the OpenID identifier.'
            );
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>First Name (givenName):</th>
                <td>', Util::getSessionVar('first_name'), '</td>
                <td>';

        if (@$errors['no_first_name']) {
            echo static::getIcon(
                'fa-exclamation-circle',
                'red',
                'Must have either givenName + sn -OR- displayName.'
            );
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>Last Name (sn):</th>
                <td>', Util::getSessionVar('last_name'), '</td>
                <td>';

        if (@$errors['no_last_name']) {
            echo static::getIcon(
                'fa-exclamation-circle',
                'red',
                'Must have either givenName + sn -OR- displayName.'
            );
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>Display Name (displayName):</th>
                <td>', Util::getSessionVar('display_name'), '</td>
                <td>';

        if (@$errors['no_display_name']) {
            echo static::getIcon(
                'fa-exclamation-circle',
                'red',
                'Must have either displayName -OR- givenName + sn.'
            );
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>Email Address (email):</th>
                <td>', Util::getSessionVar('email'), '</td>
                <td>';

        if (@$errors['no_valid_email']) {
            echo static::getIcon(
                'fa-exclamation-circle',
                'red',
                'Missing valid email address.'
            );
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>Level of Assurance (assurance):</th>
                <td>', Util::getSessionVar('loa'), '</td>
                <td> </td>
              </tr>

              <tr>
                <th>AuthnContextClassRef:</th>
                <td>', Util::getSessionVar('acr'), '</td>
                <td> </td>
              </tr>

              <tr>
                <th>AuthnMethodRef:</th>
                <td>', Util::getSessionVar('amr'), '</td>
                <td> </td>
              </tr>

              <tr>
                <th>Affiliation (affiliation):</th>
                <td>', Util::getSessionVar('affiliation'), '</td>
                <td> </td>
              </tr>

              <tr>
                <th>Entitlement (entitlement):</th>
                <td>', Util::getSessionVar('entitlement'), '</td>
                <td> </td>
              </tr>

              <tr>
                <th>Organizational Unit (ou):</th>
                <td>', Util::getSessionVar('ou'), '</td>
                <td> </td>
              </tr>

              <tr>
                <th>Member (member):</th>
                <td>', Util::getSessionVar('member_of'), '</td>
                <td> </td>
              </tr>

              <tr>
                <th>iTrustUIN (itrustuin):</th>
                <td>', Util::getSessionVar('itrustuin'), '</td>
                <td> </td>
              </tr>

              <tr>
                <th>Subject ID (subject-id):</th>
                <td>', Util::getSessionVar('subject_id'), '</td>
                <td> </td>
              </tr>

              <tr>
                <th>Pairwise ID (pairwise-id):</th>
                <td>', Util::getSessionVar('pairwise_id'), '</td>
                <td> </td>
              </tr>

              <tr>
                <th>Preferred Username:</th>
                <td>', Util::getSessionVar('preferred_username'), '</td>
                <td> </td>
              </tr>
              </tbody>
            </table>
          </div> <!-- end card-body -->';
        static::printCollapseEnd();
    }

    /**
     * printIdPMetadata
     *
     * This function shows the metadata associated with the IdP saved to
     * the PHP session.
     */
    public static function printIdPMetadata()
    {
        $idplist = Util::getIdpList();
        $idp = Util::getSessionVar('idp');
        $samlidp = ((!empty($idp)) && (!$idplist->isOAuth2($idp)));
        $shibarray = $idplist->getShibInfo($idp);

        // CIL-416 Check for eduGAIN IdPs without both REFEDS R&S and SIRTFI
        // since these IdPs are not allowed to get certificates.
        $eduGainWithoutRandSandSIRTFI = 0;
        if (
            ($samlidp) &&
            (!$idplist->isRegisteredByInCommon($idp)) &&
            ((!$idplist->isREFEDSRandS($idp)) ||
             (!$idplist->isSIRTFI($idp)))
        ) {
            $eduGainWithoutRandSandSIRTFI = 1;
        }

        static::printCollapseBegin(
            'idpmeta',
            'Identity Provider Attributes ' .
            (
                // CIL-416 Show warning for missing ePPN
                ($eduGainWithoutRandSandSIRTFI) ?
                static::getIcon(
                    'fa-exclamation-triangle',
                    'gold',
                    'This IdP does not support both ' .
                    'REFEDS R&amp;S and SIRTFI. CILogon ' .
                    'functionality may be limited.'
                ) : ''
            )
        );

        echo'
          <div class="card-body">
            <table class="table table-striped table-sm">
            <tbody>
              <tr>
                <th>Organization Name:</th>
                <td>', @$shibarray['Organization Name'] , '</td>
                <td>';

        if (empty(@$shibarray['Organization Name'])) {
            echo static::getIcon(
                'fa-exclamation-circle',
                'red',
                'Could not find ' .
                '&lt;OrganizationDisplayName&gt; in metadata.'
            );
        }

        echo '
                </td>
              </tr>
              <tr>
                <th>Home Page:</th>
                <td><a target="_blank" href="', @$shibarray['Home Page'] , '">',
                @$shibarray['Home Page'] , '</a></td>
                <td> </td>
              </tr>

              <tr>
                <th>Support Contact:</th>';
        if (
            (!empty(@$shibarray['Support Name'])) ||
            (!empty(@$shibarray['Support Address']))
        ) {
            echo '
                <td>', @$shibarray['Support Name'] , ' &lt;',
                        preg_replace('/^mailto:/', '', @$shibarray['Support Address']), '&gt;</td>
                <td> </td>';
        }
        echo '
              </tr>

        ';

        if ($samlidp) {
            echo '
              <tr>
                <th>Technical Contact:</th>';
            if (
                (!empty(@$shibarray['Technical Name'])) ||
                (!empty(@$shibarray['Technical Address']))
            ) {
                echo '
                <td>', @$shibarray['Technical Name'] , ' &lt;',
                        preg_replace('/^mailto:/', '', @$shibarray['Technical Address']), '&gt;</td>
                <td> </td>';
            }
            echo '
              </tr>

              <tr>
                <th>Administrative Contact:</th>';
            if (
                (!empty(@$shibarray['Administrative Name'])) ||
                (!empty(@$shibarray['Administrative Address']))
            ) {
                echo '
                <td>', @$shibarray['Administrative Name'] , ' &lt;',
                        preg_replace('/^mailto:/', '', @$shibarray['Administrative Address']), '&gt;</td>
                <td> </td>';
            }
            echo '
              </tr>

              <tr>
                <th>Registered by InCommon:</th>
                <td>', ($idplist->isRegisteredByInCommon($idp) ? 'Yes' : 'No'), '</td>
                <td> </td>
              </tr>

              <tr>
                <th><a style="text-decoration:underline" target="_blank"
                href="http://refeds.org/category/research-and-scholarship">REFEDS
                R &amp; S</a>:</th>
                <td>', ($idplist->isREFEDSRandS($idp) ? 'Yes' : 'No'), '</td>
                <td>';

            if (
                ($eduGainWithoutRandSandSIRTFI &&
                !$idplist->isREFEDSRandS($idp))
            ) {
                echo static::getIcon(
                    'fa-exclamation-triangle',
                    'gold',
                    'This IdP does not support both ' .
                    'REFEDS R&amp;S and SIRTFI. ' .
                    'CILogon functionality may be limited.'
                );
            }

            echo '
                </td>
              </tr>

              <tr>
                <th><a style="text-decoration:underline" target="_blank"
                       href="https://refeds.org/sirtfi">SIRTFI</a>:</th>
                <td>', ($idplist->isSIRTFI($idp) ? 'Yes' : 'No'), '</td>
                <td>';

            if (
                ($eduGainWithoutRandSandSIRTFI &&
                !$idplist->isSIRTFI($idp))
            ) {
                echo static::getIcon(
                    'fa-exclamation-triangle',
                    'gold',
                    'This IdP does not support both ' .
                    'REFEDS R&amp;S and SIRTFI. ' .
                    'CILogon functionality may be limited.'
                );
            }

            echo '
                </td>
              </tr>

              <tr>
                <th>Entity ID</th>
                <td><a style="text-decoration:underline" target="_blank"
                href="https://met.refeds.org/met/entity/',
                rawurlencode($idp),
                '">', $idp, '</a></td>
                <td> </td>
              </tr>
            ';
        } // end if ($samlidp)

            echo '
              </tbody>
            </table>
          </div> <!-- end card-body -->';
        static::printCollapseEnd();
    }

    /**
     * getIcon
     *
     * This function returns the HTML for the Font Awesome icons which can
     * appear inline with other information.  This is accomplished via the
     * use of wrapping the image in a <span> tag.
     *
     * @param string $icon The Font Awesome icon to be shown.
     * @param string $color The HMTL color for the icon.
     * @param string $help (Optionals) The popup 'title' help text to be
     *        displayed when the mouse cursor hovers over the icon.
     *        Defaults to empty string.
     * @return string HTML for the icon block to output.
     */
    public static function getIcon($icon, $color, $help = '')
    {
        return '<span style="color: ' . $color . ';
            -webkit-text-stroke-width: 1px;
            -webkit-text-stroke-color: gray;">' .
            ((strlen($help) > 0) ? '<span data-trigger="hover" ' .
            'data-toggle="popover" data-html="true" ' .
            'data-content="' . $help . '">' : '') .
            '<i class="fa nocollapse ' . $icon . '"></i>' .
            ((strlen($help) > 0) ? '</span>' : '') .
            '</span>';
    }

    /**
     * printCollapseBegin
     *
     * This function prints the preamble for a collapsible Bootstrap Card.
     *
     * @param string $name The name to give to the collapse elements which
     *        should be unique among all collapse elements on the page.
     * @param string $title The text for the card-header.
     * @param bool $collapsed (optional) If true, then start with the card
     *        collapsed. If false, start with the card opened.
     */
    public static function printCollapseBegin($name, $title, $collapsed = true)
    {
        echo '
      <div class="card col-sm-10 offset-sm-1">
        <h5 class="card-header text-center">
          <a class="d-block',
            ($collapsed ? ' collapsed' : ''),
            '" data-toggle="collapse"
            href="#collapse-', $name, '" aria-expanded="',
            ($collapsed ? 'false' : "true"),
            '" aria-controls="collapse-', $name, '"
            id="heading-', $name, '">
            <i class="fa fa-chevron-down pull-right"></i>
            ', $title, '
          </a>
        </h5>
        <div id="collapse-',$name, '" class="collapse',
        ($collapsed ? '' : ' show'),
        '" aria-labelledby="heading-', $name , '">';
    }

    /**
     * printCollapseEnd
     *
     * This function prints the closing block corresponding to the
     * printCollapseBegin.
     */
    public static function printCollapseEnd()
    {
        echo '
        </div> <!-- end collapse-... -->
      </div> <!-- end card -->
        ';
    }

    /**
     * printErrorBox
     *
     * This function prints out a bordered box with an error icon and any
     * passed-in error HTML text.  The error icon and text are output to
     * a <table> so as to keep the icon to the left of the error text.
     *
     * @param string $errortext HTML error text to be output
     */
    public static function printErrorBox($errortext)
    {
        echo '
        <div class="alert alert-danger" role="alert">
          <div class="row">
            <div class="col-1 align-self-center text-center">
            ', static::getIcon('fa-exclamation-circle fa-2x', 'red'),'
            </div>
            <div class="col">
            ', $errortext , '
            </div>
          </div>
        </div>
        ';
    }

    /**
     * printNoScript
     *
     * This function prints the <NoScript> block which is displayed if the
     * user's browser does not have JavaScript enabled.
     */
    public static function printNoScript()
    {
        echo'
      <noscript>
        <div class="alert alert-danger alert-dismissible" role="alert">
          <span><strong>Notice: </strong> JavaScript is not enabled.
          The CILogon Service requires JavaScript for functionality.
          <a target="_blank" href="https://enable-javascript.com/"
          class="alert-link">Please Enable JavaScript</a>.</span>
        </div>
      </noscript>
        ';
    }

    /**
     * printLogOff
     *
     * This function prints the Log Of boxes at the bottom of the main page.
     */
    public static function printLogOff()
    {
        $logofftext = 'End your CILogon session and return to the ' .
           'front page. Note that this will not log you out at ' .
            Util::getSessionVar('idp_display_name') . '.';

        static::printFormHead();
        echo '
          <div class="form-group mt-3">
            <div class="form-row align-items-center">
              <div class="col text-center">
              ';

        $logofftextbox = Util::getSkin()->getConfigOption('logofftextbox');
        if ((!is_null($logofftextbox)) && ((int)$logofftextbox == 1)) {
            echo '  <div class="btn btn-primary">To log off,
                please quit your browser.</div>';
        } else {
            echo '  <input type="submit" name="submit"
                class="btn btn-primary submit"
                title="', $logofftext , '" value="Log Off" />';
        }

        echo '
              </div> <!-- end col-auto -->
            </div> <!-- end form-row align-items-center -->
          </div> <!-- end form-group -->
        </form>
        ';
    }

    /**
     * printGeneralErrorPage
     *
     * This is a convenience method called by handleGotUser to print out
     * a general error page to the user.
     *
     * @param string $redirect The url for the <form> element
     * @param string $redirectform Additional hidden input fields for the
     *        <form>.
     */
    public static function printGeneralErrorPage($redirect, $redirectform)
    {
        Util::unsetAllUserSessionVars();

        static::printHeader('Error Logging On');
        static::printCollapseBegin(
            'attributeerror',
            'General Error',
            false
        );

        echo '
              <div class="card-body px-5">';

        static::printErrorBox('An error has occurred. This may be a temporary
            error. Please try again later, or contact us at the the email
            address at the bottom of the page.');

        static::printFormHead($redirect, 'get');

        echo '
              <div class="card-text my-2">
                <div class="form-group">
                  <div class="form-row align-items-center
                  justify-content-center">
                    <div class="col-auto">
                      ', $redirectform, '
                      <input type="submit" name="submit"
                      class="btn btn-primary submit form-control"
                      value="Proceed" />
                    </div>
                  </div> <!-- end form-row align-items-center -->
                </div> <!-- end form-group -->
              </div> <!-- end card-text -->
            </form>
            </div> <!-- end card-body -->';

        static::printCollapseEnd();
        static::printFooter();
    }

    /**
     * printSAMLAttributeReleaseErrorPage
     *
     * This is a convenience method called by handleGotUser to print out
     * the attribute release error page for SAML IdPs. This can occur when
     * not all attributes were released by the IdP, or when the IdP is an
     * eduGAIN IdP without both R&S and SIRTFI, and the user was trying to
     * get a certificate.
     *
     * @param string $eppn
     * @param string $eptid
     * @param string $first_name
     * @param string $last_name
     * @param string $display_name
     * @param string $email
     * @param string $idp
     * @param string $idp_display_name
     * @param string $affiliation
     * @param string $ou
     * @param string $member_of
     * @param string $acr
     * @param string $amr
     * @param string $entitlement
     * @param string $itrustuin
     * @param string $subject_id
     * @param string $pairwise_id
     * @param string $preferred_username
     * @param string $clientparams
     * @param string $redirect The url for the <form> element
     * @param string $redirectform Additional hidden input fields for the
     *        <form>.
     * @param bool   $edugainandgetcert Is the IdP in eduGAIN without both
     *        R&S and SIRTIF, and the user could get a certificate?
     */
    public static function printSAMLAttributeReleaseErrorPage(
        $eppn,
        $eptid,
        $first_name,
        $last_name,
        $display_name,
        $email,
        $idp,
        $idp_display_name,
        $affiliation,
        $ou,
        $member_of,
        $acr,
        $amr,
        $entitlement,
        $itrustuin,
        $subject_id,
        $pairwise_id,
        $preferred_username,
        $clientparams,
        $redirect,
        $redirectform,
        $edugainandgetcert
    ) {
        Util::unsetAllUserSessionVars();

        static::printHeader('Error Logging On');
        static::printCollapseBegin(
            'attributeerror',
            'Attribute Release Error',
            false
        );

        echo '
              <div class="card-body px-5">
        ';

        $errorboxstr = '
                <div class="card-text my-2">
                  There was a problem logging on. Your identity provider
                  has not provided CILogon with required information.
                </div> <!-- end card-text -->
                <dl class="row">';

        $missingattrs = '';
        // Show user which attributes are missing
        if ((strlen($eppn) == 0) && (strlen($eptid) == 0)) {
            $errorboxstr .= '
                <dt class="col-sm-3">ePTID:</dt>
                <dd class="col-sm-9">MISSING</dd>
                <dt class="col-sm-3">ePPN:</dt>
                <dd class="col-sm-9">MISSING</dd>';
            $missingattrs .= '%0D%0A    eduPersonPrincipalName' .
                             '%0D%0A    eduPersonTargetedID ';
        }
        if ((strlen($first_name) == 0) && (strlen($display_name) == 0)) {
            $errorboxstr .= '
                <dt class="col-sm-3">First Name:</dt>
                <dd class="col-sm-9">MISSING</dd>';
            $missingattrs .= '%0D%0A    givenName (first name)';
        }
        if ((strlen($last_name) == 0) && (strlen($display_name) == 0)) {
            $errorboxstr .= '
                <dt class="col-sm-3">Last Name:</dt>
                <dd class="col-sm-9">MISSING</dd>';
            $missingattrs .= '%0D%0A    sn (last name)';
        }
        if (
            (strlen($display_name) == 0) &&
            ((strlen($first_name) == 0) || (strlen($last_name) == 0))
        ) {
            $errorboxstr .= '
                <dt class="col-sm-3">Display Name:</dt>
                <dd class="col-sm-9">MISSING</dd>';
            $missingattrs .= '%0D%0A    displayName';
        }
        $emailvalid = filter_var($email, FILTER_VALIDATE_EMAIL);
        if ((strlen($email) == 0) || (!$emailvalid)) {
            $errorboxstr .= '
                <dt class="col-sm-3">Email Address:</dt>
                <dd class="col-sm-9">' .
            ((strlen($email) == 0) ? 'MISSING' : 'INVALID') . '</dd>';
            $missingattrs .= '%0D%0A    mail (email address)';
        }
        // CIL-326/CIL-539 - For eduGAIN IdPs attempting to get a cert,
        // print out missing R&S and SIRTFI values
        $idplist = Util::getIdpList();
        if ($edugainandgetcert) {
            if (!$idplist->isREFEDSRandS($idp)) {
                $errorboxstr .= '
                    <dt class="col-sm-3"><a target="_blank"
                    href="http://refeds.org/category/research-and-scholarship">Research
                    and Scholarship</a>:</dt>
                    <dd class="col-sm-9">MISSING</dd>';
                $missingattrs .= '%0D%0A    http://refeds.org/category/research-and-scholarship';
            }
            if (!$idplist->isSIRTFI($idp)) {
                $errorboxstr .= '
                    <dt class="col-sm-3"><a target="_blank"
                    href="https://refeds.org/sirtfi">SIRTFI</a>:</dt>
                    <dd class="col-sm-9">MISSING</dd>';
                $missingattrs .= '%0D%0A    http://refeds.org/sirtfi';
            }
        }
        $student = false;
        $errorboxstr .= '</dl>';

        static::printErrorBox($errorboxstr);

        if (
            (strlen($email) == 0) &&
            (preg_match('/student@/', $affiliation))
        ) {
            $student = true;
            echo '
                <div class="card-text my-2">
                  <strong>If you are a student</strong>
                  you may need to ask your identity provider
                  to release your email address.
                </div> <!-- end card-text -->
            ';
        }

        // Attempt to get the OAuth1/OIDC client name
        $portalname = Util::getSessionVar('portalname');
        if (strlen($portalname) == 0) {
            $portalname = @$clientparams['client_name'];
        }

        // Get contacts from metadata for email addresses
        $shibarray = $idplist->getShibInfo($idp);
        $emailmsg = '?subject=Attribute Release Problem for CILogon' .
        '&cc=' . EMAIL_HELP .
        '&body=Hello, I am having trouble logging on to ' .
        'https://' . DEFAULT_HOSTNAME . '/ using the ' . $idp_display_name .
        ' Identity Provider (IdP) ' .
        ((strlen($portalname) > 0) ? 'with ' . strip_tags($portalname) . ' ' : '') .
        'due to the following missing attributes:%0D%0A' .
        $missingattrs;
        if ($student) {
            $emailmsg .= '%0D%0A%0D%0ANote that my account is ' .
            'marked "student" and thus my email address may need ' .
            'to be released.';
        }
        $emailmsg .= '%0D%0A%0D%0APlease see ' .
            'http://www.cilogon.org/service/addidp for more ' .
            'details. Thank you for any help you can provide.';
        echo '
                <div class="card-text my-2">
                  Contact your identity provider to let them know you are
                  having having a problem logging on to CILogon.
                </div> <!-- end card-text -->
                <ul>
            ';

        $addrfound = false;
        $name = @$shibarray['Support Name'];
        $addr = @$shibarray['Support Address'];
        $addr = preg_replace('/^mailto:/', '', $addr);

        if (strlen($addr) > 0) {
            $addrfound = true;
            if (strlen($name) == 0) { // Use address if no name given
                $name = $addr;
            }
            echo '
                  <li> Support Contact: ' ,
                  $name , ' <a class="btn btn-primary" href="mailto:' ,
                  $addr , $emailmsg , '">' ,
                  $addr , '</a>
                  </li>';
        }

        if (!$addrfound) {
            $name = @$shibarray['Technical Name'];
            $addr = @$shibarray['Technical Address'];
            $addr = preg_replace('/^mailto:/', '', $addr);
            if (strlen($addr) > 0) {
                $addrfound = true;
                if (strlen($name) == 0) { // Use address if no name given
                    $name = $addr;
                }
                echo '
                      <li> Technical Contact: ' ,
                      $name , ' <a class="btn btn-primary" href="mailto:' ,
                      $addr , $emailmsg , '">' ,
                      $addr , '</a>
                      </li>';
            }
        }

        if (!$addrfound) {
            $name = @$shibarray['Administrative Name'];
            $addr = @$shibarray['Administrative Address'];
            $addr = preg_replace('/^mailto:/', '', $addr);
            if (strlen($addr) > 0) {
                if (strlen($name) == 0) { // Use address if no name given
                    $name = $addr;
                }
                echo '
                      <li>Administrative Contact: ' ,
                      $name , ' <a class="btn btn-primary" href="mailto:' ,
                      $addr , $emailmsg , '">' ,
                      $addr , '</a>
                      </li>';
            }
        }

        echo '
                </ul>
                <div class="card-text my-2">
                  Alternatively, you can contact us at the email address
                  at the bottom of the page.
                </div> <!-- end card-text -->
            ';

        static::printFormHead($redirect, 'get');

        echo '
              <div class="card-text my-2">
                <div class="form-group">
                  <div class="form-row align-items-center
                  justify-content-center">
                    <div class="col-auto">
                      ', $redirectform, '
                      <input type="submit" name="submit"
                      class="btn btn-primary submit form-control"
                      value="Proceed" />
                    </div>
                  </div> <!-- end form-row align-items-center -->
                </div> <!-- end form-group -->
              </div> <!-- end card-text -->
            </form>
            </div> <!-- end card-body -->';

        static::printCollapseEnd();
        static::printFooter();
    }

    /**
     * printOAuth2AttributeReleaseErrorPage
     *
     * This function is called by handleGotUser when the IdP did not release
     * all required attributes for the user. In the case of the OAuth2
     * providers, this is typically due to one of first name, last name,
     * and/or email address. Print out a special message for each OAuth2 IdP
     * to let the user know how to fix the issue.
     *
     * @param string $idp_display_name The name of the OAuth2 IdP.
     * @param string $redirect The url for the <form> element
     * @param string $redirectform Additional hidden input fields for the
     *        <form>.
     *
     */
    public static function printOAuth2AttributeReleaseErrorPage($idp_display_name, $redirect, $redirectform)
    {
        Util::unsetAllUserSessionVars();
        static::printHeader('Error Logging On');
        static::printCollapseBegin(
            'oauth2attrerror',
            'Error Logging On',
            false
        );

        echo '
            <div class="card-body px-5">';

        static::printErrorBox('There was a problem logging on.');

        if ($idp_display_name == 'Google') {
            echo '
              <div class="card-text my-2">
                There was a problem logging on. It appears that you have
                attempted to use Google as your identity provider, but your
                name or email address was missing. To rectify this problem,
                go to the <a target="_blank"
                href="https://myaccount.google.com/privacy#personalinfo">Google
                Account Personal Information page</a>, and enter your first
                name, last name, and email address. (All other Google
                account information is not required by the CILogon Service.)
              </div>
              <div class="card-text my-2">
                After you have updated your Google account profile, click
                the "Proceed" button below and attempt to log on
                with your Google account again. If you have any questions,
                please contact us at the email address at the bottom of the
                page.
              </div>';
        } elseif ($idp_display_name == 'GitHub') {
            echo '
              <div class="card-text my-2">
                There was a problem logging on. It appears that you have
                attempted to use GitHub as your identity provider, but your
                name or email address was missing. To rectify this problem,
                go to the <a target="_blank"
                href="https://github.com/settings/profile">GitHub
                Public Profile page</a>, and enter your name and email
                address. (All other GitHub account information is not
                required by the CILogon Service.)
              </div>
              <div class="card-text my-2">
                After you have updated your GitHub account profile, click
                the "Proceed" button below and attempt to log on
                with your GitHub account again. If you have any questions,
                please contact us at the email address at the bottom of the
                page.
              </div>';
        } elseif ($idp_display_name == 'ORCID') {
            echo '
              <div class="card-text my-2">
                There was a problem logging on. It appears that you have
                attempted to use ORCID as your identity provider, but your
                name or email address was missing. To rectify this problem,
                go to your <a target="_blank"
                href="https://orcid.org/my-orcid">ORCID
                Profile page</a>, enter your name and email address, and
                make sure they can be viewed by Everyone.
                (All other ORCID account information is not required by
                the CILogon Service.)
              </div>
              <div class="card-text my-2">
                After you have updated your ORCID account profile, click
                the "Proceed" button below and attempt to log on
                with your ORCID account again. If you have any questions,
                please contact us at the email address at the bottom of the
                page.
              </div>';
        } elseif ($idp_display_name == 'Microsoft') {
            echo '
              <div class="card-text my-2">
                There was a problem logging on. It appears that you have
                attempted to use Microsoft as your identity provider, but your
                name or email address was missing. To rectify this problem,
                go to your <a target="_blank"
                href="https://account.microsoft.com">Microsoft
                Account page</a>, and enter your name and email address.
                (All other Microsfot account information is not required by
                the CILogon Service.)
              </div>
              <div class="card-text my-2">
                After you have updated your Microsoft account profile, click
                the "Proceed" button below and attempt to log on
                with your Microsoft account again. If you have any questions,
                please contact us at the email address at the bottom of the
                page.
              </div>';
        }

        static::printFormHead($redirect, 'get');

        echo '
              <div class="card-text my-2">
                <div class="form-group">
                  <div class="form-row align-items-center
                  justify-content-center">
                    <div class="col-auto">
                      <input type="hidden" name="providerId"
                      value="' ,
                      Util::getAuthzUrl($idp_display_name) , '" />
                      ', $redirectform, '
                      <input type="submit" name="submit"
                      class="btn btn-primary submit form-control"
                      value="Proceed" />
                    </div>
                  </div> <!-- end form-row align-items-center -->
                </div> <!-- end form-group -->
              </div> <!-- end card-text -->
            </form>
            </div> <!-- end card-body -->';

        static::printCollapseEnd();
        static::printFooter();
    }

    /**
     * handleLogOnButtonClicked
     *
     * This function is called when the user clicks the 'Log On' button
     * on the IdP selection page. It checks to see if the 'Remember this
     * selection' checkbox was checked and sets a cookie appropriately. It
     * also sets a cookie 'providerId' so the last chosen IdP will be
     * selected the next time the user visits the site. The function then
     * calls the appropriate 'redirectTo...' function to send the user
     * to the chosen IdP.
     */
    public static function handleLogOnButtonClicked()
    {
        // Get the list of currently available IdPs
        $idps = static::getCompositeIdPList();

        // Set the cookie for keepidp if the checkbox was checked
        $pc = new PortalCookie();
        Util::setPortalOrCookieVar(
            $pc,
            'keepidp',
            ((strlen(Util::getPostVar('keepidp')) > 0) ? 'checked' : '')
        );

        // Get the user-chosen IdP from the posted form
        $providerId = Util::getPostVar('providerId');
        $providerIdValid = ((strlen($providerId) > 0) &&
                            (isset($idps[$providerId])));

        // Set the cookie for the last chosen IdP and redirect to it if in list
        Util::setPortalOrCookieVar(
            $pc,
            'providerId',
            ($providerIdValid ? $providerId : ''),
            true
        );
        if ($providerIdValid) {
            $providerName = Util::getAuthzIdP($providerId);
            if (in_array($providerName, Util::$oauth2idps)) {
                // Log in with an OAuth2 IdP
                static::redirectToGetOAuth2User($providerId);
            } else { // Use InCommon authn
                static::redirectToGetShibUser($providerId);
            }
        } else { // IdP not in list, or no IdP selected
            Util::setSessionVar('logonerror', 'Please select a valid IdP.');
            printLogonPage();
        }
    }

    /**
     * handleNoSubmitButtonClicked
     *
     * This function is the 'default' case when no 'submit' button has been
     * clicked, or if the submit session variable is not set. It checks
     * to see if either the <forceinitialidp> option is set, or if the
     * 'Remember this selection' checkbox was previously checked. If so,
     * then rediret to the appropriate IdP. Otherwise, print the main
     * Log On page.
     */
    public static function handleNoSubmitButtonClicked()
    {
        $providerId = '';
        $keepidp = '';
        $selected_idp = '';
        $redirect_uri = '';
        $client_id = '';
        $callbackuri = Util::getSessionVar('callbackuri');
        $readidpcookies = true;  // Assume config options are not set
        $skin = Util::getSkin();
        $forceinitialidp = (int)$skin->getConfigOption('forceinitialidp');
        $initialidp = (string)$skin->getConfigOption('initialidp');

        // If this is a OIDC transaction, get the redirect_uri and
        // client_id parameters from the session var clientparams.
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        if (isset($clientparams['redirect_uri'])) {
            $redirect_uri = $clientparams['redirect_uri'];
        }
        if (isset($clientparams['client_id'])) {
            $client_id = $clientparams['client_id'];
        }

        // Use the first element of the idphint list as the selected_idp.
        $idphintlist = static::getIdphintList();
        if (!empty($idphintlist)) {
            $selected_idp = $idphintlist[0];
        }

        if ((strlen($redirect_uri) > 0) || (strlen($client_id) > 0)) {
            // CIL-431 - If the OAuth2/OIDC $redirect_uri or $client_id is set,
            // then check for a match in the BYPASS_IDP_ARRAY or
            // ciloa2.bypass database table (where 'type'='idp') to see if
            // we should automatically redirect to a specific IdP. Used
            // mainly by campus gateways.
            $bypassidp = '';
            foreach (Util::getBypass()->getBypassIdPArray() as $key => $value) {
                if (
                    (preg_match($key, $redirect_uri)) ||
                    (preg_match($key, $client_id))
                ) {
                    $bypassidp = $value;
                    // CIL-837 Reset the 'skin' to unset green/red-lit IdPs
                    $skin->init(true);
                    break;
                }
            }

            // CIL-613 - Next, check for a match in the ALLOW_BYPASS_ARRAY.
            // If found, then allow the idphint/selected_idp to be used as the
            // IdP to redirect to.
            if ((empty($bypassidp)) && (!empty($selected_idp))) {
                foreach (Util::getBypass()->getAllowBypassArray() as $key => $value) {
                    if (
                        (preg_match($key, $redirect_uri)) ||
                        (preg_match($key, $client_id))
                    ) {
                        $bypassidp = $selected_idp;
                        break;
                    }
                }
            }

            if (!empty($bypassidp)) { // Match found!
                $providerId = $bypassidp;
                $keepidp = 'checked';
                // To skip the next code blocks, unset a few variables.
                $forceinitialidp = 0;     // Skip checking this option
                $selected_idp = '';       // Skip any passed-in option
                $readidpcookies = false;  // Don't read in the IdP cookies
            }
        }

        // If the <forceinitialidp> option is set, use either the
        // <initialidp> or the selected_idp as the providerId, and use
        // <forceinitialidp> as keepIdp. Otherwise, read the cookies
        // 'providerId' and 'keepidp'.
        if (
            ($forceinitialidp == 1) &&
            ((strlen($initialidp) > 0) || (strlen($selected_idp) > 0))
        ) {
            // If the <allowforceinitialidp> option is set, then make sure
            // the callback / redirect uri is in the portal list.
            $afii = $skin->getConfigOption('portallistaction', 'allowforceinitialidp');
            if (
                (is_null($afii)) || // Option not set, no need to check portal list
                (((int)$afii == 1) &&
                  (($skin->inPortalList($redirect_uri)) ||
                   ($skin->inPortalList($client_id)) ||
                   ($skin->inPortalList($callbackuri))))
            ) {
                // 'selected_idp' takes precedence over <initialidp>
                if (strlen($selected_idp) > 0) {
                    $providerId = $selected_idp;
                } else {
                    $providerId = $initialidp;
                }
                $keepidp = $forceinitialidp;
                $readidpcookies = false; // Don't read in the IdP cookies
            }
        }

        // <initialidp> options not set, or portal not in portal list?
        // Get idp and 'Remember this selection' from cookies instead.
        $pc = new PortalCookie();
        $pn = $pc->getPortalName();
        if ($readidpcookies) {
            // Check the portalcookie first, then the 'normal' cookies
            if (strlen($pn) > 0) {
                $keepidp    = $pc->get('keepidp');
                $providerId = $pc->get('providerId');
            } else {
                $keepidp    = Util::getCookieVar('keepidp');
                $providerId = Util::getCookieVar('providerId');
            }
        }

        // CIL-1023 If OIDC prompt=consent parameter is given, then ignore
        // all methods for bypassing the "Select an IdP" page so that the
        // "consent to release attributes" section is always displayed.
        if (isset($clientparams['prompt'])) {
            $promptarr = explode(' ', $clientparams['prompt']);
            if (in_array('consent', $promptarr)) {
                $providerId = '';
            }
        }

        // If both 'keepidp' and 'providerId' were set (and the
        // providerId is a greenlit IdP or valid OpenID provider),
        // then skip the Logon page and proceed to the appropriate
        // getuser script.
        if ((strlen($providerId) > 0) && (strlen($keepidp) > 0)) {
            // If selected_idp was specified at the OIDC authorize endpoint,
            // make sure that it matches the saved providerId. If not,
            // then show the Logon page and uncheck the keepidp checkbox.
            if ((strlen($selected_idp) == 0) || ($selected_idp == $providerId)) {
                Util::setPortalOrCookieVar($pc, 'providerId', $providerId, true);
                $providerName = Util::getAuthzIdP($providerId);
                if (in_array($providerName, Util::$oauth2idps)) {
                    // Log in with an OAuth2 IdP
                    static::redirectToGetOAuth2User($providerId);
                } elseif (Util::getIdpList()->exists($providerId)) {
                    // Log in with InCommon
                    static::redirectToGetShibUser($providerId);
                } else { // $providerId not greenlit
                    Util::setPortalOrCookieVar($pc, 'providerId', '', true);
                    printLogonPage();
                }
            } else { // selected_idp does not match saved providerId
                Util::setPortalOrCookieVar($pc, 'keepidp', '', true);
                printLogonPage();
            }
        } else { // One of providerId or keepidp was not set
            printLogonPage();
        }
    }

    /**
     * verifyCurrentUserSession
     *
     * This function verifies the contents of the PHP session.  It checks
     * the following:
     * (1) The persistent store 'user_uid', the Identity Provider 'idp',
     *     the IdP Display Name 'idp_display_name', and the 'status'
     *     (of getUser()) are all non-empty strings.
     * (2) The 'status' (of getUser()) is even (i.e. STATUS_OK).
     * (3) If $providerId is passed-in, it must match 'idp'.
     * If all checks are good, then this function returns true.
     *
     * @param string $providerId (Optional) The user-selected Identity
     *        Provider. If set, make sure $providerId matches the PHP
     *        session variable 'idp'.
     * @return bool True if the contents of the PHP session ar valid.
     *              False otherwise.
     */
    public static function verifyCurrentUserSession($providerId = '')
    {
        $retval = false;

        $idp       = Util::getSessionVar('idp');
        $idp_display_name   = Util::getSessionVar('idp_display_name');
        $user_uid  = Util::getSessionVar('user_uid');
        $status    = Util::getSessionVar('status');
        $authntime = Util::getSessionVar('authntime');

        // CIL-410 When using the /testidp/ flow, the 'storeattributes'
        // session var is set. In this case, the only attribute that
        // is needed is 'idp' (entityID).
        if (Util::getSessionVar('storeattributes') == '1') {
            if (strlen($idp) > 0) {
                $retval = true;
            }
        } elseif (
            (strlen($user_uid) > 0) && (strlen($idp) > 0) &&
            (strlen($idp_display_name) > 0) && (strlen($status) > 0) &&
            (strlen($authntime) > 0) &&
            (!($status & 1)) // All STATUS_OK codes are even
        ) {
            // If $providerId is passed in, make sure it matches the $idp
            if ((strlen($providerId) == 0) || ($providerId == $idp)) {
                $retval = true;
                Util::getSkin()->init(); // Does the IdP need a forced skin?
            }
        }

        return $retval;
    }

    /**
     * redirectToGetShibUser
     *
     * This method redirects control flow to the getuser script for
     * If the first parameter (a greenlit entityId) is not specified,
     * we check to see if either the providerId PHP session variable or the
     * providerId cookie is set (in that order) and use one if available.
     * The function then checks to see if there is a valid PHP session
     * and if the providerId matches the 'idp' in the session.  If so, then
     * we don't need to redirect to '/secure/getuser/' and instead we
     * we display the main page.  However, if the PHP session is not valid,
     * then this function redirects to the '/secure/getuser/' script so as
     * to do a Shibboleth authentication via mod_shib. When the providerId
     * is non-empty, the SessionInitiator will automatically go to that IdP
     * (i.e. without stopping at a WAYF).  This function also sets
     * several PHP session variables that are needed by the getuser script,
     * including the 'responsesubmit' variable which is set as the return
     * 'submit' variable in the 'getuser' script.
     *
     * @param string $providerId (Optional) An entityId of the
     *        authenticating IdP. If not specified (or set to the empty
     *        string), we check providerId PHP session variable and
     *        providerId cookie (in that order) for non-empty values.
     * @param string $responsesubmit (Optional) The value of the PHP session
     *       'submit' variable to be set upon return from the 'getuser'
     *        script.  This is utilized to control the flow of this script
     *        after 'getuser'. Defaults to 'gotuser'.
     * @param string $responseurl (Optional) A response url for redirection
     *        after successful processing at /secure/getuser/. Defaults to
     *        the current script directory.
     */
    public static function redirectToGetShibUser(
        $providerId = '',
        $responsesubmit = 'gotuser',
        $responseurl = null
    ) {
        // If providerId not set, try the cookie value
        if (strlen($providerId) == 0) {
            $providerId = Util::getPortalOrCookieVar('providerId');
        }

        // If the user has a valid 'user_uid' in the PHP session, and the
        // providerId matches the 'idp' in the PHP session, then
        // simply go to the main page.
        if (static::verifyCurrentUserSession($providerId)) {
            printMainPage();
        } else { // Otherwise, redirect to the getuser script
            // Set PHP session varilables needed by the getuser script
            Util::setSessionVar(
                'responseurl',
                (is_null($responseurl) ?
                    Util::getScriptDir(true) : $responseurl)
            );
            Util::setSessionVar('submit', 'getuser');
            Util::setSessionVar('responsesubmit', $responsesubmit);
            Util::getCsrf()->setCookieAndSession();

            // Set up the 'header' string for redirection thru mod_shib
            $mhn = static::getMachineHostname($providerId);
            $redirect = "Location: https://$mhn/Shibboleth.sso/Login?target=" .
                urlencode("https://$mhn/secure/getuser/");

            if (strlen($providerId) > 0) {
                // Use special NIHLogin Shibboleth SessionInitiator for acsByIndex
                if ($providerId == 'urn:mace:incommon:nih.gov') {
                    $redirect = preg_replace(
                        '%/Shibboleth.sso/Login%',
                        '/Shibboleth.sso/NIHLogin',
                        $redirect
                    );
                }

                $redirect .= '&providerId=' . urlencode($providerId);

                // To bypass SSO at IdP, check for session var 'forceauthn' == 1
                $forceauthn = Util::getSessionVar('forceauthn');
                Util::unsetSessionVar('forceauthn');
                if ($forceauthn) {
                    $redirect .= '&forceAuthn=true';
                } elseif (strlen($forceauthn) == 0) {
                    // 'forceauth' was not set to '0' in the session, so
                    // check the skin's option instead.
                    $forceauthn = Util::getSkin()->getConfigOption('forceauthn');
                    if ((!is_null($forceauthn)) && ((int)$forceauthn == 1)) {
                        $redirect .= '&forceAuthn=true';
                    }
                }
            }

            $log = new Loggit();
            $log->info('Shibboleth Login="' . $redirect . '"');
            header($redirect);
            exit; // No further processing necessary
        }
    }

    /**
     * redirectToGetOAuth2User
     *
     * This method redirects control flow to the getuser script for
     * when the user logs in via OAuth 2.0. It first checks to see
     * if we have a valid session. If so, we don't need to redirect and
     * instead simply show the Get Certificate page. Otherwise, we start
     * an OAuth 2.0 logon by composing a parameterized GET URL using
     * the OAuth 2.0 endpoint.
     *
     * @param string $providerId (Optional) An entityId of the
     *        authenticating IdP. If not specified (or set to the empty
     *        string), we check providerId PHP session variable and
     *        providerId cookie (in that order) for non-empty values.
     * @param string $responsesubmit (Optional) The value of the PHP session
     *        'submit' variable to be set upon return from the 'getuser'
     *         script.  This is utilized to control the flow of this script
     *         after 'getuser'. Defaults to 'gotuser'.
     */
    public static function redirectToGetOAuth2User(
        $providerId = '',
        $responsesubmit = 'gotuser'
    ) {
        // If providerId not set, try the cookie value
        if (strlen($providerId) == 0) {
            $providerId = Util::getPortalOrCookieVar('providerId');
        }

        // If the user has a valid 'user_uid' in the PHP session, and the
        // providerId matches the 'idp' in the PHP session, then
        // simply go to the 'Download Certificate' button page.
        if (static::verifyCurrentUserSession($providerId)) {
            printMainPage();
        } else { // Otherwise, redirect to the OAuth 2.0 endpoint
            // Set PHP session varilables needed by the getuser script
            Util::unsetSessionVar('logonerror');
            Util::setSessionVar('responseurl', Util::getScriptDir(true));
            Util::setSessionVar('submit', 'getuser');
            Util::setSessionVar('responsesubmit', $responsesubmit);
            $csrf = Util::getCsrf();
            $csrf->setCookieAndSession();
            $extraparams = array();
            $extraparams['state'] = $csrf->getTokenValue();

            // To bypass SSO at IdP, check for session var 'forceauthn' == 1
            $forceauthn = Util::getSessionVar('forceauthn');
            Util::unsetSessionVar('forceauthn');
            if ($forceauthn) {
                $extraparams['approval_prompt'] = 'force';
            } elseif (strlen($forceauthn) == 0) {
                // 'forceauth' was not set to '0' in the session, so
                // check the skin's option instead.
                $forceauthn = Util::getSkin()->getConfigOption('forceauthn');
                if ((!is_null($forceauthn)) && ((int)$forceauthn == 1)) {
                    $extraparams['approval_prompt'] = 'force';
                }
            }

            // Get the provider name based on the provider authz URL
            $providerName = Util::getAuthzIdP($providerId);

            // Get the authz URL and redirect
            $oauth2 = new OAuth2Provider($providerName);
            if (is_null($oauth2->provider)) {
                Util::setSessionVar('logonerror', 'Invalid Identity Provider.');
                printLogonPage();
            } else {
                $authUrl = $oauth2->provider->getAuthorizationUrl(
                    array_merge(
                        $oauth2->authzUrlOpts,
                        $extraparams
                    )
                );
                header('Location: ' . $authUrl);
                exit; // No further processing necessary
            }
        }
    }

    /**
     * handleGotUser
     *
     * This function is called upon return from one of the getuser scripts
     * which should have set the 'user_uid' and 'status' PHP session variables.
     * It verifies that the status return is one of STATUS_OK (even
     * values).  If not, we print an error message to the user.
     */
    public static function handleGotUser()
    {
        $log = new Loggit();
        $user_uid = Util::getSessionVar('user_uid');
        $status = Util::getSessionVar('status');

        // We must get and unset session vars BEFORE any HTML output since
        // a redirect may go to another site, meaning we need to update
        // the session cookie before we leave the cilogon.org domain.
        //
        // This bit of trickery sets local variables from the PHP session
        // that was just populated, using the names in the $user_attrs array.
        foreach (DBService::$user_attrs as $value) {
            $$value = Util::getSessionVar($value);
        }
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        $failureuri   = Util::getSessionVar('failureuri');
        $dn           = Util::getSessionVar('distinguished_name');

        // CIL-410 The /testidp/ flow is indicated by the presence of the
        // 'storeattributes' PHP session var. In this case, simply show
        // the main testidp page with user and IdP attributes.
        if (!empty(Util::getSessionVar('storeattributes'))) {
            printMainPage();
            return;
        }

        // Check for OIDC redirect_uri or OAuth 1.0a failureuri.
        // If found, set 'Proceed' button redirect appropriately.
        $redirect = '';
        $redirectform = '';
        // First, check for OIDC redirect_uri, with parameters in <form>
        if (isset($clientparams['redirect_uri'])) {
            $redirect = $clientparams['redirect_uri'];
            $redirectform = '<input type="hidden" name="error" value="access_denied" />' .
                '<input type="hidden" name="error_description" value="Missing attributes" />';
            if (isset($clientparams['state'])) {
                $redirectform .= '<input type="hidden" name="state" value="' .
                    $clientparams['state'] . '" />';
            }
        }

        // Next, check for OAuth 1.0a
        if ((strlen($redirect) == 0) && (strlen($failureuri) > 0)) {
            $redirect = $failureuri . "?reason=missing_attributes";
        }

        // For the 'Create Password-Protected Certificate' flow, we now
        // allow users to log in even if not all attributes are given. So
        // the check for isEduGAINandGetCert happens later for X509 certs.
        // Here we just check for OAuth1/OAuth2/OIDC flow and
        // eduGAIN getcert restriction.
        $isEduGAINAndGetCert = false;
        if (
            (strlen($failureuri) > 0) ||                      // OAuth 1.0a
            (strlen(Util::getSessionVar('clientparams')) > 0) // OIDC
        ) {
            $isEduGAINAndGetCert = Util::isEduGAINAndGetCert();
        }

        // Was this an OAuth 1.0a transaction but the distinguished_name
        // could not be calculated? Set $missingparam below.
        $oauth1withoutdn = ((strlen($failureuri) > 0) && (strlen($dn) == 0));

        // Check for various error conditions and print out appropriate page
        if (
            (strlen($user_uid) == 0) || // Empty user_uid
            (strlen($status) == 0) ||   // Empty status
            ($status & 1) ||            // Odd-numbered status = error
            ($isEduGAINAndGetCert) ||   // Not allowed
            ($oauth1withoutdn)          // OAuth1.0a needs DN for cert
        ) {
            $log->error(
                'Failed to getuser' .
                ($isEduGAINAndGetCert ? ' due to eduGAIN IdP restriction.' : '.')
            );

            // Is this a SAML IdP?
            $idplist = Util::getIdpList();
            $samlidp = ((!empty($idp)) && (!$idplist->isOAuth2($idp)));

            // Was there a misssing parameter?
            $missingparam = (($status ==
                DBService::$STATUS['STATUS_MISSING_PARAMETER_ERROR']) ||
                    $oauth1withoutdn);

            if (($isEduGAINAndGetCert) || ($missingparam && $samlidp)) {
                static::printSAMLAttributeReleaseErrorPage(
                    $eppn,
                    $eptid,
                    $first_name,
                    $last_name,
                    $display_name,
                    $email,
                    $idp,
                    $idp_display_name,
                    $affiliation,
                    $ou,
                    $member_of,
                    $acr,
                    $amr,
                    $entitlement,
                    $itrustuin,
                    $subject_id,
                    $pairwise_id,
                    $preferred_username,
                    $clientparams,
                    $redirect,
                    $redirectform,
                    $isEduGAINAndGetCert
                );
            } elseif ($missingparam && (!$samlidp)) { // OAuth2 IdP
                static::printOAuth2AttributeReleaseErrorPage(
                    $idp_display_name,
                    $redirect,
                    $redirectform
                );
            } else { // General error
                static::printGeneralErrorPage($redirect, $redirectform);
            }
        } else { // EVERYTHING IS OKAY SO FAR
            // Extra security check: Once the user has successfully
            // authenticated with an IdP, verify that the chosen IdP was
            // actually greenlit. If not, then set error message and show
            // Select an Identity Provider page again.
            Util::getSkin()->init();  // Check for forced skin
            $idps = static::getCompositeIdPList();
            $providerId = Util::getSessionVar('idp');
            if ((strlen($providerId) > 0) && (!isset($idps[$providerId]))) {
                Util::setSessionVar(
                    'logonerror',
                    'Invalid IdP selected. Please try again.'
                );
                $log->warn('Authentication attempt using non-greenlit IdP');
                // CIL-1098 Don't send email alerts for IdP-generated errors
                /*
                Util::sendErrorAlert(
                    'Authentication attempt using non-greenlit IdP',
                    '
A user successfully authenticated with an IdP, however, the selected IdP
was not in the list of greenlit IdPs as determined by the current skin.
This might indicate the user attempted to circumvent the security check
in "handleGotUser()" for valid IdPs for the skin.'
                );
                */
                Util::unsetCookieVar('providerId');
                Util::unsetUserSessionVars();
                printLogonPage();
            } else { // Got user successfully
                static::gotUserSuccess();
            }
        }
    }

    /**
     * gotUserSuccess
     *
     * This function is called after the user has been successfully
     * authenticated. If the 'status' session variable is STATUS_OK
     * then it checks if we have a new or changed user and logs
     * that appropriately. It then continues to the MainPage.
     */
    public static function gotUserSuccess()
    {
        $log = new Loggit();
        $status = Util::getSessionVar('status');

        // If this is the first time the user has used the CILogon Service,
        // and the flow is OAuth-based, send an alert if the name contains
        // any HTML entities.
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        $callbackuri = Util::getSessionVar('callbackuri');

        // Log new users with possibly empty distinguished_name values
        if ($status == DBService::$STATUS['STATUS_NEW_USER']) {
            $dn = Util::getSessionVar('distinguished_name');
            $log->info('New User' . ((strlen($dn) == 0) ? ' without a distinguished_name.' : '.'));
            // If HTML entities are in the distinguished_name, send an alert.
            if (
                (strlen($dn) > 0) &&
                ((strlen($callbackuri) > 0) ||
                 (isset($clientparams['code'])))
            ) {
                $dn = static::reformatDN(preg_replace('/\s+email=.+$/', '', $dn));
                $htmldn = Util::htmlent($dn);
                if (strcmp($dn, $htmldn) != 0) {
                    $log->warn('New user DN contains HTML entities: htmlentites(DN) = ' . $htmldn);
                    // CIL-1098 Don't send email alerts for IdP-generated errors
                    /*
                    Util::sendErrorAlert(
                        'New user DN contains HTML entities',
                        "htmlentites(DN) = $htmldn\n"
                    );
                    */
                }
            }
        } elseif ($status == DBService::$STATUS['STATUS_USER_UPDATED']) {
            $log->info('User IdP attributes changed.');
        }
        printMainPage();
    }

    /**
     * generateP12
     *
     * This function is called when the user clicks the 'Get New
     * Certificate' button. It first reads in the password fields and
     * verifies that they are valid (i.e. they are long enough and match).
     * Then it gets a credential from the MyProxy server and converts that
     * certificate into a PKCS12 which is written to disk.  If everything
     * succeeds, the temporary pkcs12 directory and lifetime is saved to
     * the 'p12' PHP session variable, which is read later when the Main
     * Page HTML is shown.
     */
    public static function generateP12()
    {
        $log = new Loggit();

        // Get the entered p12lifetime and p12multiplier and set the cookies
        list($minlifetime, $maxlifetime) =
            Util::getMinMaxLifetimes('pkcs12', 9516);
        $p12lifetime   = Util::getPostVar('p12lifetime');
        $p12multiplier = Util::getPostVar('p12multiplier');
        if (strlen($p12multiplier) == 0) {
            $p12multiplier = 1;  // For ECP, p12lifetime is in hours
        }
        $lifetime = $p12lifetime * $p12multiplier;
        if ($lifetime <= 0) { // In case user entered negative number
            $lifetime = $maxlifetime;
            $p12lifetime = $maxlifetime;
            $p12multiplier = 1;  // maxlifetime is in hours
        } elseif ($lifetime < $minlifetime) {
            $lifetime = $minlifetime;
            $p12lifetime = $minlifetime;
            $p12multiplier = 1;  // minlifetime is in hours
        } elseif ($lifetime > $maxlifetime) {
            $lifetime = $maxlifetime;
            $p12lifetime = $maxlifetime;
            $p12multiplier = 1;  // maxlifetime is in hours
        }
        Util::setCookieVar('p12lifetime', $p12lifetime);
        Util::setCookieVar('p12multiplier', $p12multiplier);
        Util::setSessionVar('p12lifetime', $p12lifetime);
        Util::setSessionVar('p12multiplier', $p12multiplier);

        // Verify that the password is at least 12 characters long
        $password1 = Util::getPostVar('password1');
        $password2 = Util::getPostVar('password2');
        $p12password = Util::getPostVar('p12password');  // For ECP clients
        if (strlen($p12password) > 0) {
            $password1 = $p12password;
            $password2 = $p12password;
        }
        if (strlen($password1) < 12) {
            Util::setSessionVar(
                'p12error',
                'Password must have at least 12 characters.'
            );
            return; // SHORT PASSWORD - NO FURTHER PROCESSING NEEDED!
        }

        // Verify that the two password entry fields matched
        if ($password1 != $password2) {
            Util::setSessionVar('p12error', 'Passwords did not match.');
            return; // MISMATCHED PASSWORDS - NO FURTHER PROCESSING NEEDED!
        }

        $dn = Util::getSessionVar('distinguished_name');
        if (strlen($dn) > 0) {
            // Append extra info, such as 'skin', to be processed by MyProxy
            $myproxyinfo = Util::getSessionVar('myproxyinfo');
            if (strlen($myproxyinfo) > 0) {
                $dn .= " $myproxyinfo";
            }
            // Attempt to fetch a credential from the MyProxy server
            $cert = MyProxy::getMyProxyCredential(
                $dn,
                '',
                MYPROXY_HOST,
                Util::getLOAPort(),
                $lifetime,
                MYPROXY_CLIENT_CRED,
                ''
            );

            // The 'openssl pkcs12' command is picky in that the private
            // key must appear BEFORE the public certificate. But MyProxy
            // returns the private key AFTER. So swap them around.
            $cert2 = '';
            if (
                preg_match(
                    '/-----BEGIN CERTIFICATE-----([^-]+)' .
                    '-----END CERTIFICATE-----[^-]*' .
                    '-----BEGIN RSA PRIVATE KEY-----([^-]+)' .
                    '-----END RSA PRIVATE KEY-----/',
                    $cert,
                    $match
                )
            ) {
                $cert2 = "-----BEGIN RSA PRIVATE KEY-----" .
                         $match[2] . "-----END RSA PRIVATE KEY-----\n" .
                         "-----BEGIN CERTIFICATE-----" .
                         $match[1] . "-----END CERTIFICATE-----";
            }

            if (strlen($cert2) > 0) { // Successfully got a certificate!
                // Create a temporary directory in DEFAULT_PKCS12_DIR
                $tdir = Util::tempDir(DEFAULT_PKCS12_DIR, '', 0770);
                $p12dir = str_replace(DEFAULT_PKCS12_DIR, '', $tdir);
                $p12file = $tdir . '/usercred.p12';

                // Call the openssl pkcs12 program to convert certificate
                exec('/bin/env ' .
                     'RANDFILE=/tmp/.rnd ' .
                     'CILOGON_PKCS12_PW=' . escapeshellarg($password1) . ' ' .
                     '/usr/bin/openssl pkcs12 -export ' .
                     '-passout env:CILOGON_PKCS12_PW ' .
                     "-out $p12file " .
                     '<<< ' . escapeshellarg($cert2));

                // Verify the usercred.p12 file was actually created
                $size = @filesize($p12file);
                if (($size !== false) && ($size > 0)) {
                    $p12link = 'https://' . static::getMachineHostname() .
                               '/pkcs12/' . $p12dir . '/usercred.p12';
                    $p12 = (time() + 300) . " " . $p12link;
                    Util::setSessionVar('p12', $p12);
                    $log->info('Generated New User Certificate="' . $p12link . '"');
                    //CIL-507 Special Log Message For XSEDE
                    $email = Util::getSessionVar('email');
                    $log->info("USAGE email=\"$email\" client=\"PKCS12\"");
                    Util::logXSEDEUsage('PKCS12', $email);
                } else { // Empty or missing usercred.p12 file - shouldn't happen!
                    Util::setSessionVar(
                        'p12error',
                        'Error creating certificate. Please try again.'
                    );
                    Util::deleteDir($tdir); // Remove the temporary directory
                    $log->error('Error creating certificate - missing usercred.p12');
                }
            } else { // The myproxy-logon command failed - shouldn't happen!
                Util::setSessionVar(
                    'p12error',
                    'Error! MyProxy unable to create certificate.'
                );
                $log->error('Error creating certificate - myproxy-logon failed');
            }
        } else { // Couldn't find the 'distinguished_name' PHP session value
            Util::setSessionVar(
                'p12error',
                'Cannot create certificate due to missing attributes.'
            );
            $log->error('Error creating certificate - missing dn session variable');
        }
    }

    /**
     * getLogOnButtonText
     *
     * This function checks the current skin to see if <logonbuttontext>
     * has been configured.  If so, it returns that value.  Otherwise,
     * it returns 'Log On'.
     *
     * @return string The text of the 'Log On' button for the WAYF, as
     *         configured for the skin.  Defaults to 'Log On'.
     */
    public static function getLogOnButtonText()
    {
        $retval = 'Log On';
        $lobt = Util::getSkin()->getConfigOption('logonbuttontext');
        if (!is_null($lobt)) {
            $retval = (string)$lobt;
        }
        return $retval;
    }

    /**
     * reformatDN
     *
     * This function takes in a certificate subject DN with the email=...
     * part already removed. It checks the skin to see if <dnformat> has
     * been set. If so, it reformats the DN appropriately.
     *
     * @param string $dn The certificate subject DN (without the email=... part)
     * @return string The certificate subject DN transformed according to
     *         the value of the <dnformat> skin config option.
     */
    public static function reformatDN($dn)
    {
        $newdn = $dn;
        $dnformat = (string)Util::getSkin()->getConfigOption('dnformat');
        if (strlen($dnformat) > 0) {
            if (
                ($dnformat == 'rfc2253') &&
                (preg_match(
                    '%/DC=(.*)/DC=(.*)/C=(.*)/O=(.*)/CN=(.*)%',
                    $dn,
                    $match
                ))
            ) {
                array_shift($match);
                $m = array_reverse(Net_LDAP2_Util::escape_dn_value($match));
                $newdn = "CN=$m[0],O=$m[1],C=$m[2],DC=$m[3],DC=$m[4]";
            }
        }
        return $newdn;
    }

    /**
     * getMachineHostname
     *
     * This function is utilized in the formation of the URL for the
     * PKCS12 credential download link and for the Shibboleth Single Sign-on
     * session initiator URL. It returns a host-specific URL
     * hostname by mapping the local machine hostname (as returned
     * by 'uname -n') to an InCommon metadata cilogon.org hostname
     * (e.g., polo2.cilogon.org). This function uses the HOSTNAME_ARRAY
     * where the keys are the local machine hostname and
     * the values are the external facing *.cilogon.org hostname.
     * In case the local machine hostname cannot be found in the
     * HOSTNAME_ARRAY, DEFAULT_HOSTNAME is returned.
     *
     * @param string $idp The entityID of the IdP used for potential
     *        special handling (e.g., for Syngenta).
     * @return string The full cilogon-specific hostname of this host.
     */
    public static function getMachineHostname($idp = '')
    {
        $retval = DEFAULT_HOSTNAME;
        // CIL-439/CIL-975 For Syngenta and other ADFS IdPs (like NSF),
        // use just a single 'hostname' value to match their
        // Active Directory configuration for CILogon's
        // assertionConsumerService URL. Otherwise, map the local
        // hostname to a polo*.cilogon.org domain name.
        if (
            (!defined('ADFS_IDP_ARRAY')) ||
            (!in_array($idp, ADFS_IDP_ARRAY))
        ) {
            $localhost = php_uname('n');
            if (array_key_exists($localhost, HOSTNAME_ARRAY)) {
                $retval = HOSTNAME_ARRAY[$localhost];
            }
        }
        return $retval;
    }

    /**
     * getCompositeIdPList
     *
     * This function generates a list of IdPs to display in the 'Select
     * An Identity Provider' box on the main CILogon page or on the
     * TestIdP page. For the main CILogon page, this is a filtered list of
     * IdPs based on the skin's greenlit/redlit list and the global
     * redlit list. For the TestIdP page, the list is all InCommon IdPs.
     *
     * @return array A two-dimensional array where the primary key is the
     *         entityID and the secondary key is either 'Display_Name'
     *         or 'Organization_Name'.
     */
    public static function getCompositeIdPList()
    {
        $retarray = array();

        $idplist = Util::getIdpList();
        $skin = Util::getSkin();

        // Check if the skin's config.xml has set the
        // 'registeredbyincommonidps' option, which restricts the SAML-
        // based IdPs to those with the <Registered_By_InCommon> tag.
        // Otherwise, just get all SAML-based IdPs that have not been
        // restricted based on the global 'redlit' list.
        $registeredbyincommonidps = $skin->getConfigOption('registeredbyincommonidps');
        if (
            (!is_null($registeredbyincommonidps)) &&
            ((int)$registeredbyincommonidps == 1)
        ) {
            $retarray = $idplist->getRegisteredByInCommonIdPs();
        } else {
            $retarray = $idplist->getSAMLIdPs();
        }

        // Add all OAuth2 IdPs to the list
        foreach (Util::$oauth2idps as $value) {
            // CIL-617 Show OAuth2 IdPs only if client_id is configured
            $client_id_def = strtoupper($value) . '_OAUTH2_CLIENT_ID';
            $client_id = constant($client_id_def);
            if ((defined($client_id_def)) && (!empty($client_id))) {
                $retarray[Util::getAuthzUrl($value)]['Organization_Name'] =
                    $value;
                $retarray[Util::getAuthzUrl($value)]['Display_Name'] =
                    $value;
            }
        }

        // Check to see if the skin's config.xml has a greenlit list of IDPs.
        // If so, go thru master IdP list and keep only those IdPs.
        if ($skin->hasGreenlitIdps()) {
            foreach ($retarray as $entityId => $names) {
                if (!$skin->idpGreenlit($entityId)) {
                    unset($retarray[$entityId]);
                }
            }
        }
        // Next, check to see if the skin's config.xml has a redlit list of
        // IdPs. If so, cull down the master IdP list removing 'bad' IdPs.
        if ($skin->hasRedlitIdps()) {
            $idpredlit = $skin->getConfigOption('idpredlit');
            foreach ($idpredlit->idp as $redidp) {
                unset($retarray[(string)$redidp]);
            }
        }

        // Fix for CIL-174 - As suggested by Keith Hazelton, replace commas and
        // hyphens with just commas.
        $regex = '/(University of California)\s*[,-]\s*/';
        foreach ($retarray as $entityId => $names) {
            if (preg_match($regex, $names['Organization_Name'])) {
                $retarray[$entityId]['Organization_Name'] =
                    preg_replace($regex, '$1, ', $names['Organization_Name']);
            }
            if (preg_match($regex, $names['Display_Name'])) {
                $retarray[$entityId]['Display_Name'] =
                    preg_replace($regex, '$1, ', $names['Display_Name']);
            }
        }

        // Re-sort the retarray by Display_Name for correct alphabetization.
        uasort($retarray, function ($a, $b) {
            return strcasecmp(
                $a['Display_Name'],
                $b['Display_Name']
            );
        });

        return $retarray;
    }

    /**
     * getIdphintList
     *
     * This function adds support for AARC-G049 "IdP Hinting". It
     * searches both the GET query parameters and the OIDC client
     * parameters passed to the 'authorize' endpoint for a parameter
     * named either 'selected_idp' or 'idphint'. This parameter can be
     * a single entityId or a comma-separated list of entityIds.
     * The entries in the list are processed to remove any 'chained'
     * idphints and also to transform OIDC 'issuer' values into
     * CILogon-specific 'entityIds' as used in the 'Select an IdP'
     * list. Any idps which are not in the current skin's 'Select
     * an IdP' list are removed. The resulting processed list of
     * entityIds is returned, which may be an empty array.
     *
     * @param array $idps (Optional) A list of valid (i.e., greenlit) IdPs.
     *        If this list is empty, then use the current skin's IdP list.
     * @return array A list of entityIds / OIDC provider URLs extracted from
     *         a passed-in parameter 'selected_idp' or 'idphint'. This array
     *         may be empty if no such parameter was found, or if the
     *         entityIds in the list were not valid.
     */
    public static function getIdphintList($idps = [])
    {
        // Check for either 'selected_idp' or 'idphint' parameter that was
        // passed in via a query parameter, either for an OAuth transaction
        // or just 'normally'. Note that if both 'selected_idp' and
        // 'idphint' were passed, 'idphint' takes priority.

        $hintarray = array();
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);

        $hintstr = '';
        if (!empty(@$clientparams['idphint'])) {
            $hintstr = $clientparams['idphint'];
        } elseif (!empty(Util::getGetVar('idphint'))) {
            $hintstr = Util::getGetVar('idphint');
        } elseif (!empty(@$clientparams['selected_idp'])) {
            $hintstr = $clientparams['selected_idp'];
        } elseif (!empty(Util::getGetVar('selected_idp'))) {
            $hintstr = Util::getGetVar('selected_idp');
        }

        if (!empty($hintstr)) {
            // Split on comma to account for multiple idps
            $hintarray = explode(',', $hintstr);

            // Process the list of IdPs to transform them appropriately.
            foreach ($hintarray as &$value) {
                // Check for 'chained' idp hints, and remove the GET params.
                if (preg_match('%([^\?]*)\?%', $value, $matches)) {
                    $value = $matches[1];
                }
                $value = static::normalizeOAuth2IdP($value);
            }
            unset($value); // Break the reference with the last element.

            // Remove any non-greenlit IdPs from the hintarray.
            if (empty($idps)) {
                $idps = static::getCompositeIdPList();
            }
            foreach ($hintarray as $value) {
                if (!isset($idps[$value])) {
                    if (($key = array_search($value, $hintarray)) !== false) {
                        unset($hintarray[$key]);
                    }
                }
            }
        }
        return $hintarray;
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
        if (preg_match('%^https?://accounts.google.com%', $idp)) {
            $idp = 'https://accounts.google.com/o/oauth2/auth';
        } elseif (preg_match('%^https?://github.com%', $idp)) {
            $idp = 'https://github.com/login/oauth/authorize';
        } elseif (preg_match('%^https?://orcid.org%', $idp)) {
            $idp = 'https://orcid.org/oauth/authorize';
        } elseif (preg_match('%^https?://login.microsoftonline.com%', $idp)) {
            $idp = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
        }
        return $idp;
    }

    /**
     * printOIDCConsent
     *
     * This function prints out the block showing the scopes requested by the
     * OIDC client. If 'user_code' is present in the $clientparams array,
     * the Device Code is also printed so the user can verify that the code
     * matches the one on the device.
     */
    public static function printOIDCConsent()
    {
        // Look in the 'scope' OIDC parameter to see which attributes are
        // being requested. The values we care about are 'email', 'profile'
        // (for first/last name), and 'edu.uiuc.ncsa.myproxy.getcert'
        // (which gives a certificate containing first/last name AND email).
        // Anything else should just be output as-is.
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        $scopes = preg_split("/[\s\+]+/", $clientparams['scope']);
        $scopes = array_unique($scopes); // Remove any duplicates

        // CIL-779 Show only those scopes which have been registered, i.e.,
        // compute the set intersection of requested and registered scopes.
        $client_scopes = json_decode($clientparams['client_scopes'], true);
        if (!is_null($client_scopes)) {
            $scopes = array_intersect($scopes, $client_scopes);
        }

        static::printCollapseBegin('oidcconsent', 'Consent to Attribute Release', false);

        echo '
            <div class="card-body px-5">
              <div class="card-text my-2">
                <a target="_blank" href="' ,
                htmlspecialchars($clientparams['client_home_url']) , '">',
                htmlspecialchars($clientparams['client_name']) , '</a>' ,
                ' requests access to the following information.
                If you do not approve this request, do not proceed.
              </div> <!-- end row -->
              <ul>
        ';

        if (array_key_exists('user_code', $clientparams)) {
            echo '<li>User Code: <tt>' . $clientparams['user_code'] .
                '</tt></li>';
        }
        if (in_array('openid', $scopes)) {
            echo '<li>Your CILogon user identifier</li>';
            $scopes = array_diff($scopes, ['openid']);
        }
        if (
            (in_array('profile', $scopes)) ||
            (in_array('edu.uiuc.ncsa.myproxy.getcert', $scopes))
        ) {
            echo '<li>Your name</li>';
            $scopes = array_diff($scopes, ['profile']);
        }
        if (
            (in_array('email', $scopes)) ||
            (in_array('edu.uiuc.ncsa.myproxy.getcert', $scopes))
        ) {
            echo '<li>Your email address</li>';
            $scopes = array_diff($scopes, ['email']);
        }
        if (in_array('org.cilogon.userinfo', $scopes)) {
            echo '<li>Your username and affiliation from your identity provider</li>';
            $scopes = array_diff($scopes, ['org.cilogon.userinfo']);
        }
        if (in_array('edu.uiuc.ncsa.myproxy.getcert', $scopes)) {
            echo '<li>A certificate that allows "' ,
            htmlspecialchars($clientparams['client_name']) ,
            '" to act on your behalf</li>';
            $scopes = array_diff($scopes, ['edu.uiuc.ncsa.myproxy.getcert']);
        }
        // Output any remaining scopes as-is
        foreach ($scopes as $value) {
            echo '<li>', $value , '</li>';
        }
        echo '</ul>
            </div> <!-- end card-body -->
        ';

        static::printCollapseEnd();
    }
}
