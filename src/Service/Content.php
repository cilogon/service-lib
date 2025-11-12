<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use CILogon\Service\PortalCookie;
use CILogon\Service\DBService;
use CILogon\Service\OAuth2Provider;
use CILogon\Service\Loggit;

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
     * @param string $title The text in the window's titlebar. Defaults to
     *        "CILogon Service".
     * @param bool $csrfcookie Set the CSRF cookie. Defaults to true.
     */
    public static function printHeader($title = '', $csrfcookie = true)
    {
        if (strlen($title) == 0) {
            $title = _('CILogon Service');
        }

        if ($csrfcookie) {
            $csrf = Util::getCsrf();
            $csrf->setTheCookie();
        }

        // Get the 'Powered By CILogon' image attributes specified by the skin
        $poweredbyimg = '/images/poweredbycilogon.png';
        $poweredbyurl = 'https://www.cilogon.org/faq';
        $poweredbyalt = 'CILogon';
        $poweredbytitle = _('CILogon Service');

        $skin = Util::getSkin();
        $pbimg = (string)$skin->getConfigOption('poweredbyimg');
        if ((strlen($pbimg) > 0) && (is_readable('/var/www/html' . $pbimg))) {
            $poweredbyimg = $pbimg;
        }

        $pburl = $skin->getConfigOption('poweredbyurl');
        if (!is_null($pburl)) {
            $poweredbyurl = (string)$pburl;
        }
        $pbalt = $skin->getConfigOption('poweredbyalt');
        if (!is_null($pbalt)) {
            $poweredbyalt = (string)$pbalt;
        }
        $pbtitle = $skin->getConfigOption('poweredbytitle');
        if (!is_null($pbtitle)) {
            $poweredbytitle = (string)$pbtitle;
        }

        // Set the language before any text output
        Util::setLanguage();

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
    <link rel="stylesheet" href="/include/fontawesome-7.1.0.min.css" />
    <link rel="stylesheet" href="/include/solid.min.css" />
    <link rel="stylesheet" href="/include/bootstrap-4.6.2.min.css" />
    <link rel="stylesheet" href="/include/bootstrap-select-1.13.18.min.css" />
    <link rel="stylesheet" href="/include/cilogon-1.2.0.css" />
    ';

        $skin->printSkinCSS();

        echo '
  </head>
  <body>';

        // CIL-1643 Additional HTML for use by e.g., a navigation bar.
        if (Util::isOutputExtra()) {
            $skinextrahtml = (string)$skin->getConfigOption('extrahtml');
            if (strlen($skinextrahtml) > 0) {
                echo $skinextrahtml;
            }
        }

        echo '
    <header>
    <div class="skincilogonlogo">
      <a target="_blank" href="', $poweredbyurl,
        '"><img src="', $poweredbyimg, '" alt="', $poweredbyalt,
        '" title="', $poweredbytitle, '" /></a>
    </div>

    <div class="logoheader">
       <h1 aria-label="', $title, '"><span>[CILogon Service]</span></h1>
    </div>
    ';

        // $langsavailable is a string of space-separated languages.
        // Convert it into an array of languages.
        $langsavailable = Util::getSessionVar('langsavailable');
        Util::unsetSessionVar('langsavailable');
        $langsavailarray = explode(' ', $langsavailable);

        // If there 2 or more languages configured, show the
        // language selector popup menu.
        if (count($langsavailarray) > 1) {
            $setlang = Util::getSessionVar('lang');
            static::printFormHead();
            echo '
    <div class="langMenu" id="langMenu">
        <div class="dropup" id="langMenuDropdown">
            <button class="btn btn-secondary" type="button" id="langMenuDropdownButton"
                    data-toggle="dropdown" aria-haspopup="true" aria-expand="false">
                <i class="fa-solid fa-language fa-3x"></i>
            </button>
            <div class="dropdown-menu" id="langMenuDropdownContent">
    ';
            foreach ($langsavailarray as $lang) {
                echo '<button class="dropdown-item';

                if ($lang == $setlang) {
                    echo ' active';
                }

                echo '" type="submit" name="submit"
                    title="', $lang, '" value="', $lang, '" >' .
                     strtoupper(substr($lang, 0, 2)) . '</button>
                     ';
            }

            echo '
            </div> <!-- End langMenuDropdownContent -->
        </div> <!-- End langMenuDropdown -->
    </div> <!-- End langMenu -->
    </form>
    ';
        }

            echo '
    </header>
    <div class="mt-4 container-fluid" role="main" id="mainbootstrap"> <!-- Main Bootstrap Container -->
    ';

        static::printNoScript();

        // CIL-712 Add skin config option to display a info banner.
        $skinbanner = (string)$skin->getConfigOption('banner');
        if (strlen($skinbanner) > 0) {
            echo '
      <div class="alert alert-secondary alert-dismissible fade show" role="alert"
           id="skinbanner">', $skinbanner, '
        <button type="button" class="close" data-dismiss="alert"
          aria-label="', _('Close') , '">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
';
        }

        if ((defined('BANNER_TEXT')) && (!empty(BANNER_TEXT))) {
            echo '
      <div class="alert alert-warning alert-dismissible fade show" role="alert"
           id="defaultbanner>', BANNER_TEXT, '
        <button type="button" class="close" data-dismiss="alert"
          aria-label="', _('Close') ,'">
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
        <p>' . _('For questions about this site, please see the') .
        ' <a target="_blank" href="https://www.cilogon.org/faq">FAQs</a> ' .
        _('or send email to') .
        ' <a href="mailto:' . EMAIL_HELP . '">' . EMAIL_HELP . '</a>.</p>
        <p>' . _('When using the CILogon Service, be mindful of') . '
        <a target="_blank" ' .
        'href="https://www.cilogon.org/aup">' .
        _('your responsibilities') .
        '</a>.</p>
        <p><a target="_blank" href="https://www.cilogon.org/acknowledgements">'  .
        _('Acknowledgements of support') . '</a> ' . _('for this site.') . '</p>';

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

    <script src="/include/jquery-3.7.1.min.js"></script>
    <script src="/include/bootstrap-4.6.2.bundle.min.js"></script>
    <script src="/include/bootstrap-select-1.13.18.min.js"></script>
    <script>$(document).ready(function(){ $(\'[data-toggle="popover"]\').popover(); });</script>
    <script src="/include/cilogon-1.2.0.js"></script>
';

        // CIL-1643 Additional JavaScript for use by e.g., a navigation bar.
        if (Util::isOutputExtra()) {
            $skinextrascript = (string)$skin->getConfigOption('extrascript');
            if (strlen($skinextrascript) > 0) {
                echo $skinextrascript;
            }
        }

        echo '
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
     * @param string $title (Optional) The value of the <form title="?">
     *        attribute. Defaults to empty string (i.e., no title attribute).
     * @param string $action (Optional) The value of the form's 'action'
     *        parameter. Defaults to getScriptDir().
     * @param string $method (Optional) The <form> 'method', one of 'get' or
     *        'post'. Defaults to 'post'.
     */
    public static function printFormHead($title = '', $action = '', $method = 'post')
    {
        static $formnum = 0;

        if (strlen($action) == 0) {
            $action = Util::getScriptDir();
        }

        echo '
          <form action="', $action, '" method="', $method, '"
          autocomplete="off" id="form', sprintf("%02d", ++$formnum), '"
          class="needs-validation" novalidate="novalidate"';
        if (!empty($title)) {
            echo ' title="', $title, '"';
        }
        echo '>';
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
                $providerId = Util::normalizeOAuth2IdP($initialidp);
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

        $googleoauth2 = Util::getOAuth2Url('Google');
        $orcidoauth2 = Util::getOAuth2Url('ORCID');
        $githuboauth2 = Util::getOAuth2Url('GitHub');
        $microsoftoauth2 = Util::getOAuth2Url('Microsoft');

        // If no previous providerId, get from skin, or default
        // to ORCID, Google, or the first IdP in the list.
        $initialidp = Util::normalizeOAuth2IdP(
            (string)$skin->getConfigOption('initialidp')
        );
        if (strlen($providerId) == 0) {
            if ((strlen($initialidp) > 0) && (isset($idps[$initialidp]))) {
                $providerId = $initialidp;
            } elseif (isset($idps[$orcidoauth2])) {
                $providerId = $orcidoauth2;
            } elseif (isset($idps[$googleoauth2])) {
                $providerId = $googleoauth2;
            } else {
                $providerId = array_key_first($idps);
            }
        }

        // CIL-1515 Get the list of recently used IdPs, limited to
        // the currently available IdPs.
        $recentidps = array_values(array_filter(
            Util::getRecentIdPs(),
            fn ($m) => array_key_exists($m, $idps)
        ));

        // Push the "default" IdP onto the front, making sure it
        // doesn't already exist in the list.
        $recentidps = array_values(array_filter(
            $recentidps,
            fn ($m) => $m != $providerId
        ));
        array_unshift($recentidps, $providerId);

        // CIL-1632 Remove "hidden" IdPs from the recently used IdPs
        $hiddenidps = $skin->getHiddenIdPs();
        $recentidps = array_diff($recentidps, $hiddenidps);
        // Sanity Check: is recentidps array now empty due to hiddenidps?
        // If so, set to the initialidp.
        if (
            (empty($recentidps)) &&
            (strlen($initialidp) > 0) &&
            (isset($idps[$initialidp]))
        ) {
            array_unshift($recentidps, $initialidp);
        }

        // Show a max number of IdPs in the recent list; defaults to 5.
        $maxrecentidps = 5;
        $maxopt = Util::getSkin()->getConfigOption('maxrecentidps');
        if (!is_null($maxopt)) {
            $maxrecentidps = (int)$maxopt;
            // Ensure $maxrecentidps is in the range [1..10]
            if ($maxrecentidps < 1) {
                $maxrecentidps = 1;
            } elseif ($maxrecentidps > 10) {
                $maxrecentidps = 10;
            }
        }
        while (count($recentidps) > $maxrecentidps) {
            array_pop($recentidps);
        }
        // The $recentidp list now contains at most $maxrecentidps recently
        // used IdPs which are also available (according to skin / idphint
        // parameter) with the "default" IdP at the front of the list.

        $selecthelp = '<p>
            ' .
            _('CILogon facilitates secure access to CyberInfrastructure ' .
            '(CI). In order to use the CILogon Service, you must first ' .
            'select an identity provider. An identity provider (IdP) ' .
            'is an organization where you have an account and can log on ' .
            'to gain access to online services.') . '
        </p>
        <p>' .
            _('If you are a faculty, staff, or student member of a ' .
            'university or college, please select it as your identity ' .
            'provider. If your school is not listed, please see') .
            ' <a target=\'_blank\' href=\'https://www.cilogon.org/selectidp\'>' .
            _('How to Select an Identity Provider') . '</a>.' .
            '
        </p>
        ';
        // CIL-1526 Allow skin to set selecthelp text
        $skinselecthelp = $skin->getConfigOption('selecthelp');
        if (!is_null($skinselecthelp)) {
            $selecthelp = (string)$skinselecthelp;
        }

        // Count number of OAuth2 providers
        $count = 0;
        if (isset($idps[$orcidoauth2])) {
            $count++;
        }
        if (isset($idps[$googleoauth2])) {
            $count++;
        }
        if (isset($idps[$githuboauth2])) {
            $count++;
        }
        if (isset($idps[$microsoftoauth2])) {
            $count++;
        }

        if ($count > 0) {
            $selecthelp .= '<p>' .
                _('If available, you can also try one of the ' .
                'social identity providers such as ORCID or Google.') .
                '</p>';
        }

        echo '
      <div class="card text-center col-lg-6 offset-lg-3 col-md-8 offset-md-2 col-sm-10 offset-sm-1 mt-3">
        <h4 class="card-header" id="heading-selectanidp">',
        ($useselectedidp ? _('Selected Identity Provider') : _('Select an Identity Provider')),
        '</h4>
        <div class="card-body">
          <form action="', Util::getScriptDir(), '" method="post">
            <div class="form-group">
            <select name="providerId" id="providerId"
                aria-label="' , _('Select an Identity Provider'), '"
                autofocus="autofocus"
                class="selectpicker mw-100"
                data-size="20" data-width="fit"
                data-live-search="true"
                data-live-search-placeholder="Type to search"
                data-live-search-normalize="true">
                ';

        // CIL-15151 Show recent IdPs at the top of the list
        $idpcount = 0;
        foreach ($recentidps as $value) {
            $idpcount++;
            echo '
                <option data-tokens="', $value, '" value="', $value,
                ($idpcount == 1) ? '" selected="selected' : '',
                '">',
                Util::htmlent($idps[$value]['Display_Name']), '</option>';
        }
        if ($idpcount > 1) {
            echo '
                <option data-divider="true" aria-label="', _('divider'), '"></option>';
        }

        echo '
            </select>
            <a href="#" tabindex="0" data-trigger="hover click"
            class="helpcursor" role="tooltip" aria-label="', _('Selection') ,'"
            data-toggle="popover" data-html="true"
            title="Selecting an Identity Provider" id="id-a-select-help"
            data-content="', $selecthelp, '"><i class="fa
            fa-question-circle"></i></a>
            </div> <!-- end div form-group -->
            ';

        if ($showremember) {
            $rememberhelp = _('Check this box to bypass the welcome page on ' .
                'subsequent visits and proceed directly to the selected ' .
                'identity provider. You will need to clear your browser\'s ' .
                'cookies to return here.');
            echo '
            <div class="form-group">
              <div class="form-check">
                <input class="form-check-input" type="checkbox"
                id="keepidp" name="keepidp" ',
                ((strlen($keepidp) > 0) ? 'checked="checked" ' : ''), ' />
                <label class="form-check-label" id="id-label-remember"
                for="keepidp">' . _('Remember this selection') . '</label>
                <a href="#" tabindex="0" data-trigger="hover click"
                class="helpcursor" role="tooltip" aria-label="', _('Remember'), '"
                data-toggle="popover" data-html="true" id="id-a-bypass-help"
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
                 ' value="', implode(',', $idphintlist), '" />';
        }
        echo '<input type="hidden" name="previouspage" value="WAYF" />';
        $lobtext = static::getLogOnButtonText();

        echo '
            <div class="form-group">
              <div class="form-row align-items-center justify-content-center">
                <div class="col-auto">
                  <button type="submit" name="submit"
                  class="btn btn-primary submit"
                  title="', $lobtext, '"
                  value="Log On" id="wayflogonbutton">',
                  $lobtext, '</button>
                </div> <!-- end col-auto -->
        ';

        $wayfcancelbutton = Util::getSkin()->getConfigOption('wayfcancelbutton');
        if (
            ($showcancel) ||
            ((!is_null($wayfcancelbutton)) && ((int)$wayfcancelbutton == 1))
        ) {
            echo '
                <div class="col-auto">
                  <button type="submit" name="submit"
                  class="btn btn-primary submit"
                  title="', _('Cancel'), '"
                  value="Cancel" id="wayfcancelbutton">',
                  _('Cancel'), '</button>
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

        $ppurl = Util::getSkin()->getConfigOption('privacypolicyurl');
        echo '
        <p class="privacypolicy">
        ', _('By logging on to this site, you agree to the'),
        ' <a target="_blank" href="',
        (is_null($ppurl) ? 'https://www.cilogon.org/privacy' : (string)$ppurl),
        '">', _('privacy policy'), '</a>.
        </p>

          </form>

        </div> <!-- End card-body -->
      </div> <!-- End card -->
        ';
    }

    /**
     * printUserAttributes
     *
     * This function shows the user the attributes released by their
     * selected IdP and saved in the PHP session.
     */
    public static function printUserAttributes()
    {
        // Extract all of the user attributes from the session
        $attr_arr = array();
        foreach (DBService::$user_attrs as $value) {
            $attr_arr[$value] = Util::getSessionVar($value);
        }

        $idplist = Util::getIdpList();
        $samlidp = ((!empty($attr_arr['idp'])) &&
                    (!$idplist->isOAuth2($attr_arr['idp'])));

        // Set various booleans for warning/error messages early so that we
        // can display a "general" warning/error icon on the card title.
        $warnings = array();
        $errors = array();

        // CIL-416 Show warning for missing ePPN or Subject ID

        if ($samlidp) {
            $errors['no_eppn'] = (empty($attr_arr['eppn']));
            $errors['no_eptid'] = (empty($attr_arr['eptid']));
            $errors['no_subject_id'] = (empty($attr_arr['subject_id']));
            $errors['no_pairwise_id'] = (empty($attr_arr['pairwise_id']));
            $errors['no_eppn_or_eptid_or_subject_id_or_pairwise_id'] = (
                ($errors['no_eppn']) &&
                ($errors['no_eptid']) &&
                ($errors['no_subject_id']) &&
                ($errors['no_pairwise_id'])
            );
        }

        $errors['no_entityID'] = (empty($attr_arr['idp']));

        $errors['no_oidc'] = (
            (!$errors['no_entityID']) &&
            (!$samlidp) &&
            (empty($attr_arr['oidc']))
        );

        $errors['no_first_name'] = (
            (empty($attr_arr['first_name'])) &&
            (empty($attr_arr['display_name']))
        );

        $errors['no_last_name'] = (
            (empty($attr_arr['last_name'])) &&
            (empty($attr_arr['display_name']))
        );

        $errors['no_display_name'] =  (
            (empty($attr_arr['display_name'])) &&
            ((empty($attr_arr['first_name'])) ||
            (empty($attr_arr['last_name'])))
        );

        $emailvalid = filter_var($attr_arr['email'], FILTER_VALIDATE_EMAIL);
        $errors['no_valid_email'] = ((empty($attr_arr['email'])) || (!$emailvalid));

        static::printCollapseBegin(
            'userattrs',
            _('User Attributes') .
            (
                ((@$warnings['no_eppn']) ? static::getIcon(
                    'fa-exclamation-triangle',
                    'gold',
                    ' ' . _('Some CILogon clients (e.g., Globus) require ePPN.')
                ) : '')
            )
        );

        echo '
          <div class="card-body">
            <table class="table table-striped table-sm"
            aria-label="', _('User Attributes'), '">
            <tbody>';

        // CIL-781 Show CILogon User Identifier (user_uid) when logged in
        $user_uid = Util::getSessionVar('user_uid');
        if (strlen($user_uid) > 0) {
            echo '
              <tr>
                <th>', _('CILogon User Identifier'), ':</th>
                <td>', $user_uid, '</td>
                <td> </td>
              </tr>';
        }

        echo '
              <tr>
                <th>', _('Identity Provider'), ' (entityID):</th>
                <td>', $attr_arr['idp'], '</td>
                <td>';

        if (@$errors['no_entityID']) {
            echo static::getIcon(
                'fa-exclamation-circle',
                'red',
                _('Missing the entityID of the IdP.')
            );
        }

        echo '
                </td>
              </tr>
        ';

        if ($samlidp) {
            echo '
              <tr>
                <th>ePTID:</th>
                <td>', $attr_arr['eptid'], '</td>
                <td>';

            if (@$errors['no_eppn_or_eptid_or_subject_id_or_pairwise_id']) {
                echo static::getIcon(
                    'fa-exclamation-circle',
                    'red',
                    _('Must have one of the following') . ': ePPN, ePTID, Subject ID, Pairwise ID'
                );
            }

            echo '
                </td>
              </tr>

              <tr>
                <th>ePPN:</th>
                <td>', $attr_arr['eppn'], '</td>
                <td>';

            if (@$errors['no_eppn_or_eptid_or_subject_id_or_pairwise_id']) {
                echo static::getIcon(
                    'fa-exclamation-circle',
                    'red',
                    _('Must have one of the following') . ': ePPN, ePTID, Subject ID, Pairwise ID'
                );
            } elseif (@$warnings['no_eppn']) {
                echo static::getIcon(
                    'fa-exclamation-triangle',
                    'gold',
                    _('Some CILogon clients (e.g., Globus) require ePPN.')
                );
            }

            echo '
                </td>
              </tr>
            ';

            if (!empty($attr_arr['subject_id'])) {
                echo '
                  <tr>
                    <th>', _('Subject ID'), ' (subject-id):</th>
                    <td>', $attr_arr['subject_id'], '</td>
                    <td> </td>
                  </tr>';
            }

            if (!empty($attr_arr['pairwise_id'])) {
                echo '
                  <tr>
                    <th>', _('Pairwise ID'), ' (pairwise-id):</th>
                    <td>', $attr_arr['pairwise_id'], '</td>
                    <td> </td>
                  </tr>';
            }
        }

        if ((!empty($attr_arr['idp'])) && (!$samlidp)) {
            echo '
              <tr>
                <th>OpenID:</th>
                <td>', $attr_arr['oidc'], '</td>
                <td>';

            if (@$errors['no_oidc']) {
                echo static::getIcon(
                    'fa-exclamation-circle',
                    'red',
                    _('Missing the OpenID identifier.')
                );
            }

            echo '
                </td>
              </tr>
            ';
        }

        if ((!empty($attr_arr['first_name'])) || (@$errors['no_first_name'])) {
            echo '
              <tr>
                <th>', _('First Name'), ' (givenName):</th>
                <td>', $attr_arr['first_name'], '</td>
                <td>';

            echo '
                </td>
              </tr>
            ';
        }

        if ((!empty($attr_arr['last_name'])) || (@$errors['no_last_name'])) {
            echo '
              <tr>
                <th>', _('Last Name'), ' (sn):</th>
                <td>', $attr_arr['last_name'], '</td>
                <td>';

            echo '
                </td>
              </tr>
            ';
        }

        if ((!empty($attr_arr['display_name'])) || (@$errors['no_display_name'])) {
            echo '
              <tr>
                <th>', _('Display Name'), ' (displayName):</th>
                <td>', $attr_arr['display_name'], '</td>
                <td>';

            echo '
                </td>
              </tr>
            ';
        }

        echo '
              <tr>
                <th>', _('Email Address'), ' (email):</th>
                <td>', $attr_arr['email'], '</td>
                <td>';

        echo '
                </td>
              </tr>';

        if (!empty($attr_arr['loa'])) {
            echo '
              <tr>
                <th>', _('Level of Assurance'), ' (assurance):</th>
                <td>', $attr_arr['loa'], '</td>
                <td> </td>
              </tr>';
        }

        if (!empty($attr_arr['acr'])) {
            echo '
              <tr>
                <th>AuthnContextClassRef:</th>
                <td>', $attr_arr['acr'], '</td>
                <td> </td>
              </tr>';
        }

        if (!empty($attr_arr['amr'])) {
            echo '
              <tr>
                <th>AuthnMethodRef:</th>
                <td>', $attr_arr['amr'], '</td>
                <td> </td>
              </tr>';
        }

        if (!empty($attr_arr['affiliation'])) {
            echo '
              <tr>
                <th>', _('Affiliation'), ' (affiliation):</th>
                <td>', $attr_arr['affiliation'], '</td>
                <td> </td>
              </tr>';
        }

        if (!empty($attr_arr['entitlement'])) {
            echo '
              <tr>
                <th>', _('Entitlement'), ' (entitlement):</th>
                <td>', $attr_arr['entitlement'], '</td>
                <td> </td>
              </tr>';
        }

        if (!empty($attr_arr['ou'])) {
            echo '
              <tr>
                <th>', _('Organizational Unit'), ' (ou):</th>
                <td>', $attr_arr['ou'], '</td>
                <td> </td>
              </tr>';
        }

        if (!empty($attr_arr['member_of'])) {
            echo '
              <tr>
                <th>', _('Member'), ' (member):</th>
                <td>', $attr_arr['member_of'], '</td>
                <td> </td>
              </tr>';
        }

        if (!empty($attr_arr['itrustuin'])) {
            echo '
              <tr>
                <th>iTrustUIN (itrustuin):</th>
                <td>', $attr_arr['itrustuin'], '</td>
                <td> </td>
              </tr>';
        }

        if (!empty($attr_arr['eduPersonOrcid'])) {
            echo '
              <tr>
                <th>eduPersonOrcid:</th>
                <td>', $attr_arr['eduPersonOrcid'], '</td>
                <td> </td>
              </tr>';
        }

        if (!empty($attr_arr['preferred_username'])) {
            echo '
              <tr>
                <th>', _('Preferred Username'), ':</th>
                <td>', $attr_arr['preferred_username'], '</td>
                <td> </td>
              </tr>';
        }

        if (!empty($attr_arr['uidNumber'])) {
            echo '
              <tr>
                <th>uidNumber:</th>
                <td>', $attr_arr['uidNumber'], '</td>
                <td> </td>
              </tr>';
        }

        echo '
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

        static::printCollapseBegin('idpmeta', _('Identity Provider Attributes'));

        echo'
          <div class="card-body">
            <table class="table table-striped table-sm"
            aria-label="', _('Identity Provider Attributes'), '">
            <tbody>
              <tr>
                <th>', _('Organization Name'), ':</th>
                <td>', @$shibarray['Organization Name'], '</td>
                <td>';

        if (empty(@$shibarray['Organization Name'])) {
            echo static::getIcon(
                'fa-exclamation-circle',
                'red',
                _('Could not find ' .
                '&lt;OrganizationDisplayName&gt; in metadata.')
            );
        }

        echo '
                </td>
              </tr>
              <tr>
                <th>', _('Home Page'), ':</th>
                <td><a target="_blank" href="', @$shibarray['Home Page'], '">',
                @$shibarray['Home Page'], '</a></td>
                <td> </td>
              </tr>

              <tr>
                <th>', _('Support Contact'), ':</th>';
        if (
            (!empty(@$shibarray['Support Name'])) ||
            (!empty(@$shibarray['Support Address']))
        ) {
            echo '
                <td>', @$shibarray['Support Name'], ' &lt;',
                        preg_replace('/^mailto:/', '', @$shibarray['Support Address']), '&gt;</td>
                <td> </td>';
        }
        echo '
              </tr>

        ';

        if ($samlidp) {
            echo '
              <tr>
                <th>', _('Technical Contact'), ':</th>';
            if (
                (!empty(@$shibarray['Technical Name'])) ||
                (!empty(@$shibarray['Technical Address']))
            ) {
                echo '
                <td>', @$shibarray['Technical Name'], ' &lt;',
                        preg_replace('/^mailto:/', '', @$shibarray['Technical Address']), '&gt;</td>
                <td> </td>';
            }
            echo '
              </tr>

              <tr>
                <th>', _('Administrative Contact'), ':</th>';
            if (
                (!empty(@$shibarray['Administrative Name'])) ||
                (!empty(@$shibarray['Administrative Address']))
            ) {
                echo '
                <td>', @$shibarray['Administrative Name'], ' &lt;',
                        preg_replace('/^mailto:/', '', @$shibarray['Administrative Address']), '&gt;</td>
                <td> </td>';
            }
            echo '
              </tr>

              <tr>
                <th>', _('Registered by InCommon'), ':</th>
                <td>', ($idplist->isRegisteredByInCommon($idp) ? _('Yes') : _('No')), '</td>
                <td> </td>
              </tr>

              <tr>
                <th><a style="text-decoration:underline" target="_blank"
                href="http://refeds.org/category/research-and-scholarship">REFEDS
                R &amp; S</a>:</th>
                <td>', ($idplist->isREFEDSRandS($idp) ? _('Yes') : _('No')), '</td>
                <td>';

            echo '
                </td>
              </tr>

              <tr>
                <th><a style="text-decoration:underline" target="_blank"
                       href="https://refeds.org/sirtfi">SIRTFI</a>:</th>
                <td>', ($idplist->isSIRTFI($idp) ? _('Yes') : _('No')), '</td>
                <td>';

            echo '
                </td>
              </tr>

              <tr>
                <th>', _('Entity ID'), ' (entityID)', ':</th>
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
            '<i class="fa-solid nocollapse ' . $icon . '"></i>' .
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
      <section title="', $title, '" id="id-section-', $name, '">
      <div class="card col-sm-10 offset-sm-1" id="id-div-', $name, '">
        <h5 class="card-header text-center">
          <a class="d-block',
            ($collapsed ? ' collapsed' : ''),
            '" data-toggle="collapse"
            href="#collapse-', $name, '" aria-expanded="',
            ($collapsed ? 'false' : "true"),
            '" aria-controls="collapse-', $name, '"
            id="heading-', $name, '">
            <i class="fa-solid fa-chevron-down fa-pull-end"></i>
            ', $title, '
          </a>
        </h5>
        <div id="collapse-',$name, '" class="collapse',
        ($collapsed ? '' : ' show'),
        '" >';
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
      </section>
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
            ', $errortext, '
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
          <span>', _('Notice: JavaScript is not enabled. ' .
          'The CILogon Service requires JavaScript for functionality.'),
          ' <a target="_blank" href="https://enable-javascript.com/" ',
          'class="alert-link">', _('Please Enable JavaScript'), '</a>.
          </span>
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
        $logofftext = _(
            'End your CILogon session and return to the front ' .
            'page. Note that this will not log you out from ' .
            'your Identity Provider.'
        );

        static::printFormHead(_('Log Off'));
        echo '
          <div class="form-group mt-3">
            <div class="form-row align-items-center">
              <div class="col text-center">
              ';

        $logofftextbox = Util::getSkin()->getConfigOption('logofftextbox');
        if ((!is_null($logofftextbox)) && ((int)$logofftextbox == 1)) {
            echo '  <div class="btn btn-primary"
                title="', _('Exit your browser'), '">',
                _('To log off, please quit your browser.'), '</div>';
        } else {
            echo '  <button type="submit" name="submit"
                class="btn btn-primary submit"
                title="', $logofftext, '" value="Log Off">',
                _('Log Off'), '</button>';
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

        static::printHeader(_('Error Logging On'));
        static::printCollapseBegin(
            'attributeerror',
            _('General Error'),
            false
        );

        echo '
              <div class="card-body px-5" id="id-general-error">';

        static::printErrorBox(_('An error has occurred. This may be a ' .
            'temporary error. Please try again later, or contact us at ' .
            'the the email address at the bottom of the page.'));

        static::printFormHead(_('General Error'), $redirect, 'get');

        echo '
              <div class="card-text my-2">
                <div class="form-group">
                  <div class="form-row align-items-center
                  justify-content-center">
                    <div class="col-auto">
                      ', $redirectform, '
                      <button type="submit" name="submit"
                      class="btn btn-primary submit form-control"
                      value="Proceed"
                      title="', _('Proceed'),'">',
                      _('Proceed'), '</button>
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
     * not all attributes were released by the IdP.
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
     * @param string $eduPersonOrcid
     * @param string $subject_id
     * @param string $pairwise_id
     * @param string $preferred_username
     * @param string $uidNumber
     * @param string $clientparams
     * @param string $redirect The url for the <form> element
     * @param string $redirectform Additional hidden input fields for the
     *        <form>.
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
        $eduPersonOrcid,
        $subject_id,
        $pairwise_id,
        $preferred_username,
        $uidNumber,
        $clientparams,
        $redirect,
        $redirectform
    ) {
        Util::unsetAllUserSessionVars();

        static::printHeader(_('Error Logging On'));
        static::printCollapseBegin(
            'attributeerror',
            _('Attribute Release Error'),
            false
        );

        echo '
              <div class="card-body px-5">
        ';

        $errorboxstr = '
                <div class="card-text my-2" id="id-problem-logging-on">
                  ' .
                  _('There was a problem logging on. Your identity provider ' .
                  'has not provided CILogon with required information.') . '
                </div> <!-- end card-text -->
                <dl class="row">';

        $missingattrs = '';
        // Show user which attributes are missing
        if (
            (strlen($eppn) == 0) &&
            (strlen($eptid) == 0) &&
            (strlen($subject_id) == 0)
        ) {
            $errorboxstr .= '
                <dt class="col-sm-3">subject-id:</dt>
                <dd class="col-sm-9">' . _('MISSING') . '</dd>
                <dt class="col-sm-3">ePPN:</dt>
                <dd class="col-sm-9">' . _('MISSING') . '</dd>';
            $missingattrs .= '%0D%0A    subject-id   -OR-' .
                             '%0D%0A    eduPersonPrincipalName';
        }
        if ((strlen($first_name) == 0) && (strlen($display_name) == 0)) {
            $errorboxstr .= '
                <dt class="col-sm-3">' . _('First Name') . ':</dt>
                <dd class="col-sm-9">' . _('MISSING') . '</dd>';
            $missingattrs .= '%0D%0A    givenName (first name)';
        }
        if ((strlen($last_name) == 0) && (strlen($display_name) == 0)) {
            $errorboxstr .= '
                <dt class="col-sm-3">' . _('Last Name') . ':</dt>
                <dd class="col-sm-9">' . _('MISSING') . '</dd>';
            $missingattrs .= '%0D%0A    sn (last name)';
        }
        if (
            (strlen($display_name) == 0) &&
            ((strlen($first_name) == 0) || (strlen($last_name) == 0))
        ) {
            $errorboxstr .= '
                <dt class="col-sm-3">' . _('Display Name') . ':</dt>
                <dd class="col-sm-9">' . _('MISSING') . '</dd>';
            $missingattrs .= '%0D%0A    displayName';
        }
        $emailvalid = filter_var($email, FILTER_VALIDATE_EMAIL);
        if ((strlen($email) == 0) || (!$emailvalid)) {
            $errorboxstr .= '
                <dt class="col-sm-3">' . _('Email Address') . ':</dt>
                <dd class="col-sm-9">' .
            ((strlen($email) == 0) ? '' . _('MISSING') . '' : _('INVALID')) . '</dd>';
            $missingattrs .= '%0D%0A    mail (email address)';
        }
        $errorboxstr .= '</dl>';
        static::printErrorBox($errorboxstr);

        $student = false;
        if (
            (strlen($email) == 0) &&
            (preg_match('/student@/', $affiliation))
        ) {
            $student = true;
            echo '
                <div class="card-text my-2" id="id-student-email">
                  ',
                  _('If you are a student, ' .
                  'you may need to ask your identity provider ' .
                  'to release your email address.'), '
                </div> <!-- end card-text -->
            ';
        }

        // Attempt to get the OAuth1/OIDC client name
        $portalname = Util::getSessionVar('portalname');
        if (strlen($portalname) == 0) {
            $portalname = @$clientparams['client_name'];
        }

        // Get contacts from metadata for email addresses
        $idplist = Util::getIdpList();
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
            'https://www.cilogon.org/service/addidp for more ' .
            'details. Thank you for any help you can provide.';
        echo '
                <div class="card-text my-2" id="id-having-problems">
                  ',
                  _('Contact your identity provider to let them know you ' .
                  'are having having a problem logging on to CILogon.'), '
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
                  <li> ', _('Support Contact'), ': ',
                  $name, ' <a class="btn btn-primary"
                  title="', _('Contact Support'), '"
                  href="mailto:', $addr, $emailmsg, '">',
                  $addr, '</a>
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
                      <li> ', _('Technical Contact'), ': ',
                      $name, ' <a class="btn btn-primary"
                      title="', _('Contact Support'), '"
                      href="mailto:', $addr, $emailmsg, '">',
                      $addr, '</a>
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
                      <li> ', _('Administrative Contact'), ': ',
                      $name, ' <a class="btn btn-primary"
                      title="', _('Contact Support'), '"
                      href="mailto:', $addr, $emailmsg, '">',
                      $addr, '</a>
                      </li>';
            }
        }

        echo '
                </ul>
                <div class="card-text my-2" id="id-alternatively">
                  ',
                  _('Alternatively, you can contact us at the email address ' .
                  'at the bottom of the page.'), '
                </div> <!-- end card-text -->
            ';

        static::printFormHead(_('Attribute Release Error'), $redirect, 'get');

        echo '
              <div class="card-text my-2">
                <div class="form-group">
                  <div class="form-row align-items-center
                  justify-content-center">
                    <div class="col-auto">
                      ', $redirectform, '
                      <button type="submit" name="submit"
                      class="btn btn-primary submit form-control"
                      value="Proceed"
                      title="', _('Proceed'), '">',
                      _('Proceed'), '</button>
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
        static::printHeader(_('Error Logging On'));
        static::printCollapseBegin(
            'oauth2attrerror',
            _('Error Logging On'),
            false
        );

        echo '
            <div class="card-body px-5">';

        static::printErrorBox(_('There was a problem logging on.'));

        if ($idp_display_name == 'Google') {
            echo '
              <div class="card-text my-2" id="id-problem-google-1">
                ',
                _('There was a problem logging on. It appears that you have ' .
                'attempted to use Google as your identity provider, but your ' .
                'name or email address was missing. To rectify this problem, ' .
                'go to'),
                ' <a target="_blank" href="https://myaccount.google.com/personal-info">',
                _('Google\'s Personal Info page'), '</a> ',
                _('and enter your first name, last name, and email address. ' .
                ' (All other Google account information is not required by ' .
                'the CILogon Service.)'), '
              </div>
              <div class="card-text my-2" id="id-problem-google-2">
                ',
                _('After you have updated your Google account profile, click ' .
                'the "Proceed" button below and attempt to log on ' .
                'with your Google account again. If you have any questions, ' .
                'please contact us at the email address at the bottom of the ' .
                'page.'), '
              </div>';
        } elseif ($idp_display_name == 'GitHub') {
            echo '
              <div class="card-text my-2" id="id-problem-github-1">
                ',
                _('There was a problem logging on. It appears that you have ' .
                'attempted to use GitHub as your identity provider, but your ' .
                'name or email address was missing. To rectify this problem, ' .
                'go to'),
                ' <a target="_blank" href="https://github.com/settings/profile">',
                _('GitHub\'s Public Profile page') , '</a>, ',
                _('and enter your name and email address. ' .
                '(All other GitHub account information is not required by ' .
                'the CILogon Service.)'), '
              </div>
              <div class="card-text my-2" id="id-problem-github-2">
                ',
                _('After you have updated your GitHub account profile, click ' .
                'the "Proceed" button below and attempt to log on ' .
                'with your GitHub account again. If you have any questions, ' .
                'please contact us at the email address at the bottom of the ' .
                'page.'), '
              </div>';
        } elseif ($idp_display_name == 'ORCID') {
            echo '
              <div class="card-text my-2" id="id-problem-orcid-1">
                ',
                _('There was a problem logging on. It appears that you have ' .
                'attempted to use ORCID as your identity provider, but your ' .
                'name or email address was missing. To rectify this problem, ' .
                'go to'),
                ' <a target="_blank" href="https://orcid.org/my-orcid">',
                _('ORCID\'s Profile page'), '</a>, ',
                _('enter your name and email address, and ' .
                'make sure they can be viewed by Everyone. ' .
                '(All other ORCID account information is not required by ' .
                'the CILogon Service.)'), '
              </div>
              <div class="card-text my-2" id="id-problem-orcid-2">
                ',
                _('After you have updated your ORCID account profile, click ' .
                'the "Proceed" button below and attempt to log on ' .
                'with your ORCID account again. If you have any questions, ' .
                'please contact us at the email address at the bottom of the ' .
                'page.'), '
              </div>';
        } elseif ($idp_display_name == 'Microsoft') {
            echo '
              <div class="card-text my-2" id="id-problem-microsoft-1">
                ',
                _('There was a problem logging on. It appears that you have ' .
                'attempted to use Microsoft as your identity provider, but your ' .
                'name or email address was missing. To rectify this problem, ' .
                'go to'),
                ' <a target="_blank" href="https://account.microsoft.com">',
                _('Microsoft\'s Account page'), '</a>, ',
                _('and enter your name and email address. ' .
                '(All other Microsfot account information is not required by ' .
                'the CILogon Service.)'), '
              </div>
              <div class="card-text my-2" id="id-problem-microsoft-2">
                ',
                _('After you have updated your Microsoft account profile, click ' .
                'the "Proceed" button below and attempt to log on ' .
                'with your Microsoft account again. If you have any questions, ' .
                'please contact us at the email address at the bottom of the ' .
                'page.'), '
              </div>';
        }

        static::printFormHead(_('Attribute Release Error'), $redirect, 'get');

        echo '
              <div class="card-text my-2">
                <div class="form-group">
                  <div class="form-row align-items-center
                  justify-content-center">
                    <div class="col-auto">
                      <input type="hidden" name="providerId"
                      value="',
                      Util::getOAuth2Url($idp_display_name), '" />
                      ', $redirectform, '
                      <button type="submit" name="submit"
                      class="btn btn-primary submit form-control"
                      value="Proceed"
                      title="', _('Proceed'), '">',
                      _('Proceed'), '</button>
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
        $providerId = Util::normalizeOAuth2IdP(Util::getPostVar('providerId'));
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
            Util::getRecentIdPs($providerId);
            $providerName = Util::getOAuth2IdP($providerId);
            if (array_key_exists($providerName, Util::$oauth2idps)) {
                // Log in with an OAuth2 IdP
                static::redirectToGetOAuth2User($providerId);
            } else { // Use InCommon authn
                static::redirectToGetShibUser($providerId);
            }
        } else { // IdP not in list, or no IdP selected
            Util::setSessionVar('logonerror', _('Please select a valid IdP.'));
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
     * then redirect to the appropriate IdP. Otherwise, print the main
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
        $idp = Util::getSessionVar('idp');
        $readidpcookies = true;  // Assume config options are not set
        $skin = Util::getSkin();
        $forceinitialidp = (int)$skin->getConfigOption('forceinitialidp');
        $initialidp = Util::normalizeOAuth2IdP(
            (string)$skin->getConfigOption('initialidp')
        );

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
            $selected_idp = Util::normalizeOAuth2IdP($idphintlist[0]);
        }

        if ((strlen($redirect_uri) > 0) || (strlen($client_id) > 0)) {
            // CIL-431 - If the OAuth2/OIDC $redirect_uri or $client_id is set,
            // then check for a match in the BYPASS_IDP_ARRAY or
            // ciloa2.bypass database table (where 'type'='idp') to see if
            // we should automatically redirect to a specific IdP. Used
            // mainly by campus gateways.
            $bypassidp = '';
            $bypassidparray = Util::getBypass()->getBypassIdPArray();
            if ((!is_null($bypassidparray)) && (!empty($bypassidparray))) {
                foreach ($bypassidparray as $key => $value) {
                    if (
                        ($key === $redirect_uri) ||
                        ($key === $client_id) ||
                        ($key === @(Util::getAdminForClient($client_id))['admin_id']) ||
                        (@preg_match($key, $redirect_uri)) ||
                        (@preg_match($key, $client_id)) ||
                        (@preg_match($key, @(Util::getAdminForClient($client_id))['admin_id']))
                    ) {
                        $bypassidp = $value;
                        // CIL-837 Reset the 'skin' to unset green/red-lit IdPs
                        $skin->init(true);
                        break;
                    }
                }
            }

            // CIL-613 - Next, check for a match in the ALLOW_BYPASS_ARRAY.
            // If found, then allow the idphint/selected_idp to be used as the
            // IdP to redirect to.
            if ((empty($bypassidp)) && (!empty($selected_idp))) {
                foreach (Util::getBypass()->getAllowBypassArray() as $key => $value) {
                    if (
                        ($key === $redirect_uri) ||
                        ($key === $client_id) ||
                        ($key === @(Util::getAdminForClient($client_id))['admin_id']) ||
                        (@preg_match($key, $redirect_uri)) ||
                        (@preg_match($key, $client_id)) ||
                        (@preg_match($key, @(Util::getAdminForClient($client_id))['admin_id']))
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
            } else {
                $last_sso_idp = Util::getLastSSOIdP();
                if ((strlen($last_sso_idp) > 0) && ($last_sso_idp == $idp)) {
                    // CIL-1369 Special Single Sign-On (SSO) handling for
                    // OIDC clients. If the $client_id has an associated
                    // admin client, and the session IdP matches the IdP
                    // previously used with a CO's OIDC client, then bypass
                    // the "Select an Identity Provider" page.
                    $providerId = $idp;
                    $keepidp = 'checked';
                    // To skip the next code blocks, unset a few variables.
                    $forceinitialidp = 0;     // Skip checking this option
                    $selected_idp = '';       // Skip any passed-in option
                    $readidpcookies = false;  // Don't read in the IdP cookies
                }
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
            $providerId = Util::normalizeOAuth2IdP($providerId);

            // If selected_idp was specified at the OIDC authorize endpoint,
            // make sure that it matches the saved providerId. If not,
            // then show the Logon page and uncheck the keepidp checkbox.
            if ((strlen($selected_idp) == 0) || ($selected_idp == $providerId)) {
                Util::setPortalOrCookieVar($pc, 'providerId', $providerId, true);
                $providerName = Util::getOAuth2IdP($providerId);
                if (array_key_exists($providerName, Util::$oauth2idps)) {
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

        $idp              = Util::getSessionVar('idp');
        $idp_display_name = Util::getSessionVar('idp_display_name');
        $user_uid         = Util::getSessionVar('user_uid');
        $status           = Util::getSessionVar('status');
        $authntime        = Util::getSessionVar('authntime');

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
     * when the user logs in with a Shibboleth Identity Provider.
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
     * instead simply show the user attributes page. Otherwise, we start
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
        // simply go to the user attributes page.
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
            $providerName = Util::getOAuth2IdP($providerId);

            // Get the authz URL and redirect
            $oauth2 = new OAuth2Provider($providerName);
            if (is_null($oauth2->provider)) {
                Util::setSessionVar('logonerror', _('Invalid Identity Provider.'));
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

        // Check for various error conditions and print out appropriate page
        if (
            (strlen($user_uid) == 0) || // Empty user_uid
            (strlen($status) == 0) ||   // Empty status
            ($status & 1)               // Odd-numbered status = error
        ) {
            $log->error(
                '=DBS= Failed to getuser.' .
                ' status="' . DBService::statusToStatusText($status) . '"' .
                ' user_uid="' .
                ((strlen($user_uid) > 0) ? $user_uid : '<MISSING>') . '"'
            );

            // Is this a SAML IdP?
            $idplist = Util::getIdpList();
            $samlidp = ((!empty($idp)) && (!$idplist->isOAuth2($idp)));

            // Was there a misssing parameter?
            $missingparam = ($status ==
                DBService::$STATUS['STATUS_MISSING_PARAMETER_ERROR']);

            if ($missingparam && $samlidp) {
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
                    $eduPersonOrcid,
                    $subject_id,
                    $pairwise_id,
                    $preferred_username,
                    $uidNumber,
                    $clientparams,
                    $redirect,
                    $redirectform
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
            $providerId = Util::normalizeOAuth2IdP(Util::getSessionVar('idp'));
            if ((strlen($providerId) > 0) && (!isset($idps[$providerId]))) {
                Util::setSessionVar(
                    'logonerror',
                    _('Invalid IdP selected. Please try again.')
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
                Util::getLastSSOIdP(true); // Save current IdP for SSO
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

        // Log users with interesting statuses
        if ($status == DBService::$STATUS['STATUS_NEW_USER']) {
            $log->info('=DBS= New user created.');
        } elseif ($status == DBService::$STATUS['STATUS_USER_UPDATED']) {
            $log->info('=DBS= User data updated.');
        } elseif ($status == DBService::$STATUS['STATUS_IDP_UPDATED']) {
            $log->info('=DBS= User IdP entityID updated.');
        }
        printMainPage();
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
        $retval = _('Log On');
        $lobt = Util::getSkin()->getConfigOption('logonbuttontext');
        if (!is_null($lobt)) {
            $retval = (string)$lobt;
        }
        return $retval;
    }

    /**
     * getMachineHostname
     *
     * This function is utilized in the formation of the URL for the
     * Shibboleth Single Sign-on session initiator URL. It returns
     * a host-specific URL
     * hostname by mapping the local machine hostname (as returned
     * by 'uname -n') to an InCommon metadata cilogon.org hostname
     * (e.g., cilogon.org). DEFAULT_HOSTNAME is returned by default.
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
        foreach (Util::$oauth2idps as $name => $url) {
            // CIL-617 Show OAuth2 IdPs only if client_id is configured
            $client_id_def = strtoupper($name) . '_OAUTH2_CLIENT_ID';
            if (defined($client_id_def)) {
                $client_id = constant($client_id_def);
                if (!empty($client_id)) {
                    $retarray[$url]['Organization_Name'] = $name;
                    $retarray[$url]['Display_Name'] = $name;
                }
            }
        }

        // CIL-1739 Combine the <regauthgreenlit> and <idpgreenlit> lists
        // into a single list of green-lit IdPs.
        $combinedGreenList = array();
        // First, put any green-lit IdPs into the combined list.
        if ($skin->hasGreenlitIdps()) {
            $idpgreenlit = $skin->getConfigOption('idpgreenlit');
            foreach ($idpgreenlit->idp as $greenidp) {
                $greenidp = Util::normalizeOAuth2IdP($greenidp);
                $combinedGreenList[] = (string)$greenidp;
            }
        }
        // Next, for each green-lit regauth, put the corresponding
        // list of IdPs into the compbined list.
        if ($skin->hasGreenlitRegAuths()) {
            $regauthgreenlit = $skin->getConfigOption('regauthgreenlit');
            $idplist = Util::getIdpList();
            foreach ($regauthgreenlit->regauth as $greenregauth) {
                $greenidplist = $idplist->getIdPsForRegAuth((string)$greenregauth);
                if (!empty($greenidplist)) {
                    $combinedGreenList = array_merge($combinedGreenList, $greenidplist);
                }
            }
        }

        // Loop through the list of IdPs and check for the following.
        // CIL-174 As suggested by Keith Hazelton, replace commas and
        // hyphens with just commas. for University of California schools.
        // CIL-1685 Filter IdPs based on Registration Authorities.
        // Also filter IdPs based on IdP greenlit/redlit lists.
        $regex = '/(University of California)\s*[,-]\s*/';
        foreach ($retarray as $entityId => $names) {
            // CIL-174 For UC schools, replace hyphens with commas
            if (preg_match($regex, $names['Organization_Name'])) {
                $retarray[$entityId]['Organization_Name'] =
                    preg_replace($regex, '$1, ', $names['Organization_Name']);
            }
            if (preg_match($regex, $names['Display_Name'])) {
                $retarray[$entityId]['Display_Name'] =
                    preg_replace($regex, '$1, ', $names['Display_Name']);
            }
            // CIL-1685 Filter IdP list based on Registration Authorities.
            // Also filter based on IdP greenlit/redlit lists.
            if (
                (!empty($combinedGreenList) && !in_array($entityId, $combinedGreenList)) ||
                ($skin->regAuthRedlit($entityId)) ||
                ($skin->idpRedlit($entityId))
            ) {
                unset($retarray[$entityId]);
            }
        }

        // Re-sort the retarray by Display_Name for correct alphabetization.
        uasort($retarray, function ($a, $b) {
            return strcasecmp(
                $a['Display_Name'],
                $b['Display_Name']
            );
        });

        // CIL-1595 Show "preferred" IdPs at the top of the list
        $idppreferred = $skin->getConfigOption('idppreferred');
        if ((!is_null($idppreferred)) && (!empty($idppreferred->idp))) {
            $prefidparray = array();
            foreach ($idppreferred->idp as $prefidp) {
                $prefidp = Util::normalizeOAuth2IdP($prefidp);
                $prefidparray[(string)$prefidp] = $retarray[(string)$prefidp];
                unset($retarray[(string)$prefidp]);
            }
            $retarray = array_merge($prefidparray, $retarray);
        }

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
                $value = Util::normalizeOAuth2IdP($value);
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
        $hintarray = array_values($hintarray); // Re-index array after unset
        return $hintarray;
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

        // CIL-1765 - Find any non-standard scopes to be shown to the user
        $standard_scopes = [
            'openid',
            'email',
            'profile',
            'org.cilogon.userinfo',
            'edu.uiuc.ncsa.myproxy.getcert'
        ];
        $nonstandard_scopes = array_diff($scopes, $standard_scopes);

        // CIL-779 Show only those scopes which have been registered, i.e.,
        // compute the set intersection of requested and registered scopes.
        $client_scopes = json_decode($clientparams['client_scopes'], true);
        if (!is_null($client_scopes)) {
            $scopes = array_intersect($scopes, $client_scopes);
        }

        // Add back in any non-standard scopes
        $scopes = array_merge($scopes, $nonstandard_scopes);

        static::printCollapseBegin('oidcconsent', _('Consent to Attribute Release'), false);

        echo '
            <div class="card-body px-5">
              <div class="card-text my-2" id="id-consent-attribute-release">
                <a target="_blank" href="',
                htmlspecialchars($clientparams['client_home_url']), '">',
                htmlspecialchars($clientparams['client_name']), '</a>',
                _(' requests access to the following information. ' .
                'If you do not approve this request, do not proceed.'), '
              </div> <!-- end row -->
              <ul>
        ';

        if (array_key_exists('user_code', $clientparams)) {
            echo '<li>', _('User Code'), ': <tt>' . $clientparams['user_code'] .
                '</tt></li>';
        }
        if (in_array('openid', $scopes)) {
            echo '<li>', _('Your CILogon user identifier'), '</li>';
            $scopes = array_diff($scopes, ['openid']);
        }
        if (
            (in_array('profile', $scopes)) ||
            (in_array('edu.uiuc.ncsa.myproxy.getcert', $scopes))
        ) {
            echo '<li>', _('Your name'), '</li>';
            $scopes = array_diff($scopes, ['profile']);
        }
        if (
            (in_array('email', $scopes)) ||
            (in_array('edu.uiuc.ncsa.myproxy.getcert', $scopes))
        ) {
            echo '<li>', _('Your email address'), '</li>';
            $scopes = array_diff($scopes, ['email']);
        }
        if (in_array('org.cilogon.userinfo', $scopes)) {
            echo '<li>', _('Your username and affiliation from your ' .
                'identity provider'), '</li>';
            $scopes = array_diff($scopes, ['org.cilogon.userinfo']);
        }
        // Output any remaining scopes as-is
        foreach ($scopes as $value) {
            // Skip printing out the 'getcert' scope
            if ($value != 'edu.uiuc.ncsa.myproxy.getcert') {
                echo '<li>', $value, '</li>';
            }
        }
        echo '</ul>
            </div> <!-- end card-body -->
        ';

        static::printCollapseEnd();
    }

    /**
     * printLogout
     *
     * This function is called by the '/logout/' endpoint. It removes various session variables and
     * cookies so the user can log in again later. If the IdP is known, the user is shown a link to
     * (optionally) log out of the IdP.
     */
    public static function printLogout()
    {
        $log = new Loggit();
        $log->info('Logout page hit.', false, false);

        $idp              = Util::getSessionVar('idp');
        $idp_display_name = Util::getSessionVar('idp_display_name');
        $skin             = Util::getSessionVar('cilogon_skin'); // Preserve the skin

        Util::removeShibCookies();
        Util::unsetUserSessionVars();
        Util::setSessionVar('cilogon_skin', $skin); // Re-apply the skin

        static::printHeader(_('Logged Out of the CILogon Service'));

        Util::unsetSessionVar('cilogon_skin'); // Clear the skin

        static::printCollapseBegin('logout', _('Logged Out of CILogon'), false);

        echo '
            <div class="card-body px-5">
              <div class="card-text my-2" id="id-successfully-logged-out-1">
                ',
                _('You have successfully logged out of CILogon.'), '
              </div> <!-- end card-text -->
        ';

        if ($idp == Util::getOAuth2Url('Google')) {
            echo '
              <div class="card-text my-2" id="id-successfully-logged-out-2">
                ',
                _('You can optionally click the link below to log out of Google. ' .
                'However, this will log you out from ALL of your Google accounts. ' .
                'Any current Google sessions in other tabs/windows may be invalidated.'), '
              </div>
              <div class="row align-items-center justify-content-center mt-3">
                <div class="col-auto">
                  <a class="btn btn-primary"
                  title="', _('Log out from Identity Provider'), '"
                  href="https://accounts.google.com/Logout">',
                  _('(Optional) Log out from Google'), '</a>
                </div> <!-- end col-auto -->
              </div> <!-- end row align-items-center -->
            ';
        } elseif ($idp == Util::getOAuth2Url('GitHub')) {
            echo '
              <div class="card-text my-2" id="id-successfully-logged-out-3">
                ',
                _('You can optionally click the link below to log out of GitHub.'), '
              </div>
              <div class="row align-items-center justify-content-center mt-3">
                <div class="col-auto">
                  <a class="btn btn-primary"
                  title="', _('Log out from Identity Provider'), '"
                  href="https://github.com/logout">',
                  _('(Optional) Log out from GitHub'), '</a>
                </div> <!-- end col-auto -->
              </div> <!-- end row align-items-center -->
            ';
        } elseif ($idp == Util::getOAuth2Url('ORCID')) {
            echo '
              <div class="card-text my-2" id="id-successfully-logged-out-4">
                ',
                _('You can optionally click the link below to log out of ORCID. ' .
                'Note that ORCID will redirect you to the ORCID Sign In page. ' .
                'You can ignore this as your authentication session with ORCID ' .
                'will have been cleared first.'), '
              </div>
              <div class="row align-items-center justify-content-center mt-3">
                <div class="col-auto">
                  <a class="btn btn-primary"
                  title="', _('Log out from Identity Provider'), '"
                  href="https://orcid.org/signout">',
                  _('(Optional) Log out from ORCID'), '</a>
                </div> <!-- end col-auto -->
              </div> <!-- end row align-items-center -->
            ';
        } elseif ($idp == Util::getOAuth2Url('Microsoft')) {
            echo '
              <div class="card-text my-2" id="id-successfully-logged-out-5">
                ',
                _('You can optionally click the link below to log out of Microsoft.' .
                'However, this will log you out from ALL of your Microsoft accounts.' .
                'Any current Microsoft sessions in other tabs/windows may be ' .
                'invalidated.'), '
              </div>
              <div class="row align-items-center justify-content-center mt-3">
                <div class="col-auto">
                  <a class="btn btn-primary"
                  title="', _('Log out from Identity Provider'), '"
                  href="https://login.microsoftonline.com/common/oauth2/v2.0/logout">',
                  _('(Optional) Log out from Microsoft'), '</a>
                </div> <!-- end col-auto -->
              </div> <!-- end row align-items-center -->
            ';
        } elseif (!empty($idp)) {
            if (empty($idp_display_name)) {
                $idp_display_name = _('your Identity Provider');
            }
            $idplist = Util::getIdpList();
            $logout = $idplist->getLogout($idp);
            if (empty($logout)) {
                echo '
              <div class="card-text my-2" id="id-successfully-logged-out-6">
                ',
                _('You may still be logged in to'), ' ', $idp_display_name,
                '. ', _('Close your web browser or'),
                ' <a target="_blank" ' .
                'href="https://www.lifewire.com/how-to-delete-cookies-2617981">',
                _('clear your cookies'), '</a> ',
                _('to clear your authentication session.'), '
              </div>
              ';
            } else {
                echo '
              <div class="card-text my-2" id="id-successfully-logged-out-7">
                ',
                _('You can optionally click the link below to log out of'),
                ' ', $idp_display_name, '. ',
                _('Note that some Identity Providers do not support log out. If you ' .
                'receive an error, close your web browser or'),
                ' <a target="_blank" ' .
                'href="https://www.lifewire.com/how-to-delete-cookies-2617981">',
                _('clear your cookies'), '</a> ',
                _('to clear your authentication session.'), '
              </div>
              <div class="row align-items-center justify-content-center mt-3">
                <div class="col-auto">
                  <a class="btn btn-primary"
                  title="', _('Log out from Identity Provider'), '"
                  href="', $logout, '">',
                  _('(Optional) Log out from'), ' ', $idp_display_name, '</a>
                </div> <!-- end col-auto -->
              </div> <!-- end row align-items-center -->
              ';
            }
        }

        echo '
            </div> <!-- end card-body -->
        ';

        static::printCollapseEnd();
        static::printFooter();
    }
}
