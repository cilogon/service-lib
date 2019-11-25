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
     * @param string $extra Optional extra text to go in the <head> block
     * @param bool $csrfcookie Set the CSRF cookie. Defaults to true.
     */
    public static function printHeader($title = '', $extra = '', $csrfcookie = true)
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
            (!is_null($skinpoweredbyimg)) &&
            (strlen($skinpoweredbyimg) > 0) &&
            (is_readable('/var/www/html' . $skinpoweredbyimg))
        ) {
            $poweredbyimg = $skinpoweredbyimg;
        }

        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
        <head><title>' , $title , '</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <meta name="viewport" content="initial-scale=0.6" />
        <link rel="stylesheet" type="text/css" href="/include/cilogon.css" />
        ';

        $skin->printSkinLink();

        echo '
        <script type="text/javascript" src="/include/cilogon.js"></script>
        ' ;

        echo '
    <!--[if IE]>
        <style type="text/css">
          body { behavior: url(/include/csshover3.htc); }
        </style>
    <![endif]-->
        ';

        if (strlen($extra) > 0) {
            echo $extra;
        }

        echo '
        </head>

        <body>

        <div class="skincilogonlogo">
        <a target="_blank" href="http://www.cilogon.org/faq/"><img
        src="' , $poweredbyimg , '" alt="CILogon"
        title="CILogon Service" /></a>
        </div>

        <div class="logoheader">
           <h1><span>[CILogon Service]</span></h1>
        </div>
        <div class="pagecontent">
         ';

        if ((defined('BANNER_TEXT')) && (!empty(BANNER_TEXT))) {
            echo '
            <div class="noticebanner">' , BANNER_TEXT , '</div>
            ';
        }

        $providerId = Util::getSessionVar('idp');
        if ($providerId == "urn:mace:incommon:idp.protectnetwork.org") {
            echo '
            <div class="noticebanner">Availability of the ProtectNetwork
            Identity Provider (IdP) will end after December 2014. Please
            consider using another IdP.</div>
            ';
        }
    }

    /**
     * printFooter
     *
     * This function should be called to print out the closing HTML block
     * for each web page.
     *
     * @param string $footer Optional extra text to be output before the
     * closing footer div.
     */
    public static function printFooter($footer = '')
    {
        if (strlen($footer) > 0) {
            echo $footer;
        }

        echo '
        <br class="clear" />
        <div class="footer">
        <a target="_blank" href="http://www.cilogon.org/faq"><img
        src="/images/questionIcon.png" class="floatrightclear"
        width="40" height="40" alt="CILogon FAQ" title="CILogon FAQ" /></a>
        <p>For questions about this site, please see the <a target="_blank"
        href="http://www.cilogon.org/faq">FAQs</a> or send email to <a
        href="mailto:help@cilogon.org">help&nbsp;@&nbsp;cilogon.org</a>.</p>
        <p>Know <a target="_blank"
        href="http://ca.cilogon.org/responsibilities">your responsibilities</a>
        for using the CILogon Service.</p>
        <p>See <a target="_blank"
        href="http://ca.cilogon.org/acknowledgements">acknowledgements</a> of
        support for this site.</p>
        </div> <!-- Close "footer" div -->
        </div> <!-- Close "pagecontent" div -->
        </body>
        </html>
        ';

        session_write_close();
    }

    /**
     * printPageHeader
     *
     * This function prints a fancy formatted box with a single line of
     * text, suitable for a titlebox on each web page (to appear just below
     * the page banner at the very top). It prints a gradent border around
     * the four edges of the box and then outlines the inner box.
     *
     * @param string $text The text string to appear in the titlebox.
     */
    public static function printPageHeader($text)
    {
        echo '
        <div class="titlebox">' , $text , '
        </div>
        ';
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
    public static function printFormHead(
        $action = '',
        $method = 'post'
    ) {
        static $formnum = 0;

        if (strlen($action) == 0) {
            $action = Util::getScriptDir();
        }

        echo '
        <form action="' , $action , '" method="' , $method , '"
         autocomplete="off" id="form' , sprintf("%02d", ++$formnum) , '">
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
     * @param bool $samlidps (Optional) Show all SAML-based IdPs in
     *        selection list? Defaults to false, which means show
     *        only whitelisted IdPs.
     */
    public static function printWAYF($showremember = true, $samlidps = false)
    {
        $helptext = 'Check this box to bypass the welcome page on ' .
            'subsequent visits and proceed directly to the selected ' .
            'identity provider. You will need to clear your browser\'s ' .
            'cookies to return here.';
        $searchtext = "Enter characters to search for in the list above.";

        // Get an array of IdPs
        $idps = static::getCompositeIdPList($samlidps);

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

        // Make sure previously selected IdP is in list of available IdPs.
        if ((strlen($providerId) > 0) && (!isset($idps[$providerId]))) {
            $providerId = '';
        }

        // If no previous providerId, get from skin, or default to Google.
        if (strlen($providerId) == 0) {
            $initialidp = (string)$skin->getConfigOption('initialidp');
            if ((!is_null($initialidp)) && (isset($idps[$initialidp]))) {
                $providerId = $initialidp;
            } else {
                $providerId = Util::getAuthzUrl('Google');
            }
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

        echo '
        <br />
        <div class="actionbox"';

        if (Util::getSessionVar('showhelp') == 'on') {
            echo ' style="width:92%;"';
        }

        echo '>
        <table class="helptable">
        <tr>
        <td class="actioncell">

          <form action="' , Util::getScriptDir() , '" method="post">
          <fieldset>

          <p>' , ($useselectedidp ? 'Selected' : 'Select An') ,
          ' Identity Provider:</p>
          ';

        // See if the skin has set a size for the IdP <select> list
        $selectsize = 4;
        $ils = $skin->getConfigOption('idplistsize');
        if ((!is_null($ils)) && ((int)$ils > 0)) {
            $selectsize = (int)$ils;
        }

        // When selected_idp is used, list size may be smaller
        if ($useselectedidp) {
            $selectsize = min($selectsize, count($idps));
        }

        echo '
          <p>
          <select name="providerId" id="providerId" size="' , $selectsize , '"
           onkeypress="enterKeySubmit(event)" ondblclick="doubleClickSubmit()"' ,
           // Hide the drop-down arrow in Firefox and Chrome
          ($useselectedidp ?
              'style="-moz-appearance:none;-webkit-appearance:none"' : '') ,
           '>
        ';

        foreach ($idps as $entityId => $names) {
            echo '    <option value="' , $entityId , '"';
            if ($entityId == $providerId) {
                echo ' selected="selected"';
            }
            echo '>' , Util::htmlent($names['Display_Name']) , '</option>' , "\n    ";
        }

        echo '  </select>
        </p>

        <p id="listsearch" class="zeroheight">
        <label for="searchlist" class="helpcursor" title="' ,
        $searchtext , '">Search:</label>
        <input type="text" name="searchlist" id="searchlist" value=""
        size="30" onkeyup="searchOptions(this.value)"
        title="' , $searchtext , '" />
    <!--[if IE]><input type="text" style="display:none;" disabled="disabled" size="1"/><![endif]-->
        </p>
        ';

        if ($showremember) {
            echo '
            <p>
            <label for="keepidp" title="' , $helptext ,
            '" class="helpcursor">Remember this selection:</label>
            <input type="checkbox" name="keepidp" id="keepidp" ' ,
            ((strlen($keepidp) > 0) ? 'checked="checked" ' : '') ,
            'title="' , $helptext , '" class="helpcursor" />
            </p>
            ';
        }

        echo '
        <p class="silvercheckbox">
        <label for="silveridp">Request Silver:</label>
        <input type="checkbox" name="silveridp" id="silveridp"/>
        </p>

        <p>
        ';

        echo Util::getCsrf()->hiddenFormElement();

        $lobtext = static::getLogOnButtonText();

        echo '
        <input type="submit" name="submit" class="submit helpcursor"
        title="Continue to the selected identity provider."
        value="' , $lobtext , '" id="wayflogonbutton" />
        <input type="hidden" name="previouspage" value="WAYF" />
        <input type="submit" name="submit" class="submit helpcursor"
        title="Cancel authentication and navigate away from this site."
        value="Cancel" id="wayfcancelbutton" />
        </p>
        ';

        $logonerror = Util::getSessionVar('logonerror');
        if (strlen($logonerror) > 0) {
            echo "<p class=\"logonerror\">$logonerror</p>";
            Util::unsetSessionVar('logonerror');
        }

        echo '
        <p class="privacypolicy">
        By selecting "' , $lobtext , '", you agree to <a target="_blank"
        href="http://ca.cilogon.org/policy/privacy">CILogon\'s privacy
        policy</a>.
        </p>

        </fieldset>

        </form>
      </td>
      ';

        if (Util::getSessionVar('showhelp') == 'on') {
            echo '
          <td class="helpcell">
          <div>
          ';

            if ($samlidps) { // SAML-based IdPs only means running from /testidp/
                echo '
                <p>
                CILogon facilitates secure access to CyberInfrastructure
                (<acronym title="CyberInfrastructure">CI</acronym>). In
                order to test your identity provider with the CILogon Service,
                you must first Log On. If your preferred identity provider is
                not listed, please contact <a
                href="mailto:help@cilogon.org">help@cilogon.org</a>, and
                we will try to add your identity provider in the future.
                </p>
                ';
            } else { // If not InCommon only, print help text for OpenID providers.
                echo '
                <p>
                CILogon facilitates secure access to CyberInfrastructure
                (<acronym title="CyberInfrastructure">CI</acronym>).
                In order to use the CILogon Service, you must first select
                an identity provider. An identity provider (IdP) is an
                organization where you have an account and can log on
                to gain access to online services.
                </p>
                <p>
                If you are a faculty, staff, or student member of a university
                or college, please select it for your identity provider.
                If your school is not listed, please contact <a
                href="mailto:help@cilogon.org">help@cilogon.org</a>, and we will
                try to add your school in the future.
                </p>
                ';

                $googleauthz = Util::getAuthzUrl('Google');
                if (isset($idps[$googleauthz])) {
                    echo '
                    <p>
                    If you have a <a target="_blank"
                    href="https://myaccount.google.com">Google</a>
                    account, you can select it for
                    authenticating to the CILogon Service.
                    </p>
                    ';
                }
                $githubauthz = Util::getAuthzUrl('GitHub');
                if (isset($idps[$githubauthz])) {
                    echo '
                    <p>
                    If you have a <a target="_blank"
                    href="https://github.com/settings/profile">GitHub</a>
                    account, you can select it for
                    authenticating to the CILogon Service.
                    </p>
                    ';
                }
                $orcidauthz = Util::getAuthzUrl('ORCID');
                if (isset($idps[$orcidauthz])) {
                    echo '
                    <p>
                    If you have a <a target="_blank"
                    href="https://orcid.org/my-orcid">ORCID</a>
                    account, you can select it for
                    authenticating to the CILogon Service.
                    </p>
                    ';
                }
            }

            echo '
          </div>
          </td>
          ';
        }
        echo '
      </tr>
      </table>
      </div>
      ';
    }

    /**
     * printCertInfo
     *
     * This function prints information related to the X.509 certificate
     * such as DN (distinguished name) and LOA (level of assurance).
     */
    public static function printCertInfo()
    {
        $dn = Util::getSessionVar('dn');
        // Strip off the email address from the pseudo-DN.
        $dn = Content::reformatDN(preg_replace('/\s+email=.+$/', '', $dn));

        echo '
        <table class="certinfo">
          <tr>
            <th>Certificate&nbsp;Subject:</th>
            <td>' , Util::htmlent($dn) , '</td>
          </tr>
          <tr>
            <th>Identity&nbsp;Provider:</th>
            <td>' , Util::getSessionVar('idpname') , '</td>
          </tr>
          <tr>
            <th><a target="_blank"
            href="http://ca.cilogon.org/loa">Level&nbsp;of&nbsp;Assurance:</a></th>
            <td>
        ';

        $loa = Util::getSessionVar('loa');
        if ($loa == 'openid') {
            echo '<a href="http://ca.cilogon.org/policy/openid"
                  target="_blank">OpenID</a>';
        } elseif ($loa == 'http://incommonfederation.org/assurance/silver') {
            echo '<a href="http://ca.cilogon.org/policy/silver"
                  target="_blank">Silver</a>';
        } else {
            echo '<a href="http://ca.cilogon.org/policy/basic"
                  target="_blank">Basic</a>';
        }
        echo '
            </td>
          </tr>
        </table>
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
        $idplist = Util::getIdpList();
        $idp = Util::getSessionVar('idp');
        $gotattrs = Util::gotUserAttributes();
        $samlidp = ((!empty($idp)) && (!$idplist->isOAuth2($idp)));

        echo '
        <div class="summary">
            <div id="userattrs1" style="display:' ,
            ($gotattrs ? "inline" : "none") ,
            '"><span class="expander"><a
            href="javascript:showHideDiv(\'userattrs\',-1)"><img
            src="/images/triright.gif" alt="&rArr;" width="14" height="14" />
            User Attributes</a></span>';

        // CIL-416 Show warning for missing ePPN
        if (
            ($samlidp) &&
            (empty(Util::getSessionVar('ePPN'))) &&
            (!empty(Util::getSessionVar('ePTID')))
        ) {
            Content::printIcon('warn', 'Some CILogon clients (e.g., Globus) require ePPN.');
        }

        echo '
            </div>
            <div id="userattrs2" style="display:' ,
                ($gotattrs ? "none" : "inline") ,
            '"><span class="expander"><a
            href="javascript:showHideDiv(\'userattrs\',-1)"><img
            src="/images/tridown.gif" alt="&dArr;" width="14" height="14" />
            User Attributes</a></span>
            </div>
            <br class="clear" />
            <div id="userattrs3" style="display:' ,
                ($gotattrs ? "none" : "inline") ,
            '">

            <table cellpadding="5">
              <tr class="odd">
                <th>Identity Provider (entityID):</th>
                <td>' , $idp , '</td>
                <td>';

        if (empty($idp)) {
            Content::printIcon('error', 'Missing the entityID of the IdP.');
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>ePTID:</th>
                <td>' , Util::getSessionVar('ePTID') , '</td>
                <td>';

        if (
            ($samlidp) &&
            (empty(Util::getSessionVar('ePPN'))) &&
            (empty(Util::getSessionVar('ePTID')))
        ) {
            Content::printIcon('error', 'Must have either ePPN -OR- ePTID.');
        }

        echo '
                </td>
              </tr>

              <tr class="odd">
                <th>ePPN:</th>
                <td>' , Util::getSessionVar('ePPN') , '</td>
                <td>';

        if (($samlidp) && empty(Util::getSessionVar('ePPN'))) {
            if (empty(Util::getSessionVar('ePTID'))) {
                Content::printIcon('error', 'Must have either ePPN -OR- ePTID.');
            } else {
                Content::printIcon('warn', 'Some CILogon clients (e.g., Globus) require ePPN.');
            }
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>OpenID:</th>
                <td>' , Util::getSessionVar('oidcID'), '</td>
                <td>';

        if (
            (!empty($idp)) &&
            (!$samlidp) &&
            empty(Util::getSessionVar('oidcID'))
        ) {
            Content::printIcon('error', 'Missing the OpenID identifier.');
        }

        echo '
                </td>
              </tr>

              <tr class="odd">
                <th>First Name (givenName):</th>
                <td>' ,Util::getSessionVar('firstname') , '</td>
                <td>';

        if (
            (empty(Util::getSessionVar('firstname'))) &&
            (empty(Util::getSessionVar('displayname')))
        ) {
            Content::printIcon('error', 'Must have either givenName + sn -OR- displayName.');
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>Last Name (sn):</th>
                <td>' , Util::getSessionVar('lastname') , '</td>
                <td>';

        if (
            (empty(Util::getSessionVar('lastname'))) &&
            (empty(Util::getSessionVar('displayname')))
        ) {
            Content::printIcon('error', 'Must have either givenName + sn -OR- displayName.');
        }

        echo '
                </td>
              </tr>

              <tr class="odd">
                <th>Display Name (displayName):</th>
                <td>' , Util::getSessionVar('displayname') , '</td>
                <td>';

        if (
            (empty(Util::getSessionVar('displayname'))) &&
            ((empty(Util::getSessionVar('firstname'))) ||
            (empty(Util::getSessionVar('lastname'))))
        ) {
            Content::printIcon('error', 'Must have either displayName -OR- givenName + sn.');
        }

        echo '
                </td>
              </tr>

              <tr>
                <th>Email Address (email):</th>
                <td>' , Util::getSessionVar('emailaddr') , '</td>
                <td>';

        $emailvalid = filter_var(Util::getSessionVar('emailaddr'), FILTER_VALIDATE_EMAIL);
        if ((empty(Util::getSessionVar('emailaddr'))) || (!$emailvalid)) {
            Content::printIcon('error', 'Missing valid email address.');
        }

        echo '
                </td>
              </tr>

              <tr class="odd">
                <th>Level of Assurance (assurance):</th>
                <td>' , Util::getSessionVar('loa') , '</td>
                <td> </td>
              </tr>

              <tr>
                <th>AuthnContextClassRef:</th>
                <td>' , Util::getSessionVar('acr') , '</td>
                <td> </td>
              </tr>

              <tr class="odd">
                <th>Affiliation (affiliation):</th>
                <td>' , Util::getSessionVar('affiliation') , '</td>
                <td> </td>
              </tr>

              <tr>
                <th>Entitlement (entitlement):</th>
                <td>' , Util::getSessionVar('entitlement') , '</td>
                <td> </td>
              </tr>

              <tr class="odd">
                <th>Organizational Unit (ou):</th>
                <td>' , Util::getSessionVar('ou') , '</td>
                <td> </td>
              </tr>

              <tr>
                <th>Member (member):</th>
                <td>' , Util::getSessionVar('memberof') , '</td>
                <td> </td>
              </tr>

              <tr class="odd">
                <th>iTrustUIN (itrustuin):</th>
                <td>' , Util::getSessionVar('itrustuin') , '</td>
                <td> </td>
              </tr>


            </table>
            </div> <!-- userattrs3 -->
        </div> <!-- summary -->
        ';
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
        $gotattrs = Util::gotUserAttributes();
        $samlidp = ((!empty($idp)) && (!$idplist->isOAuth2($idp)));
        $shibarray = $idplist->getShibInfo($idp);

        echo '
        <div class="summary">
            <div id="meta1" style="display:' ,
                ($gotattrs ? "inline" : "none") ,
            '"><span class="expander"><a
            href="javascript:showHideDiv(\'meta\',-1)"><img
            src="/images/triright.gif" alt="&rArr;" width="14" height="14" />
            Identity Provider Attributes</a></span>';

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

        if ($eduGainWithoutRandSandSIRTFI) {
            Content::printIcon('warn', 'This IdP does not support both ' .
                'REFEDS R&amp;S and SIRTFI. CILogon functionality may be limited.');
        }

        echo '
            </div>
            <div id="meta2" style="display:' ,
                ($gotattrs ? "none" : "inline") ,
            '"><span class="expander"><a
            href="javascript:showHideDiv(\'meta\',-1)"><img
            src="/images/tridown.gif" alt="&dArr;" width="14" height="14" />
            Identity Provider Attributes</a></span>
            </div>
            <br class="clear" />
            <div id="meta3" style="display:' ,
                ($gotattrs ? "none" : "inline") ,
            '">

            <table cellpadding="5">
              <tr class="odd">
                <th>Organization Name:</th>
                <td>' , @$shibarray['Organization Name'] , '</td>
                <td>';

        if (empty(@$shibarray['Organization Name'])) {
            Content::printIcon('error', 'Could not find ' .
                '&lt;OrganizationDisplayName&gt; in metadata.');
        }

        echo '
                </td>
              </tr>
              <tr>
                <th>Home Page:</th>
                <td><a target="_blank" href="' , @$shibarray['Home Page'] , '">' ,
                @$shibarray['Home Page'] , '</a></td>
                <td> </td>
              </tr>

              <tr class="odd">
                <th>Support Contact:</th>
        ';
        if (
            (!empty(@$shibarray['Support Name'])) ||
            (!empty(@$shibarray['Support Address']))
        ) {
            echo '
                <td>' , @$shibarray['Support Name'] , ' &lt;' ,
                        preg_replace('/^mailto:/', '', @$shibarray['Support Address']) , '&gt;</td>
                <td> </td>';
        }
        echo '
              </tr>

        ';

        if ($samlidp) {
            echo '
                  <tr>
                    <th>Technical Contact:</th>
            ';
            if (
                (!empty(@$shibarray['Technical Name'])) ||
                (!empty(@$shibarray['Technical Address']))
            ) {
                echo '
                    <td>' , @$shibarray['Technical Name'] , ' &lt;' ,
                            preg_replace('/^mailto:/', '', @$shibarray['Technical Address']) , '&gt;</td>
                    <td> </td>';
            }
            echo '
                  </tr>

                  <tr class="odd">
                    <th>Administrative Contact:</th>
            ';
            if (
                (!empty(@$shibarray['Administrative Name'])) ||
                (!empty(@$shibarray['Administrative Address']))
            ) {
                echo '
                    <td>' , @$shibarray['Administrative Name'] , ' &lt;' ,
                            preg_replace('/^mailto:/', '', @$shibarray['Administrative Address']) , '&gt;</td>
                    <td> </td>';
            }
            echo '
                  </tr>

                  <tr>
                    <th>Registered by InCommon:</th>
                    <td>' , ($idplist->isRegisteredByInCommon($idp) ? 'Yes' : 'No') , '</td>
                    <td> </td>
                  </tr>

                  <tr class="odd">
                    <th><a style="text-decoration:underline" target="_blank"
                    href="http://id.incommon.org/category/research-and-scholarship">InCommon R
                    &amp; S</a>:</th>
                    <td>' , ($idplist->isInCommonRandS($idp) ? 'Yes' : 'No') , '</td>
                    <td> </td>
                  </tr>

                  <tr>
                    <th><a style="text-decoration:underline" target="_blank"
                    href="http://refeds.org/category/research-and-scholarship">REFEDS
                    R &amp; S</a>:</th>
                    <td>' , ($idplist->isREFEDSRandS($idp) ? 'Yes' : 'No') , '</td>
                    <td>';
            if (
                ($eduGainWithoutRandSandSIRTFI &&
                !$idplist->isREFEDSRandS($idp))
            ) {
                Content::printIcon(
                    'warn',
                    'This IdP does not support both ' .
                    'REFEDS R&amp;S and SIRTFI. ' .
                    'CILogon functionality may be limited.'
                );
            }

            echo '              </td>
                  </tr>

                  <tr class="odd">
                    <th><a style="text-decoration:underline" target="_blank"
                           href="https://refeds.org/sirtfi">SIRTFI</a>:</th>
                    <td>' , ($idplist->isSIRTFI($idp) ? 'Yes' : 'No') , '</td>
                    <td>';

            if (
                ($eduGainWithoutRandSandSIRTFI &&
                !$idplist->isSIRTFI($idp))
            ) {
                Content::printIcon(
                    'warn',
                    'This IdP does not support both ' .
                    'REFEDS R&amp;S and SIRTFI. ' .
                    'CILogon functionality may be limited.'
                );
            }

            echo '              </td>
                  </tr>

                  <tr>
                    <th><a style="text-decoration:underline" target="_blank"
                    href="http://id.incommon.org/assurance/bronze">InCommon Bronze</a>:</th>
                    <td>' , ($idplist->isBronze($idp) ? 'Yes' : 'No') , '</td>
                    <td> </td>
                  </tr>

                  <tr class="odd">
                    <th><a style="text-decoration:underline" target="_blank"
                    href="http://id.incommon.org/assurance/silver">InCommon Silver</a>:</th>
                    <td>' , ($idplist->isSilver($idp) ? 'Yes' : 'No') , '</td>
                    <td> </td>
                  </tr>

                  <tr>
                    <th>Entity ID</th>
                    <td><a style="text-decoration:underline" target="_blank"
                    href="https://met.refeds.org/met/entity/',
                    rawurlencode($idp),
                    '">', $idp, '</td>
                    <td> </td>
                  </tr>
                  ';
        } // end if ($samlidp)
        echo '
              </table>
            </div>  <!-- meta3 -->
        </div>  <!-- summary -->
        ';
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
        $pn = $pc->getPortalName();
        if (strlen(Util::getPostVar('keepidp')) > 0) {
            if (strlen($pn) > 0) {
                $pc->set('keepidp', 'checked');
            } else {
                Util::setCookieVar('keepidp', 'checked');
            }
        } else {
            if (strlen($pn) > 0) {
                $pc->set('keepidp', '');
            } else {
                Util::unsetCookieVar('keepidp');
            }
        }

        // Get the user-chosen IdP from the posted form
        $providerId = Util::getPostVar('providerId');

        // Set the cookie for the last chosen IdP and redirect to it if in list
        if ((strlen($providerId) > 0) && (isset($idps[$providerId]))) {
            if (strlen($pn) > 0) {
                $pc->set('providerId', $providerId);
                $pc->write();
            } else {
                Util::setCookieVar('providerId', $providerId);
            }
            $providerName = Util::getAuthzIdP($providerId);
            if (in_array($providerName, Util::$oauth2idps)) {
                // Log in with an OAuth2 IdP
                static::redirectToGetOAuth2User($providerId);
            } else { // Use InCommon authn
                static::redirectToGetShibUser($providerId);
            }
        } else { // IdP not in list, or no IdP selected
            if (strlen($pn) > 0) {
                $pc->set('providerId', '');
                $pc->write();
            } else {
                Util::unsetCookieVar('providerId');
            }
            Util::setSessionVar('logonerror', 'Please select a valid IdP.');
            printLogonPage();
        }
    }

    /**
     * handleHelpButtonClicked
     *
     * This function is called when the user clicks on the 'Show Help' /
     * 'Hide Help' button in the upper right corner of the page. It toggles
     * the 'showhelp' session variable and redisplays the appropriate page
     * with help now shown or hidden.
     */
    public static function handleHelpButtonClicked()
    {
        if (Util::getSessionVar('showhelp') == 'on') {
            Util::unsetSessionVar('showhelp');
        } else {
            Util::setSessionVar('showhelp', 'on');
        }

        $stage = Util::getSessionVar('stage');
        if (static::verifyCurrentUserSession()) {
            if ($stage == 'main') {
                printMainPage();
            } else {
                printLogonPage();
            }
        } else {
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
            // then check for a match in the BYPASS_IDP_ARRAY to see if we
            // should automatically redirect to a specific IdP. Used mainly
            // by campus gateways.
            $bypassidp = '';
            foreach (BYPASS_IDP_ARRAY as $key => $value) {
                if (
                    (preg_match($key, $redirect_uri)) ||
                    (preg_match($key, $client_id))
                ) {
                    $bypassidp = $value;
                    break;
                }
            }

            // CIL-613 - Next, check for a match in the ALLOW_BYPASS_ARRAY.
            // If found, then allow the idphint/selected_idp to be used as the
            // IdP to redirect to.
            if (empty($bypassidp) && (!empty($selected_idp))) {
                foreach (ALLOW_BYPASS_ARRAY as $value) {
                    if (
                        (preg_match($value, $redirect_uri)) ||
                        (preg_match($value, $client_id))
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

        // If both 'keepidp' and 'providerId' were set (and the
        // providerId is a whitelisted IdP or valid OpenID provider),
        // then skip the Logon page and proceed to the appropriate
        // getuser script.
        if ((strlen($providerId) > 0) && (strlen($keepidp) > 0)) {
            // If selected_idp was specified at the OIDC authorize endpoint,
            // make sure that it matches the saved providerId. If not,
            // then show the Logon page and uncheck the keepidp checkbox.
            if ((strlen($selected_idp) == 0) || ($selected_idp == $providerId)) {
                $providerName = Util::getAuthzIdP($providerId);
                if (in_array($providerName, Util::$oauth2idps)) {
                    // Log in with an OAuth2 IdP
                    static::redirectToGetOAuth2User($providerId);
                } elseif (Util::getIdpList()->exists($providerId)) {
                    // Log in with InCommon
                    static::redirectToGetShibUser($providerId);
                } else { // $providerId not in whitelist
                    if (strlen($pn) > 0) {
                        $pc->set('providerId', '');
                        $pc->write();
                    } else {
                        Util::unsetCookieVar('providerId');
                    }
                    printLogonPage();
                }
            } else { // selected_idp does not match saved providerId
                if (strlen($pn) > 0) {
                    $pc->set('keepidp', '');
                    $pc->write();
                } else {
                    Util::unsetCookieVar('keepidp');
                }
                printLogonPage();
            }
        } else { // One of providerId or keepidp was not set
            printLogonPage();
        }
    }

    /**
     * printIcon
     *
     * This function prints out the HTML for the little icons which can
     * appear inline with other information.  This is accomplished via the
     * use of wrapping the image in a <span> tag.
     *
     * @param string $icon The prefix of the '...Icon.png' image to be
     *        shown. E.g., to show 'errorIcon.png', pass in 'error'.
     * @param string $popuptext (Optionals) The popup 'title' text to be
     *        displayed when the  mouse cursor hovers over the icon.
     *        Defaults to empty string.
     * @param string $class (Optionals) A CSS class for the icon. Will be
     *        appended after the 'helpcursor' class. Defaults to empty
     *        string.
     */
    public static function printIcon($icon, $popuptext = '', $class = '')
    {
        echo '<span';
        if (strlen($popuptext) > 0) {
            echo ' class="helpcursor ' , $class , '" title="' , $popuptext , '"';
        }
        echo '>&nbsp;<img src="/images/' , $icon , 'Icon.png"
              alt="&laquo; ' , ucfirst($icon) , '"
              width="14" height="14" /></span>';
    }

    /**
     * printHelpButton
     *
     * This function prints the 'Show Help' / 'Hide Help' button in the
     * upper-right corner of the main box area on the page.
     */
    public static function printHelpButton()
    {
        echo '
        <div class="helpbutton">
        ';

        static::printFormHead();

        echo '
          <input type="submit" name="submit" class="helpbutton" value="' ,
          (Util::getSessionVar('showhelp') == 'on' ? 'Hide' : 'Show') , '&#10; Help " />
          </form>
        </div>
        ';
    }

    /**
     * verifyCurrentUserSession
     *
     * This function verifies the contents of the PHP session.  It checks
     * the following:
     * (1) The persistent store 'uid', the Identity Provider 'idp', the
     *     IdP Display Name 'idpname', and the 'status' (of getUser()) are
     *     all non-empty strings.
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
        $idpname   = Util::getSessionVar('idpname');
        $uid       = Util::getSessionVar('uid');
        $status    = Util::getSessionVar('status');
        $dn        = Util::getSessionVar('dn');
        $authntime = Util::getSessionVar('authntime');

        // CIL-410 When using the /testidp/ flow, the 'storeattributes'
        // session var is set. In this case, the only attribute that
        // is needed is 'idp' (entityID).
        if (Util::getSessionVar('storeattributes') == '1') {
            if (strlen($idp) > 0) {
                $retval = true;
            }
        } elseif (
            (strlen($uid) > 0) && (strlen($idp) > 0) &&
            (strlen($idpname) > 0) && (strlen($status) > 0) &&
            (strlen($dn) > 0) && (strlen($authntime) > 0) &&
            (!($status & 1)) // All STATUS_OK codes are even
        ) {
            // Check for eduGAIN IdP and possible get cert context
            if (Util::isEduGAINAndGetCert()) {
                Util::unsetUserSessionVars();
            } elseif ((strlen($providerId) == 0) || ($providerId == $idp)) {
                // If $providerId passed in, make sure it matches the $idp
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
     * If the first parameter (a whitelisted entityId) is not specified,
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
     * @param bool $allowsilver Is it okay to request silver assurance in
     *        the authnContextClassRef? If not, then ignore the 'Request
     *        Silver' checkbox and silver certification in metadata.
     *        Defaults to true.
     */
    public static function redirectToGetShibUser(
        $providerId = '',
        $responsesubmit = 'gotuser',
        $responseurl = null,
        $allowsilver = true
    ) {
        // If providerId not set, try the cookie value
        if (strlen($providerId) == 0) {
            $providerId = Util::getPortalOrNormalCookieVar('providerId');
        }

        // If the user has a valid 'uid' in the PHP session, and the
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

                // If Silver IdP or 'Request Silver' checked, send extra parameter
                if ($allowsilver) {
                    if (
                        (Util::getIdpList()->isSilver($providerId)) ||
                        (strlen(Util::getPostVar('silveridp')) > 0)
                    ) {
                        Util::setSessionVar('requestsilver', '1');
                        $redirect .= '&authnContextClassRef=' .
                            urlencode('http://id.incommon.org/assurance/silver');
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
            $providerId = Util::getPortalOrNormalCookieVar('providerId');
        }

        // If the user has a valid 'uid' in the PHP session, and the
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
        <div class="errorbox">
        <table cellpadding="5">
        <tr>
        <td valign="top">
        ';
        static::printIcon('error');
        echo '&nbsp;
        </td>
        <td> ' , $errortext , '
        </td>
        </tr>
        </table>
        </div>
        ';
    }

    /**
     * handleGotUser
     *
     * This function is called upon return from one of the getuser scripts
     * which should have set the 'uid' and 'status' PHP session variables.
     * It verifies that the status return is one of STATUS_OK (even
     * values).  If not, we print an error message to the user.
     */
    public static function handleGotUser()
    {
        $log = new Loggit();
        $uid = Util::getSessionVar('uid');
        $status = Util::getSessionVar('status');

        // We must get and unset session vars BEFORE any HTML output since
        // a redirect may go to another site, meaning we need to update
        // the session cookie before we leave the cilogon.org domain.
        $ePPN         = Util::getSessionVar('ePPN');
        $ePTID        = Util::getSessionVar('ePTID');
        $firstname    = Util::getSessionVar('firstname');
        $lastname     = Util::getSessionVar('lastname');
        $displayname  = Util::getSessionVar('displayname');
        $emailaddr    = Util::getSessionVar('emailaddr');
        $idp          = Util::getSessionVar('idp');
        $idpname      = Util::getSessionVar('idpname');
        $affiliation  = Util::getSessionVar('affiliation');
        $ou           = Util::getSessionVar('ou');
        $memberof     = Util::getSessionVar('memberof');
        $acr          = Util::getSessionVar('acr');
        $entitlement  = Util::getSessionVar('entitlement');
        $itrustuin    = Util::getSessionVar('itrustuin');
        $clientparams = json_decode(Util::getSessionVar('clientparams'), true);
        $failureuri   = Util::getSessionVar('failureuri');

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

        // If empty 'uid' or 'status' or odd-numbered status code, error!
        if ((strlen($uid) == 0) || (strlen($status) == 0) || ($status & 1)) {
            // Got all session vars by now, so okay to unset.
            Util::unsetAllUserSessionVars();

            $log->error('Failed to getuser.');

            static::printHeader('Error Logging On');

            echo '
            <div class="boxed">
            ';

            if ($status == DBService::$STATUS['STATUS_MISSING_PARAMETER_ERROR']) {
                // Check if the problem IdP was an OAuth2 IdP;
                // probably no first/last name
                if ($idpname == 'Google') {
                    static::printErrorBox('
                    <p>
                    There was a problem logging on. It appears that you have
                    attempted to use Google as your identity provider, but your
                    name or email address was missing. To rectify this problem,
                    go to the <a target="_blank"
                    href="https://myaccount.google.com/privacy#personalinfo">Google
                    Account Personal Information page</a>, and enter your first
                    name, last name, and email address. (All other Google
                    account information is not required by the CILogon Service.)
                    </p>
                    <p>
                    After you have updated your Google account profile, click
                    the "Proceed" button below and attempt to log on
                    with your Google account again. If you have any questions,
                    please contact us at the email address at the bottom of the
                    page.</p>
                    ');

                    echo '
                    <div>
                    ';
                    static::printFormHead($redirect, 'get');
                    echo '
                    <p class="centered">
                    <input type="hidden" name="providerId" value="' ,
                    Util::getAuthzUrl('Google') , '" /> ' , $redirectform , '
                    <input type="submit" name="submit" class="submit"
                    value="Proceed" />
                    </p>
                    </form>
                    </div>
                    ';
                } elseif ($idpname == 'GitHub') {
                    static::printErrorBox('
                    <p>
                    There was a problem logging on. It appears that you have
                    attempted to use GitHub as your identity provider, but your
                    name or email address was missing. To rectify this problem,
                    go to the <a target="_blank"
                    href="https://github.com/settings/profile">GitHub
                    Public Profile page</a>, and enter your name and email address.
                    (All other GitHub account information is not required by
                    the CILogon Service.)
                    </p>
                    <p>
                    After you have updated your GitHub account profile, click
                    the "Proceed" button below and attempt to log on
                    with your GitHub account again. If you have any questions,
                    please contact us at the email address at the bottom of the
                    page.</p>
                    ');

                    echo '
                    <div>
                    ';
                    static::printFormHead($redirect, 'get');
                    echo '
                    <p class="centered">
                    <input type="hidden" name="providerId" value="' ,
                    Util::getAuthzUrl('GitHub') , '" /> ' , $redirectform , '
                    <input type="submit" name="submit" class="submit"
                    value="Proceed" />
                    </p>
                    </form>
                    </div>
                    ';
                } elseif ($idpname == 'ORCID') {
                    static::printErrorBox('
                    <p>
                    There was a problem logging on. It appears that you have
                    attempted to use ORCID as your identity provider, but your
                    name or email address was missing. To rectify this problem,
                    go to your <a target="_blank"
                    href="https://orcid.org/my-orcid">ORCID
                    Profile page</a>, enter your name and email address, and
                    make sure they can be viewed by Everyone.
                    (All other ORCID account information is not required by
                    the CILogon Service.)
                    </p>
                    <p>
                    After you have updated your ORCID account profile, click
                    the "Proceed" button below and attempt to log on
                    with your ORCID account again. If you have any questions,
                    please contact us at the email address at the bottom of the
                    page.</p>
                    ');

                    echo '
                    <div>
                    ';
                    static::printFormHead($redirect, 'get');
                    echo '
                    <p class="centered">
                    <input type="hidden" name="providerId" value="' ,
                    Util::getAuthzUrl('ORCID') , '" /> ' , $redirectform , '
                    <input type="submit" name="submit" class="submit"
                    value="Proceed" />
                    </p>
                    </form>
                    </div>
                    ';
                } else { // Problem was missing SAML attribute from Shib IdP
                    static::printAttributeReleaseErrorMessage(
                        $ePPN,
                        $ePTID,
                        $firstname,
                        $lastname,
                        $displayname,
                        $emailaddr,
                        $idp,
                        $idpname,
                        $affiliation,
                        $ou,
                        $memberof,
                        $acr,
                        $entitlement,
                        $itrustuin,
                        $clientparams,
                        $redirect,
                        $redirectform,
                        Util::isEduGAINAndGetCert($idp, $idpname)
                    );
                }
            } else {
                static::printErrorBox('An internal error has occurred. System
                    administrators have been notified. This may be a temporary
                    error. Please try again later, or contact us at the the email
                    address at the bottom of the page.');

                echo '
                <div>
                ';
                static::printFormHead($redirect, 'get');
                echo $redirectform , '
                <input type="submit" name="submit" class="submit" value="Proceed" />
                </form>
                </div>
                ';
            }

            echo '
            </div>
            ';
            static::printFooter();
        } elseif (Util::isEduGAINAndGetCert($idp, $idpname)) {
            // If eduGAIN IdP and session can get a cert, then error!
            // Got all session vars by now, so okay to unset.
            Util::unsetAllUserSessionVars();

            $log->error('Failed to getuser due to eduGAIN IdP restriction.');

            static::printHeader('Error Logging On');

            echo '
            <div class="boxed">
            ';
            static::printAttributeReleaseErrorMessage(
                $ePPN,
                $ePTID,
                $firstname,
                $lastname,
                $displayname,
                $emailaddr,
                $idp,
                $idpname,
                $affiliation,
                $ou,
                $memberof,
                $acr,
                $entitlement,
                $itrustuin,
                $clientparams,
                $redirect,
                $redirectform,
                true
            );

            echo '
            </div>
            ';
            static::printFooter();
        } else { // Got one of the STATUS_OK status codes
            // Extra security check: Once the user has successfully authenticated
            // with an IdP, verify that the chosen IdP was actually whitelisted.
            // If not, then set error message and show Select an Identity Provider
            // page again.
            Util::getSkin()->init();  // Check for forced skin
            $idps = static::getCompositeIdPList();
            $providerId = Util::getSessionVar('idp');
            if ((strlen($providerId) > 0) && (!isset($idps[$providerId]))) {
                Util::setSessionVar(
                    'logonerror',
                    'Invalid IdP selected. Please try again.'
                );
                Util::sendErrorAlert(
                    'Authentication attempt using non-whitelisted IdP',
                    'A user successfully authenticated with an IdP, however, the
selected IdP was not in the list of whitelisted IdPs as determined
by the current skin. This might indicate the user attempted to
circumvent the security check in "handleGotUser()" for valid
IdPs for the skin.'
                );
                Util::unsetCookieVar('providerId');
                Util::unsetAllUserSessionVars();
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

        if (
            ($status == DBService::$STATUS['STATUS_NEW_USER']) &&
            ((strlen($callbackuri) > 0) ||
             (isset($clientparams['code'])))
        ) {
            // Extra check for new users: see if any HTML entities
            // are in the user name. If so, send an email alert.
            $dn = Util::getSessionVar('dn');
            $dn = static::reformatDN(preg_replace('/\s+email=.+$/', '', $dn));
            $htmldn = Util::htmlent($dn);
            if (strcmp($dn, $htmldn) != 0) {
                Util::sendErrorAlert(
                    'New user DN contains HTML entities',
                    "htmlentites(DN) = $htmldn\n"
                );
            }
        }

        // For a new user, or if the user got new attributes, just log it.
        // Then proceed to the Main Page.
        if ($status == DBService::$STATUS['STATUS_NEW_USER']) {
            $log->info('New User.');
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
            static::getMinMaxLifetimes('pkcs12', 9516);
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

        // Set the port based on the Level of Assurance
        $port = 7512;
        $loa = Util::getSessionVar('loa');
        if ($loa == 'http://incommonfederation.org/assurance/silver') {
            $port = 7514;
        } elseif ($loa == 'openid') {
            $port = 7516;
        }

        $dn = Util::getSessionVar('dn');
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
                $port,
                $lifetime,
                '/var/www/config/hostcred.pem',
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
                // Create a temporary directory in /var/www/html/pkcs12/
                $tdirparent = '/var/www/html/pkcs12/';
                $polonum = '3';   // Prepend the polo? number to directory
                if (preg_match('/(\d+)\./', php_uname('n'), $polomatch)) {
                    $polonum = $polomatch[1];
                }
                $tdir = Util::tempDir($tdirparent, $polonum, 0770);
                $p12dir = str_replace($tdirparent, '', $tdir);
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
                    $log->info('USAGE email="' .
                        Util::getSessionVar('emailaddr') . '" client="PKCS12"');
                } else { // Empty or missing usercred.p12 file - shouldn't happen!
                    Util::setSessionVar(
                        'p12error',
                        'Error creating certificate. Please try again.'
                    );
                    Util::deleteDir($tdir); // Remove the temporary directory
                    $log->info('Error creating certificate - missing usercred.p12');
                }
            } else { // The myproxy-logon command failed - shouldn't happen!
                Util::setSessionVar(
                    'p12error',
                    'Error! MyProxy unable to create certificate.'
                );
                $log->info('Error creating certificate - myproxy-logon failed');
            }
        } else { // Couldn't find the 'dn' PHP session value - shouldn't happen!
            Util::setSessionVar(
                'p12error',
                'Missing username. Please enable cookies.'
            );
            $log->info('Error creating certificate - missing dn session variable');
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
     * getSerialStringFromDN
     *
     * This function takes in a CILogon subject DN and returns just the
     * serial string part (e.g., A325). This function is needed since the
     * serial_string is not stored in the PHP session as a separate
     * variable since it is always available in the 'dn' session variable.
     *
     * @param string $dn The certificate subject DN (typically found in the
     *        session 'dn' variable)
     * @return string The serial string extracted from the subject DN, or
     *         empty string if DN is empty or wrong format.
     */
    public static function getSerialStringFromDN($dn)
    {
        $serial = ''; // Return empty string upon error

        // Strip off the email address, if present
        $dn = preg_replace('/\s+email=.+$/', '', $dn);
        // Find the 'CN=' entry
        if (preg_match('%/DC=org/DC=cilogon/C=US/O=.*/CN=(.*)%', $dn, $match)) {
            $cn = $match[1];
            if (preg_match('/\s+([^\s]+)$/', $cn, $match)) {
                $serial = $match[1];
            }
        }
        return $serial;
    }

    /**
     * getEmailFromDN
     *
     * This function takes in a CILogon subject DN and returns just the
     * email address part. This function is needed since the email address
     * is not stored in the PHP session as a separate variable since it is
     * always available in the 'dn' session variable.
     *
     * @param string $dn The certificate subject DN (typically found in the
     *        session 'dn' variable)
     * @return string The email address extracted from the subject DN, or
     *         empty string if DN is empty or wrong format.
     */
    public static function getEmailFromDN($dn)
    {
        $email = ''; // Return empty string upon error
        if (preg_match('/\s+email=(.+)$/', $dn, $match)) {
            $email = $match[1];
        }
        return $email;
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
        if (!is_null($dnformat)) {
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
     * getMinMaxLifetimes
     *
     * This function checks the skin's configuration to see if either or
     * both of minlifetime and maxlifetime in the specified config.xml
     * block have been set. If not, default to minlifetime of 1 (hour) and
     * the specified defaultmaxlifetime.
     *
     * @param string $section The XML section block from which to read the
     *        minlifetime and maxlifetime values. Can be one of the
     *        following: 'pkcs12' or 'delegate'.
     * @param int $defaultmaxlifetime Default maxlifetime (in hours) for the
     *        credential.
     * @return array An array consisting of two entries: the minimum and
     *         maximum lifetimes (in hours) for a credential.
     */
    public static function getMinMaxLifetimes($section, $defaultmaxlifetime)
    {
        $minlifetime = 1;    // Default minimum lifetime is 1 hour
        $maxlifetime = $defaultmaxlifetime;
        $skin = Util::getSkin();
        $skinminlifetime = $skin->getConfigOption($section, 'minlifetime');
        // Read the skin's minlifetime value from the specified section
        if ((!is_null($skinminlifetime)) && ((int)$skinminlifetime > 0)) {
            $minlifetime = max($minlifetime, (int)$skinminlifetime);
            // Make sure $minlifetime is less than $maxlifetime;
            $minlifetime = min($minlifetime, $maxlifetime);
        }
        // Read the skin's maxlifetime value from the specified section
        $skinmaxlifetime = $skin->getConfigOption($section, 'maxlifetime');
        if ((!is_null($skinmaxlifetime)) && ((int)$skinmaxlifetime) > 0) {
            $maxlifetime = min($maxlifetime, (int)$skinmaxlifetime);
            // Make sure $maxlifetime is greater than $minlifetime
            $maxlifetime = max($minlifetime, $maxlifetime);
        }

        return array($minlifetime, $maxlifetime);
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
        // CIL-439 For Syngenta, use just a single 'hostname' value to
        // match their Active Directory configuration for CILogon's
        // assertionConsumerService URL. Otherwise, map the local
        // hostname to a *.cilogon.org domain name.
        if ($idp != 'https://sts.windows.net/06219a4a-a835-44d5-afaf-3926343bfb89/') {
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
     * IdPs based on the skin's whitelist/blacklist and the global
     * blacklist file. For the TestIdP page, the list is all InCommon IdPs.
     *
     * @param bool $samlidps (Optional) Show all SAML-based IdPs in
     *        selection list? Defaults to false, which means show only
     *        whitelisted IdPs.
     * @return array A two-dimensional array where the primary key is the
     *         entityID and the secondary key is either 'Display_Name'
     *         or 'Organization_Name'.
     */
    public static function getCompositeIdPList($samlidps = false)
    {
        $retarray = array();

        $idplist = Util::getIdpList();
        if ($samlidps) { // Get all SAML-based IdPs only
            $retarray = $idplist->getSAMLIdPs();
        } else { // Get the selected InCommon IdPs, plus maybe OAuth2 IdPs
            $skin = Util::getSkin();

            // Check if the skin's config.xml has set the
            // 'registeredbyincommonidps' option, which restricts the SAML-
            // based IdPs to those with the <Registered_By_InCommon> tag.
            // Otherwise, just get the SAML-based IdPs that have the
            // <Whitelisted> tag. Note that the skin's <idpwhitelist>
            // is still consulted in either case (below).
            $registeredbyincommonidps = $skin->getConfigOption('registeredbyincommonidps');
            if (
                (!is_null($registeredbyincommonidps)) &&
                ((int)$registeredbyincommonidps == 1)
            ) {
                $retarray = $idplist->getRegisteredByInCommonIdPs();
            } else {
                $retarray = $idplist->getWhitelistedIdPs();
            }

            // Add all OAuth2 IdPs to the list
            foreach (Util::$oauth2idps as $value) {
                // CIL-617 Show OAuth2 IdPs only if client_id is configured
                $client_id = constant(strtoupper($value) . '_OAUTH2_CLIENT_ID');
                if (!empty($client_id)) {
                    $retarray[Util::getAuthzUrl($value)]['Organization_Name'] =
                        $value;
                    $retarray[Util::getAuthzUrl($value)]['Display_Name'] =
                        $value;
                }
            }

            // Check to see if the skin's config.xml has a whitelist of IDPs.
            // If so, go thru master IdP list and keep only those IdPs in the
            // config.xml's whitelist.
            if ($skin->hasIdpWhitelist()) {
                foreach ($retarray as $entityId => $names) {
                    if (!$skin->idpWhitelisted($entityId)) {
                        unset($retarray[$entityId]);
                    }
                }
            }
            // Next, check to see if the skin's config.xml has a blacklist of
            // IdPs. If so, cull down the master IdP list removing 'bad' IdPs.
            if ($skin->hasIdpBlacklist()) {
                $idpblacklist = $skin->getConfigOption('idpblacklist');
                foreach ($idpblacklist->idp as $blackidp) {
                    unset($retarray[(string)$blackidp]);
                }
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
     * printAttributeReleaseErrorMessage
     *
     * This is a convenience method called by handleGotUser to print out
     * the attribute release error page to the user.
     *
     * @param string $ePPN
     * @param string $ePTID
     * @param string $firstname
     * @param string $lastname
     * @param string $displayname
     * @param string $emailaddr
     * @param string $idp
     * @param string $idpname
     * @param string $affiliation
     * @param string $ou
     * @param string $memberof
     * @param string $acr
     * @param string $entitlement
     * @param string $itrustuin
     * @param string $clientparams
     * @param string $redirect
     * @param string $redirectform
     * @param bool   $edugainandgetcert
     */
    public static function printAttributeReleaseErrorMessage(
        $ePPN,
        $ePTID,
        $firstname,
        $lastname,
        $displayname,
        $emailaddr,
        $idp,
        $idpname,
        $affiliation,
        $ou,
        $memberof,
        $acr,
        $entitlement,
        $itrustuin,
        $clientparams,
        $redirect,
        $redirectform,
        $edugainandgetcert
    ) {
        $errorboxstr =
        '<p>There was a problem logging on. Your identity
        provider has not provided CILogon with required information.</p>
        <blockquote><table cellpadding="5">';

        $missingattrs = '';
        // Show user which attributes are missing
        if ((strlen($ePPN) == 0) && (strlen($ePTID) == 0)) {
            $errorboxstr .=
            '<tr><th>ePTID:</th><td>MISSING</td></tr>
            <tr><th>ePPN:</th><td>MISSING</td></tr>';
            $missingattrs .= '%0D%0A    eduPersonPrincipalName' .
                             '%0D%0A    eduPersonTargetedID ';
        }
        if ((strlen($firstname) == 0) && (strlen($displayname) == 0)) {
            $errorboxstr .=
            '<tr><th>First Name:</th><td>MISSING</td></tr>';
            $missingattrs .= '%0D%0A    givenName (first name)';
        }
        if ((strlen($lastname) == 0) && (strlen($displayname) == 0)) {
            $errorboxstr .=
            '<tr><th>Last Name:</th><td>MISSING</td></tr>';
            $missingattrs .= '%0D%0A    sn (last name)';
        }
        if (
            (strlen($displayname) == 0) &&
            ((strlen($firstname) == 0) || (strlen($lastname) == 0))
        ) {
            $errorboxstr .=
            '<tr><th>Display Name:</th><td>MISSING</td></tr>';
            $missingattrs .= '%0D%0A    displayName';
        }
        $emailvalid = filter_var($emailaddr, FILTER_VALIDATE_EMAIL);
        if ((strlen($emailaddr) == 0) || (!$emailvalid)) {
            $errorboxstr .=
            '<tr><th>Email Address:</th><td>' .
            ((strlen($emailaddr) == 0) ? 'MISSING' : 'INVALID') .
            '</td></tr>';
            $missingattrs .= '%0D%0A    mail (email address)';
        }
        // CIL-326/CIL-539 - For eduGAIN IdPs attempting to get a cert,
        // print out missing R&S and SIRTFI values
        $idplist = Util::getIdpList();
        if ($edugainandgetcert) {
            if (!$idplist->isREFEDSRandS($idp)) {
                $errorboxstr .=
                '<tr><th><a target="_blank"
                href="http://refeds.org/category/research-and-scholarship">Research
                and Scholarship</a>:</th><td>MISSING</td></tr>';
                $missingattrs .= '%0D%0A    http://refeds.org/category/research-and-scholarship';
            }
            if (!$idplist->isSIRTFI($idp)) {
                $errorboxstr .=
                '<tr><th><a target="_blank"
                href="https://refeds.org/sirtfi">SIRTFI</a>:</th><td>MISSING</td></tr>';
                $missingattrs .= '%0D%0A    http://refeds.org/sirtfi';
            }
        }
        $student = false;
        $errorboxstr .= '</table></blockquote>';
        if (
            (strlen($emailaddr) == 0) &&
            (preg_match('/student@/', $affiliation))
        ) {
            $student = true;
            $errorboxstr .= '<p><b>If you are a student</b>, ' .
            'you may need to ask your identity provider ' .
            'to release your email address.</p>';
        }

        // Get contacts from metadata for email addresses
        $shibarray = $idplist->getShibInfo($idp);
        $emailmsg = '?subject=Attribute Release Problem for CILogon' .
        '&cc=help@cilogon.org' .
        '&body=Hello, I am having trouble logging on to ' .
        'https://' . DEFAULT_HOSTNAME . '/ using the ' . $idpname .
        ' Identity Provider (IdP) ' .
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
        $errorboxstr .= '<p>Contact your identity provider to ' .
        'let them know you are having having a problem logging on ' .
        'to CILogon.</p><blockquote><ul>';

        $addrfound = false;
        $name = @$shibarray['Support Name'];
        $addr = @$shibarray['Support Address'];
        $addr = preg_replace('/^mailto:/', '', $addr);

        if (strlen($addr) > 0) {
            $addrfound = true;
            if (strlen($name) == 0) { // Use address if no name given
                $name = $addr;
            }
            $errorboxstr .= '<li> Support Contact: ' .
                $name . ' &lt;<a href="mailto:' .
                $addr . $emailmsg . '">' .
                $addr . '</a>&gt;</li>';
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
                $errorboxstr .= '<li> Technical Contact: ' .
                    $name . ' &lt;<a href="mailto:' .
                    $addr . $emailmsg . '">' .
                    $addr . '</a>&gt;</li>';
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
                $errorboxstr .= '<li>Administrative Contact: ' .
                    $name . ' &lt;<a href="mailto:' .
                    $addr . $emailmsg . '">' .
                    $addr . '</a>&gt</li>';
            }
        }

        $errorboxstr .= '</ul></blockquote>

        <p> Alternatively, you can contact us at the email address
        at the bottom of the page.</p>
        ';

        static::printErrorBox($errorboxstr);

        echo '
        <div>
        ';

        static::printFormHead($redirect, 'get');
        echo $redirectform , '
        <input type="submit" name="submit" class="submit"
        value="Proceed" />
        </form>
        </div>
        ';
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
     * @param array $idps (Optional) A list of valid (i.e., whitelisted) IdPs.
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
                // Also, check for OIDC issuers and transform them into
                // CILogon-specific values used in the 'Select an IdP' list.
                if (preg_match('%https://accounts.google.com%', $value)) {
                    $value = 'https://accounts.google.com/o/oauth2/auth';
                } elseif (preg_match('%https://github.com%', $value)) {
                    $value = 'https://github.com/login/oauth/authorize';
                } elseif (preg_match('%https://orcid.org%', $value)) {
                    $value = 'https://orcid.org/oauth/authorize';
                }
            }
            unset($value); // Break the reference with the last element.

            // Remove any non-whitelisted IdPs from the hintarray.
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
}
