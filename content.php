<?php

require_once('util.php');
require_once('autoloader.php');
require_once('myproxy.php');

/* If needed, set the "Notification" banner text to a non-empty value   */
/* and uncomment the "define" statement in order to display a           */
/* notification box at the top of each page.                            */
/*
define('BANNER_TEXT',
       'We are currently experiencing problems issuing certificates. We are
       working on a solution. We apologize for the inconvenience.'
);
*/
/* The full URL of the Shibboleth-protected and OpenID getuser scripts. */
define('GETUSER_URL','https://' . getMachineHostname() . '/secure/getuser/');
define('GETOIDCUSER_URL','https://' . HOSTNAME . '/getuser/');

/* The csrf token object to set the CSRF cookie and print the hidden */
/* CSRF form element.  Be sure to do "global $csrf" to use it.       */
$csrf = new csrf();

/* The configuration for the skin, if any. */
/* Be sure to do "global $skin" to use it. */
$skin = new skin();


/************************************************************************
 * Function   : printHeader                                             *
 * Parameter  : (1) The text in the window's titlebar                   *
 *              (2) Optional extra text to go in the <head> block       *
 *              (3) Set the CSRF and CSRFProtetion cookies. Defaults    *
 *                  to true.                                            *
 * This function should be called to print out the main HTML header     *
 * block for each web page.  This gives a consistent look to the site.  *
 * Any style changes should go in the cilogon.css file.                 *
 ************************************************************************/
function printHeader($title='',$extra='',$csrfcookie=true) {
    global $csrf;       // Initialized above
    global $skin;

    if ($csrfcookie) {
        $csrf->setTheCookie();
        // Set the CSRF cookie used by GridShib-CA
        util::setCookieVar('CSRFProtection',$csrf->getTokenValue(),0);
    }

    // Find the "Powered By CILogon" image if specified by the skin
    $poweredbyimg = "/images/poweredbycilogon.png";
    $skinpoweredbyimg = (string)$skin->getConfigOption('poweredbyimg');
    if ((!is_null($skinpoweredbyimg)) && 
        (strlen($skinpoweredbyimg) > 0) &&
        (is_readable('/var/www/html' . $skinpoweredbyimg))) {
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

    $deployjava = $skin->getConfigOption('deployjava');
    if ((!is_null($deployjava)) && ((int)$deployjava == 1)) {
        echo '<script type="text/javascript" src="/include/deployJava.js"></script>';
    }

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

    if ((defined('BANNER_TEXT')) && (strlen(BANNER_TEXT) > 0)) {
        echo '
        <div class="noticebanner">' , BANNER_TEXT , '</div>
        ';
    }

    $providerId = util::getSessionVar('idp');
    if ($providerId == "urn:mace:incommon:idp.protectnetwork.org") {
        echo '
        <div class="noticebanner">Availability of the ProtectNetwork 
        Identity Provider (IdP) will end after December 2014. Please 
        consider using another IdP.</div>
        ';
    }
}

/************************************************************************
 * Function   : printFooter                                             *
 * Parameter  : (1) Optional extra text to be output before the closing *
 *                  footer div.                                         *
 * This function should be called to print out the closing HTML block   *
 * for each web page.                                                   *
 ************************************************************************/
function printFooter($footer='') {
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

/************************************************************************
 * Function  : printPageHeader                                          *
 * Parameter : The text string to appear in the titlebox.               *
 * This function prints a fancy formatted box with a single line of     *
 * text, suitable for a titlebox on each web page (to appear just below *
 * the page banner at the very top).  It prints a gradent border around *
 * the four edges of the box and then outlines the inner box.           *
 ************************************************************************/
function printPageHeader($text) {
    echo '
    <div class="titlebox">' , $text , '
    </div>
    ';
}

/************************************************************************
 * Function   : printFormHead                                           *
 * Parameters : (1) (Optional) The value of the form's "action"         *
 *                  parameter. Defaults to getScriptDir().              *
 *              (2) (Optional) True if extra hidden tags should be      *
 *                  output for the GridShib-CA client application.      *
 *                  Defaults to false.                                  *
 * This function prints out the opening <form> tag for displaying       *
 * submit buttons.  The first parameter is used for the "action" value  *
 * of the <form>.  If omitted, getScriptDir() is called to get the      *
 * location of the current script.  This function outputs a hidden csrf *
 * field in the form block.  If the second parameter is given and set   *
 * to true, then an additional hidden input element is output to be     *
 * utilized by the GridShib-CA client.                                  *
 ************************************************************************/
function printFormHead($action='',$gsca=false) {
    global $csrf;
    static $formnum = 0;

    if (strlen($action) == 0) {
        $action = util::getScriptDir();
    }

    echo '
    <form action="' , $action , '" method="post" 
     autocomplete="off" id="form' , sprintf("%02d",++$formnum) , '">
    ';
    echo $csrf->hiddenFormElement();

    if ($gsca) {
        /* Output hidden form element for GridShib-CA */
        echo '
        <input type="hidden" name="CSRFProtection" value="' .
        $csrf->getTokenValue() . '" />
        ';
    }
}

/************************************************************************
 * Function   : printWAYF                                               *
 * Parameters : (1) Show the "Remember this selection" checkbox?        *
 *                  True or false. Defaults to true.                    *
 *              (2) Show all InCommon IdPs in selection list?           *
 *                  True or false. Defaults to false, which means show  *
 *                  only whitelisted IdPs.                              *
 * This function prints the list of IdPs in a <select> form element     *
 * which can be printed on the main login page to allow the user to     *
 * select "Where Are You From?".  This function checks to see if a      *
 * cookie for the 'providerId' had been set previously, so that the     *
 * last used IdP is selected in the list.                               *
 ************************************************************************/
function printWAYF($showremember=true,$incommonidps=false) {
    global $csrf;
    global $skin;

    $whiteidpsfile = '/var/www/html/include/whiteidps.txt';
    $helptext = "Check this box to bypass the welcome page on subsequent visits and proceed directly to the selected identity provider. You will need to clear your browser's cookies to return here."; 
    $searchtext = "Enter characters to search for in the list above.";

    /* Get an array of IdPs */
    $idps = getCompositeIdPList($incommonidps);

    /* Check if the user had previously selected an IdP from the list. */
    /* First, check the portalcookie, then the 'normal' cookie.        */
    $keepidp = '';
    $providerId = '';
    $pc = new portalcookie();
    $pn = $pc->getPortalName();
    if (strlen($pn) > 0) {
        $keepidp    = $pc->get('keepidp');
        $providerId = $pc->get('providerId');
    } else {
        $keepidp    = util::getCookieVar('keepidp');
        $providerId = util::getCookieVar('providerId');
    }

    /* Make sure previously selected IdP is in list of available IdPs. */
    if ((strlen($providerId) > 0) && (!isset($idps[$providerId]))) {
        $providerId = '';
    }

    /* If no previous providerId, get from skin, or default to Google. */
    if (strlen($providerId) == 0) {
        $initialidp = (string)$skin->getConfigOption('initialidp');
        if ((!is_null($initialidp)) && (isset($idps[$initialidp]))) {
            $providerId = $initialidp;
        } else {
            $providerId = GOOGLE_OIDC;
        }
    }

    /* Check if an OIDC client selected an IdP for the transaction.  *
     * If so, verify that the IdP is in the list of available IdPs.  */
    $useselectedidp = false;
    $clientparams = json_decode(util::getSessionVar('clientparams'),true);
    if (isset($clientparams['selected_idp'])) {
        $selected_idp = $clientparams['selected_idp'];
        if ((strlen($selected_idp) > 0) && (isset($idps[$selected_idp]))) {
            $useselectedidp = true;
            $providerId = $selected_idp;
            /* Update the IdP selection list to show only this one IdP */
            $idps = array($selected_idp => $idps[$selected_idp]);
        }
    }

    echo '
    <br />
    <div class="actionbox"';

    if (util::getSessionVar('showhelp') == 'on') {
        echo ' style="width:92%;"';
    }

    echo '>
    <table class="helptable">
    <tr>
    <td class="actioncell">

      <form action="' , util::getScriptDir() , '" method="post">
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

      // When selected_idp is used, list size should always be 1.
      if ($useselectedidp) {
          $selectsize = 1;
      }

      echo '
      <p>
      <select name="providerId" id="providerId" size="' , $selectsize , '"
       onkeypress="enterKeySubmit(event)" ondblclick="doubleClickSubmit()"' ,
       /* Hide the drop-down arrow in Firefox and Chrome */
      ($useselectedidp ?
          'style="-moz-appearance:none;-webkit-appearance:none"' : '') ,
       '>
    ';

    foreach ($idps as $entityId => $idpName) {
        echo '    <option value="' , $entityId , '"';
        if ($entityId == $providerId) {
            echo ' selected="selected"';
        }
        echo '>' , util::htmlent($idpName) , '</option>' , "\n    ";
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

    echo $csrf->hiddenFormElement();

    $lobtext = getLogOnButtonText();

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

    $logonerror = util::getSessionVar('logonerror');
    if (strlen($logonerror) > 0) {
        echo "<p class=\"logonerror\">$logonerror</p>";
        util::unsetSessionVar('logonerror');
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

  if (util::getSessionVar('showhelp') == 'on') {
      echo '
      <td class="helpcell">
      <div>
      ';

      if ($incommonidps) { /* InCommon IdPs only means running from /testidp/ */
          echo '
          <p>
          CILogon facilitates secure access to CyberInfrastructure (<acronym
          title="CyberInfrastructure">CI</acronym>). In order to test your
          identity provider with the CILogon Service, you must first Log On.
          If your preferred identity provider is not listed, please fill out
          the <a target="_blank" href="https://' , HOSTNAME ,
          '/requestidp/">"request a new organization" form</a>, and we will
          try to add your identity provider in the future.
          </p>
          ';
      } else { /* If not InCommon only, print help text for OpenID providers. */
          echo '
          <p>
          CILogon facilitates secure access to CyberInfrastructure (<acronym
          title="CyberInfrastructure">CI</acronym>).
          In order to use the CILogon Service, you must first select an identity
          provider. An identity provider (IdP) is an organization where you have
          an account and can log on to gain access to online services. 
          </p>
          <p>
          If you are a faculty, staff, or student member of a university or
          college, please select it for your identity
          provider.  If your school is not listed,
          please fill out the <a target="_blank"
          href="https://' , HOSTNAME , '/requestidp/">"request a new
          organization" form</a>, and we will try to add your school in the
          future.
          </p>
          ';

          $googleavail=$skin->idpAvailable(GOOGLE_OIDC);
          if ($googleavail) {
              echo '
              <p>
              If you have a <a target="_blank"
              href="http://google.com/profiles/me">Google</a>
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

/************************************************************************
 * Function   : printTwoFactorBox                                       *
 * This function prints the "Manage Two-Factor" box on the main page.   *
 ************************************************************************/
function printTwoFactorBox() {
    $managetwofactortext = 'Enable or disable two-factor authentication for your account';

    echo '
    <div class="twofactoractionbox"';

    $style = ''; // Might add extra CSS to the twofactoractionbox
    if (util::getSessionVar('showhelp') == 'on') {
        $style .= "width:92%;";
    }
    if (twofactor::getEnabled() != 'none') {
        $style .= "display:block;"; // Force display if two-factor enabled
    }
    if (strlen($style) > 0) {
        echo ' style="' , $style , '"';
    }
    
    echo '>
    <table class="helptable">
    <tr>
    <td class="actioncell">
    ';
    
    printFormHead();

    $twofactorname = twofactor::getEnabledName();
    if ($twofactorname == 'Disabled') {
        $twofactorname = 'Two-Factor Authentication Disabled';
    } else {
        $twofactorname .= ' Enabled';
    }
    echo '
      <p>' , $twofactorname , '</p>

      <p>
      <input type="submit" name="submit" class="submit helpcursor" 
      title="' , $managetwofactortext , '" value="Manage Two-Factor" />
      </p>
      </form>
    </td>
    ';

    if (util::getSessionVar('showhelp') == 'on') {
        echo '
        <td class="helpcell">
        <div>
        <p>
        Two-factor authentication provides extra security on your account by
        using a physical device (e.g., your mobile phone) to generate a one
        time password which you enter after you log in to your selected
        Identity Provider. Click the "Manage Two-Factor" button to enable or
        disable two-factor authentication.
        </p>
        </div>
        </td>
        ';
    }
    
    echo '
    </tr>
    </table>
    </div> <!-- twofactoractionbox -->
    ';
}

/************************************************************************
 * Function  : printTwoFactorPage                                       *
 * This function prints out the Manage Two-Factor Authentication page.  *
 * Display of which two-factor types are available to the user is       *
 * controlled by CSS. From this page, the user can Enable or Disable    *
 * various two-factor authentication methods.                           *
 ************************************************************************/
function printTwoFactorPage() {
    util::setSessionVar('stage','managetwofactor'); // For Show/Hide Help button

    printHeader('Manage Two-Factor Authentication');

    $twofactorname = twofactor::getEnabledName();

    echo '
    <div class="boxed">
    ';
    printHelpButton();
    echo'
    <h2>Two-Factor Authentication</h2>
    <div class="actionbox">
    <p><b>Two-Factor Authentication:</b></p>
    <p>' , $twofactorname , '</p>
    </div> <!-- actionbox -->
    ';

    if (util::getSessionVar('showhelp') == 'on') {
        echo '
        <div>
        <p>
        Multi-factor authentication requires a user to present two or more
        distinct authentication factors from the following categories:
        </p>
        <ul>
        <li>Something you <b>know</b> (e.g., username and password)</li>
        <li>Something you <b>have</b> (e.g., mobile phone or hardware 
            token)</li>
        <li>Something you <b>are</b> (e.g., fingerprint)</li>
        </ul>
        <p>
        Below you can configure a second factor using something you <b>have</b>,
        i.e., your mobile phone. You will first be prompted to register the
        second-factor device, typically by installing specific apps on your
        phone and completing a registration process. Then you will need to log
        in with the second factor to verify the registration process.
        </p>
        <p>
        Once you have successfully enabled two-factor authentication, you will
        be prompted on future CILogon Service logons for your second-factor
        authentication.  You can select the second-factor authentication to use
        (or not) by clicking the "Enable" (or "Disable") button.
        </p>
        </div>
        ';
    }

    echo '
    <table class="twofactortypes">
    <tr class="twofactorgooglerow"' , 
    (twofactor::isEnabled('ga') ? ' style="display:table-row;"' : '') , 
    '>
    <th>Google Authenticator</th>
    <td>
    ';
    printFormHead();
    echo '
    <input type="hidden" name="twofactortype" value="ga" />
    <input type="submit" name="submit" class="submit" value="' ,
    (twofactor::isEnabled('ga') ? 'Disable' : 'Enable') ,
    '" />
    </form>
    </td>
    </tr>
    ';

    if (util::getSessionVar('showhelp') == 'on') {
        echo '
        <tr>
        <td colspan="4">
        Google Authenticator is an app available for Android OS, Apple
        iOS, and BlackBerry OS. The app generates one-time password tokens.
        After you have logged on to the CILogon Service with your chosen
        Identity Provider, you would be prompted to use the Google
        Authenticator app to generate a second passcode and enter it. 
        </td>
        </tr>
        ';
    }

    echo '
    <tr class="twofactorduorow"' , 
    (twofactor::isEnabled('duo') ? '
        style="display:table-row;border-top-width:1px"' : '') , 
    '>
    <th>Duo Security</th>
    <td>
    ';
    printFormHead(); 
    echo '
    <input type="hidden" name="twofactortype" value="duo" />
    <input type="submit" name="submit" class="submit" value="' ,
    (twofactor::isEnabled('duo') ? 'Disable' : 'Enable') ,
    '" />
    </form>
    </td>
    </tr>
    ';

    if (util::getSessionVar('showhelp') == 'on') {
        echo '
        <tr>
        <td colspan="4">
        Duo Security is an app available for most smartphones, including
        Android OS, Apple iOS, Blackberry OS, Palm, Symbian, and Windows
        Mobile. The app can respond to "push" notifications, and can also
        generate one-time password tokens. After you have logged on to the
        CILogon Service with your chosen Identity Provider, you would be
        prompted to select a Duo log in method and then authenticate with
        your mobile phone.
        </td>
        </tr>
        ';
    }

    echo '
    </table>

    <noscript>
    <div class="nojs smaller">
    Javascript is disabled. In order to activate the link
    below, please enable Javascript in your browser.
    </div>
    </noscript>

    <p>
    <a href="javascript:showHideDiv(\'lostdevice\',-1)">Lost your phone?</a>
    </p>
    <div id="lostdevice" style="display:none">
    <p>
    If you have lost your phone, you can click on the
    "I Lost My Phone" button below to remove all two-factor methods
    from your account.  This will send an email message to the system
    adminisrator and to the email address provided by your Identity
    Provider.  You can then use the CILogon Service without
    two-factor authentication enabled. You will need to re-register your
    device with you want to use it for two-factor authentication again.
    <br/>
    ';
    printFormHead();
    echo '
    <input type="submit" name="submit" class="submit" 
    value="I Lost My Phone" />
    </p>
    </div>

    <p>
    <input type="submit" name="submit" class="submit" 
    value="Done with Two-Factor" />
    </p>
    </form>

    </div> <!-- boxed -->
    ';
    printFooter();
}

/************************************************************************
 * Function  : handleEnableDisableTwoFactor                             *
 * Parameter : True for 'enable', false for 'disable'. Default is       *
 *             false (for 'disable').                                   *
 * This function is called when the user clicks either an 'Enable' or   *
 * 'Disable' button from the Manage Two-Factor page, or when the user   *
 * clicks 'Verify' on the Google Authenticator Registration page.       *
 * The passed-in parameter tells which type of button was pressed.      *
 * If 'Disable', then simply set 'enabled=none' in the datastore and    *
 * display the Manage Two-Factor page again. If 'Enable' or 'Verify',   *
 * check the 'twofactortype' hidden form variable for which two-factor  *
 * authentication method is to be enabled. Then print out that          *
 * two-factor page. Note that twofactor::printPage() does the work of   *
 * figuring out if the user has registered the phone yet or not, and    *
 * displays the appropriate page.                                       *
 ************************************************************************/
function handleEnableDisableTwoFactor($enable=false) {
    if ($enable) {
        $twofactortype = util::getPostVar('twofactortype');
        if (strlen($twofactortype) > 0) {
            twofactor::printPage($twofactortype);
        } else {
            printLogonPage();
        }
    } else { // 'Disable' clicked
        // Check if the user clicked 'Disable Two-Factor' and send email
        if (util::getPostVar('missingphone') == '1') {
            // Make sure two-factor was enabled
            $twofactorname = twofactor::getEnabledName();
            if ($twofactorname != 'Disabled') {
                $email = getEmailFromDN(util::getSessionVar('dn'));
                if (strlen($email) > 0) { // Make sure email address exists
                    twofactor::sendPhoneAlert(
                        'Forgot Phone for Two-Factor Authentication',
'While using the CILogon Service, you (or someone using your account)
indicated that you forgot your phone by clicking the "Disable Two-Factor"
button. This disabled two-factor authentication by "' . $twofactorname . '"
using "' . util::getSessionVar('idpname') . '" as your Identity Provider.

If you did not disable two-factor authentication, please send email to
"help@cilogon.org" to report this incident.',
                        $email
                    );
                } else { // No email address is bad - send error alert
                    util::sendErrorAlert('Missing Email Address',
'When attempting to send an email notification to a user who clicked the
"Disable Two-Factor" button because of a forgotten phone, the CILogon
Service was unable to find an email address. This should never occur and
is probably due to a badly formatted "dn" string.');
                }
            }
        }

        // Finally, disable two-factor authentication
        twofactor::setDisabled();
        twofactor::write();
        printTwoFactorPage();
    }
}

/************************************************************************
 * Function  : handleILostMyPhone                                       *
 * This function is called when the user clicks the 'I Lost My Phone'   *
 * button.  It sends email to the user AND to alerts because Duo        *
 * Security requires that a sysadmin unregister the phone for the user. *
 * It then unsets the 'twofactor' session variable, and writes it to    *
 * the datastore, effectively wiping out all two-factor information for *
 * the user.                                                            *
 ************************************************************************/
function handleILostMyPhone() {
    // First, send email to user
    $email = getEmailFromDN(util::getSessionVar('dn'));
    if (strlen($email) > 0) { // Make sure email address exists
        twofactor::sendPhoneAlert(
            'Lost Phone for Two-Factor Authentication',
'While using the CILogon Service, you (or someone using your account)
indicated that you lost your phone by clicking the "I Lost My Phone"
button. This removed two-factor authentication for your account when
using "' . util::getSessionVar('idpname') . '" as your Identity Provider.

System administrators have been notified of this incident. If you require
further assistance, please send email to "help@cilogon.org".',
            $email
        );
    } else { // No email address is bad - send error alert
        util::sendErrorAlert('Missing Email Address',
'When attempting to send an email notification to a user who clicked the
"I Lost My Phone" button, the CILogon Service was unable to find an 
email address. This should never occur and is probably due to a badly
formatted "dn" string.');
    }

    // Next, send email to sysadmin
    $errortext = 'A user clicked the "I Lost My Phone" button. ';
    if (twofactor::isRegistered('duo')) {
        $duoconfig = new duoconfig();
        $errortext .= '

The user had registered "Duo Security" as one of the two-factor methods.
Since there is no way for the CILogon Service to UNregister this method
at the Duo Security servers, a system administrator will need to delete 
this user\'s registration at https://' . $duoconfig->param['host'] .
' .';
    }
    util::sendErrorAlert('Two-Factor Authentication Disabled',$errortext);

    // Finally, disable and unregister two-factor authentication
    util::unsetSessionVar('twofactor');
    twofactor::write();
    printTwoFactorPage();
}

/************************************************************************
 * Function  : handleGoogleAuthenticatorLogin                           *
 * This function is called when the user enters a one time password as  *
 * generated by the Google Authenticator app. This can occur (1) when   *
 * the user is first configuring GA two-factor and (2) when the user    *
 * logs in to the CILogon Service and GA is enabled. If the OTP is      *
 * correctly validated, the gotUserSuccess() function is called to      *
 * show output to the user.                                             *
 ************************************************************************/
function handleGoogleAuthenticatorLogin() {
    $gacode = util::getPostVar('gacode');
    if ((strlen($gacode) > 0) && (twofactor::isGACodeValid($gacode))) {
        gotUserSuccess();
    } else {
        twofactor::printPage('ga');
    }
}

/************************************************************************
 * Function  : handleDuoSecurityLogin                                   *
 * This function is called when the user authenticates with Duo         *
 * Security. If the Duo authentication is valid, then the               *
 * gotUserSuccess() function is then called to show output to the user. *
 ************************************************************************/
function handleDuoSecurityLogin() {
    $sig_response = util::getPostVar('sig_response');
    if ((strlen($sig_response) > 0) &&
        (twofactor::isDuoCodeValid($sig_response))) {
        gotUserSuccess();
    } else {
        twofactor::printPage('duo');
    }
}

/************************************************************************
 * Function  : handleLogOnButtonClicked                                 *
 * This function is called when the user clicks the "Log On" button     *
 * on the IdP selection page. It checks to see if the "Remember this    *
 * selection" checkbox was checked and sets a cookie appropriately. It  *
 * also sets a cookie 'providerId' so the last chosen IdP will be       *
 * selected the next time the user visits the site. The function then   *
 * calls the appropriate "redirectTo..." function to send the user      *
 * to the chosen IdP.                                                   *
 ************************************************************************/
function handleLogOnButtonClicked() {
    // Get the list of currently available IdPs
    $idps = getCompositeIdPList();

    // Set the cookie for keepidp if the checkbox was checked
    $pc = new portalcookie();
    $pn = $pc->getPortalName();
    if (strlen(util::getPostVar('keepidp')) > 0) {
        if (strlen($pn) > 0) {
            $pc->set('keepidp','checked');
        } else {
            util::setCookieVar('keepidp','checked');
        }
    } else {
        if (strlen($pn) > 0) {
            $pc->set('keepidp','');
        } else {
            util::unsetCookieVar('keepidp');
        }
    }
    
    // Set the cookie for the last chosen IdP and redirect to it if in list
    $providerIdPost = util::getPostVar('providerId');
    if ((strlen($providerIdPost) > 0) && (isset($idps[$providerIdPost]))) {
        if (strlen($pn) > 0) {
            $pc->set('providerId',$providerIdPost);
            $pc->write();
        } else {
            util::setCookieVar('providerId',$providerIdPost);
        }
        if ($providerIdPost == GOOGLE_OIDC) { // Log in with Google
            redirectToGetGoogleOAuth2User();
        } else { // Use InCommon authn
            redirectToGetShibUser($providerIdPost);
        }
    } else { // IdP not in list, or no IdP selected
        if (strlen($pn) > 0) {
            $pc->set('providerId','');
            $pc->write();
        } else {
            util::unsetCookieVar('providerId');
        }
        util::setSessionVar('logonerror','Please select a valid IdP.');
        printLogonPage();
    }
}

/************************************************************************
 * Function  : handleHelpButtonClicked                                  *
 * This function is called when the user clicks on the "Show Help" /    *
 * "Hide Help" button in the upper right corner of the page. It toggles *
 * the 'showhelp' session variable and redisplays the appropriate page  *
 * with help now shown or hidden.                                       *
 ************************************************************************/
function handleHelpButtonClicked() {
    if (util::getSessionVar('showhelp') == 'on') {
        util::unsetSessionVar('showhelp');
    } else {
        util::setSessionVar('showhelp','on');
    }

    $stage = util::getSessionVar('stage');
    if (verifyCurrentSession()) {
        if ($stage == 'main') {
            printMainPage();
        } elseif ($stage == 'managetwofactor') {
            printTwoFactorPage();
        } else {
            printLogonPage();
        }
    } else {
        printLogonPage();
    }
}

/************************************************************************
 * Function  : handleNoSubmitButtonClicked                              *
 * This function is the "default" case when no "submit" button has been *
 * clicked, or if the submit session variable is not set. It checks     *
 * to see if either the <forceinitialidp> option is set, or if the      *
 * "Remember this selection" checkbox was previously checked. If so,    *
 * then rediret to the appropriate IdP. Otherwise, print the main       *
 * Log On page.                                                         *
 ************************************************************************/
function handleNoSubmitButtonClicked() {
    global $skin;
    global $idplist;

    /* If this is a OIDC transaction, get the selected_idp and   *
     * redirect_uri parameters from the session var clientparams.*/
    $selected_idp = '';
    $redirect_uri = '';
    $clientparams = json_decode(util::getSessionVar('clientparams'),true);
    if (isset($clientparams['selected_idp'])) {
        $selected_idp = $clientparams['selected_idp'];
    }
    if (isset($clientparams['redirect_uri'])) {
        $redirect_uri = $clientparams['redirect_uri'];
    }

    /* If the <forceinitialidp> option is set, use either the    *
     * <initialidp> or the "selected_idp" as the providerId, and *
     * use <forceinitialidp> as keepIdp. Otherwise, read the     *
     * cookies 'providerId' and 'keepidp'.                       */
    $providerId = '';
    $keepidp = '';
    $readidpcookies = true;  // Assume config options are not set
    $forceinitialidp = (int)$skin->getConfigOption('forceinitialidp');
    $initialidp = (string)$skin->getConfigOption('initialidp');
    if (($forceinitialidp == 1) && 
        ((strlen($initialidp) > 0) || (strlen($selected_idp) > 0))) {
        // If the <allowforceinitialidp> option is set, then make sure
        // the callback / redirect uri is in the portal list.
        $afii=$skin->getConfigOption('portallistaction','allowforceinitialidp');
        if ((is_null($afii)) || // Option not set, no need to check portal list
            (((int)$afii == 1) && 
              (($skin->inPortalList(util::getSessionVar('callbackuri'))) ||
               ($skin->inPortalList($redirect_uri))))) {
            // "selected_idp" takes precedence over <initialidp>
            if (strlen($selected_idp) > 0) {
                $providerId = $selected_idp;
            } else {
                $providerId = $initialidp;
            }
            $keepidp = $forceinitialidp;
            $readidpcookies = false; // Don't read in the IdP cookies
        }
    }
    
    /* <initialidp> options not set, or portal not in portal list?  *
     * Get idp and "Remember this selection" from cookies instead.  */
    $pc = new portalcookie();
    $pn = $pc->getPortalName();
    if ($readidpcookies) {
        // Check the portalcookie first, then the 'normal' cookies
        if (strlen($pn) > 0) {
            $keepidp    = $pc->get('keepidp');
            $providerId = $pc->get('providerId');
        } else {
            $keepidp    = util::getCookieVar('keepidp');
            $providerId = util::getCookieVar('providerId');
        }
    }

    /* If both "keepidp" and "providerId" were set (and the         *
     * providerId is a whitelisted IdP or valid OpenID provider),   *
     * then skip the Logon page and proceed to the appropriate      *
     * getuser script.                                              */
    if ((strlen($providerId) > 0) && (strlen($keepidp) > 0)) {
        /* If selected_idp was specified at the OIDC authorize endpoint, *
         * make sure that it matches the saved providerId. If not,       *
         * then show the Logon page and uncheck the keepidp checkbox.    */
        if ((strlen($selected_idp) == 0) || ($selected_idp == $providerId)) {
            if ($providerId == GOOGLE_OIDC) { // Use Google
                redirectToGetGoogleOAuth2User();
            } elseif ($idplist->exists($providerId)) { // Use InCommon
                redirectToGetShibUser($providerId);
            } else { // $providerId not in whitelist
                if (strlen($pn) > 0) {
                    $pc->set('providerId','');
                    $pc->write();
                } else {
                    util::unsetCookieVar('providerId');
                }
                printLogonPage();
            }
        } else { // selected_idp does not match saved providerId
            if (strlen($pn) > 0) {
                $pc->set('keepidp','');
                $pc->write();
            } else {
                util::unsetCookieVar('keepidp');
            }
            printLogonPage();
        }
    } else { // One of providerId or keepidp was not set
        printLogonPage();
    }
}

/************************************************************************
 * Function  : printIcon                                                *
 * Parameters: (1) The prefix of the "...Icon.png" image to be shown.   *
 *                 E.g. to show "errorIcon.png", pass in "error".       *
 *             (2) The popup "title" text to be displayed when the      *
 *                 mouse cursor hovers over the icon.  Defaults to "".  *
 *             (3) A CSS class for the icon. Will be appended after     *
 *                 the 'helpcursor' class.                              *
 * This function prints out the HTML for the little icons which can     *
 * appear inline with other information.  This is accomplished via the  *
 * use of wrapping the image in a <span> tag.                           *
 ************************************************************************/
function printIcon($icon,$popuptext='',$class='') {
    echo '<span';
    if (strlen($popuptext) > 0) {
        echo ' class="helpcursor ' , $class , '" title="' , $popuptext , '"';
    }
    echo '>&nbsp;<img src="/images/' , $icon , 'Icon.png" 
          alt="&laquo; ' , ucfirst($icon) , '"
          width="14" height="14" /></span>';
}

/************************************************************************
 * Function   : printHelpButton                                         *
 * This function prints the "Show Help" / "Hide Help" button in the     *
 * upper-right corner of the main box area on the page.                 *
 ************************************************************************/
function printHelpButton() {
    echo '
    <div class="helpbutton">
    ';

    printFormHead();

    echo '
      <input type="submit" name="submit" class="helpbutton" value="' , 
      (util::getSessionVar('showhelp')=='on' ? 'Hide':'Show') , '&#10; Help " />
      </form>
    </div>
    ';
}

/************************************************************************
 * Function   : verifyCurrentSession                                    *
 * Parameter  : (Optional) The user-selected Identity Provider          *
 * Returns    : True if the contents of the PHP session ar valid,       *
 *              False otherwise.                                        *
 * This function verifies the contents of the PHP session.  It checks   *
 * the following:                                                       *
 * (1) The persistent store 'uid', the Identity Provider 'idp', the     *
 *     IdP Display Name 'idpname', and the 'status' (of getUser()) are  *
 *     all non-empty strings.                                           *
 * (2) The 'status' (of getUser()) is even (i.e. STATUS_OK).            *
 * (3) If $providerId is passed-in, it must match 'idp'.                *
 * If all checks are good, then this function returns true.             *
 ************************************************************************/
function verifyCurrentSession($providerId='') {
    global $skin;

    $retval = false;

    $uid       = util::getSessionVar('uid');
    $idp       = util::getSessionVar('idp');
    $idpname   = util::getSessionVar('idpname');
    $status    = util::getSessionVar('status');
    $dn        = util::getSessionVar('dn');
    $authntime = util::getSessionVar('authntime');
    if ((strlen($uid) > 0) && (strlen($idp) > 0) && 
        (strlen($idpname) > 0) && (strlen($status) > 0) &&
        (strlen($dn) > 0) && (strlen($authntime) > 0) &&
        (!($status & 1))) {  // All STATUS_OK codes are even
        if ((strlen($providerId) == 0) || ($providerId == $idp)) {
            $retval = true;
        }
    }

    // As a final check, see if the IdP requires a forced skin
    if ($retval) {
        $skin->init();
    }

    return $retval;
}

/************************************************************************
 * Function  : verifySessionAndCall                                     *
 * Parameters: (1) The function to call if the current session is       *
 *                 successfully verified.                               *
 *             (2) An array of parameters to pass to the function.      *
 *                 Defaults to empty array, meaning zero parameters.    *
 * This function is a convenience method called by several cases in the *
 * main 'switch' call at the top of the index.php file. I noticed       *
 * a pattern where verifyCurrentSession() was called to verify the      *
 * current user session. Upon success, one or two functions were called *
 * to continue program, flow. Upon failure, cookies and session         *
 * variables were cleared, and the main Logon page was printed. This    *
 * function encapsulates that pattern. If the user's session is valid,  *
 * the passed-in $func is called, possibly with parameters passed in as *
 * an array. The function returns true if the session is verified, so   *
 * that other functions may be called upon return.                      *
 ************************************************************************/
function verifySessionAndCall($func,$params=array()) {
    $retval = false;
    if (verifyCurrentSession()) { // Verify PHP session contains valid info
        $retval = true;
        call_user_func_array($func,$params);
    } else {
        printLogonPage(true); // Clear cookies and session vars too
    }
    return $retval;
}

/************************************************************************
 * Function   : redirectToGetShibUser                                   *
 * Parameters : (1) An entityId of the authenticating IdP.  If not      *
 *                  specified (or set to the empty string), we check    *
 *                  providerId PHP session variable and providerId      *
 *                  cookie (in that order) for non-empty values.        *
 *              (2) (Optional) The value of the PHP session 'submit'    *
 *                  variable to be set upon return from the 'getuser'   *
 *                  script.  This is utilized to control the flow of    *
 *                  this script after "getuser". Defaults to 'gotuser'. *
 *              (3) A response url for redirection after successful     *
 *                  processing at /secure/getuser/. Defaults to         *
 *                  the current script directory.                       *
 *              (4) Is it okay to request silver assurance in the       *
 *                  authnContextClassRef? If not, then ignore the       *
 *                  "Request Silver" checkbox and silver certification  *
 *                  in metadata. Defaults to true.                      *
 * This method redirects control flow to the getuser script for         *
 * If the first parameter (a whitelisted entityId) is not specified,    *
 * we check to see if either the providerId PHP session variable or the *
 * providerId cookie is set (in that order) and use one if available.   *
 * The function then checks to see if there is a valid PHP session      *
 * and if the providerId matches the 'idp' in the session.  If so, then *
 * we don't need to redirect to "/secure/getuser/" and instead we       *
 * we display the main page.  However, if the PHP session is not valid, *
 * then this function redirects to the "/secure/getuser/" script so as  *
 * to do a Shibboleth authentication via mod_shib. When the providerId  *
 * is non-empty, the SessionInitiator will automatically go to that IdP *
 * (i.e. without stopping at a WAYF).  This function also sets          *
 * several PHP session variables that are needed by the getuser script, *
 * including the 'responsesubmit' variable which is set as the return   *
 * 'submit' variable in the 'getuser' script.                           *
 ************************************************************************/
function redirectToGetShibUser($providerId='',$responsesubmit='gotuser',
                               $responseurl=null,$allowsilver=true) {
    global $csrf;
    global $log;
    global $skin;
    global $idplist;

    // If providerId not set, try the cookie value
    if (strlen($providerId) == 0) {
        $providerId = util::getPortalOrNormalCookieVar('providerId');
    }
    
    // If the user has a valid 'uid' in the PHP session, and the
    // providerId matches the 'idp' in the PHP session, then 
    // simply go to the main page.
    if (verifyCurrentSession($providerId)) {
        printMainPage();
    } else { // Otherwise, redirect to the getuser script
        // Set PHP session varilables needed by the getuser script
        util::setSessionVar('responseurl',
            (is_null($responseurl) ? util::getScriptDir(true) : $responseurl));
        util::setSessionVar('submit','getuser');
        util::setSessionVar('responsesubmit',$responsesubmit);
        $csrf->setCookieAndSession();

        // Set up the "header" string for redirection thru mod_shib 
        $redirect = 
            'Location: https://' . getMachineHostname() .
            '/Shibboleth.sso/Login?' .
            'target=' . urlencode(GETUSER_URL);

        if (strlen($providerId) > 0) {
            // Use special NIHLogin Shibboleth SessionInitiator for acsByIndex
            if ($providerId == 'urn:mace:incommon:nih.gov') {
                $redirect = preg_replace('%/Shibboleth.sso/Login%',
                                         '/Shibboleth.sso/NIHLogin',$redirect);
            }

            $redirect .= '&providerId=' . urlencode($providerId);

            // To bypass SSO at IdP, check for session var 'forceauthn' == 1
            $forceauthn = util::getSessionVar('forceauthn');
            util::unsetSessionVar('forceauthn');
            if ($forceauthn) {
                $redirect .= '&forceAuthn=true';
            } elseif (strlen($forceauthn)==0) { 
                // 'forceauth' was not set to '0' in the session, so 
                // check the skin's option instead.
                $forceauthn = $skin->getConfigOption('forceauthn');
                if ((!is_null($forceauthn)) && ((int)$forceauthn == 1)) {
                    $redirect .= '&forceAuthn=true';
                }
            }

            // If Silver IdP or "Request Silver" checked, send extra parameter
            if ($allowsilver) {
                if (($idplist->isSilver($providerId)) ||
                    (strlen(util::getPostVar('silveridp')) > 0)) {
                    util::setSessionVar('requestsilver','1');
                    $redirect .= '&authnContextClassRef=' . 
                        urlencode('http://id.incommon.org/assurance/silver');
                }
            }
        }

        $log->info('Shibboleth Login="' . $redirect . '"');
        header($redirect);
    }
}

/************************************************************************
 * Function   : redirectToGetGoogleOAuth2User                           *
 * Parameters : (1) (Optional) The value of the PHP session 'submit'    *
 *                  variable to be set upon return from the 'getuser'   *
 *                  script.  This is utilized to control the flow of    *
 *                  this script after "getuser". Defaults to 'gotuser'. *
 * This method redirects control flow to the getuser script for         *
 * when the user logs in via Google OAuth 2.0. It first checks to see   *
 * if we have a valid session. If so, we don't need to redirect and     *
 * instead simply show the Get Certificate page. Otherwise, we start    *
 * a Google OAuth 2.0 logon by composing a parameterized GET URL using  *
 * the Google OAuth 2.0 endpoint. (See                                  *
 * https://developers.google.com/accounts/docs/OAuth2Login for more     *
 * information.)                                                        *
 ************************************************************************/
function redirectToGetGoogleOAuth2User($responsesubmit='gotuser') {
    global $csrf;
    global $log;
    global $skin;

    $providerId = GOOGLE_OIDC;

    // If the user has a valid 'uid' in the PHP session, and the
    // providerId matches the 'idp' in the PHP session, then 
    // simply go to the 'Download Certificate' button page.
    if (verifyCurrentSession($providerId)) {
        printMainPage();
    } else { // Otherwise, redirect to the Google OAuth 2.0 endpoint
        // Set PHP session varilables needed by the getuser script
        util::unsetSessionVar('logonerror');
        util::setSessionVar('responseurl',util::getScriptDir(true));
        util::setSessionVar('submit','getuser');
        util::setSessionVar('responsesubmit',$responsesubmit);
        $csrf->setCookieAndSession();
                
        // To bypass SSO at IdP, check for session var 'forceauthn' == 1
        $forceauthn = util::getSessionVar('forceauthn');
        util::unsetSessionVar('forceauthn');
        $max_auth_age = null;
        if ($forceauthn) {
            $max_auth_age = '0';
        } elseif (strlen($forceauthn)==0) { 
            // 'forceauth' was not set to '0' in the session, so 
            // check the skin's option instead.
            $forceauthn = $skin->getConfigOption('forceauthn');
            if ((!is_null($forceauthn)) && ((int)$forceauthn == 1)) {
                $max_auth_age = '0';
            }
        }

        // If we can read the Google OAuth clientid from the config file,
        // craft the Google OAuth 2.0 query string URL and redirect.
        if ((is_array(util::$ini_array)) &&
            (array_key_exists('googleoauth2.clientid',util::$ini_array))) {
            $clientid = util::$ini_array['googleoauth2.clientid'];
            $redirect_url = $providerId . '?' .
                'response_type=code&' .
                "client_id=$clientid&" .
                'scope=openid+profile+email&' .
                'state=' . $csrf->getTokenValue() . '&' .
                'openid.realm=' . 'https://' . HOSTNAME . '/' . '&' .
                'redirect_uri=' . GETOIDCUSER_URL . '&' .
                (is_null($max_auth_age) ? '' : "max_auth_age=$max_auth_age&") .
                'access_type=offline&prompt=select_account';
            header("Location: " . $redirect_url);
        } else {
            util::setSessionVar('logonerror','Unable to read config file.');
            printLogonPage();
        }
    }
}

/************************************************************************
 * Function   : printErrorBox                                           *
 * Parameter  : HTML error text to be output.                           *
 * This function prints out a bordered box with an error icon and any   *
 * passed-in error HTML text.  The error icon and text are output to    *
 * a <table> so as to keep the icon to the left of the error text.      *
 ************************************************************************/
function printErrorBox($errortext) {
    echo '
    <div class="errorbox">
    <table cellpadding="5">
    <tr>
    <td valign="top">
    ';
    printIcon('error');
    echo '&nbsp;
    </td>
    <td> ' , $errortext , '
    </td>
    </tr>
    </table>
    </div>
    ';
}

/************************************************************************
 * Function   : handleGotUser                                           *
 * This function is called upon return from one of the getuser scripts  *
 * which should have set the 'uid' and 'status' PHP session variables.  *
 * It verifies that the status return is one of STATUS_OK (even         *
 * values).  If not, we print an error message to the user.             *
 ************************************************************************/
function handleGotUser() {
    global $skin;
    global $log;
    global $idplist;

    $uid = util::getSessionVar('uid');
    $status = util::getSessionVar('status');

    // We must get and unset session vars BEFORE any HTML output since
    // a redirect may go to another site, meaning we need to update
    // the session cookie before we leave the cilogon.org domain.
    $ePPN         = util::getSessionVar('ePPN');
    $ePTID        = util::getSessionVar('ePTID');
    $firstname    = util::getSessionVar('firstname');
    $lastname     = util::getSessionVar('lastname');
    $displayname  = util::getSessionVar('displayname');
    $emailaddr    = util::getSessionVar('emailaddr');
    $idp          = util::getSessionVar('idp');
    $idpname      = util::getSessionVar('idpname');
    $affiliation  = util::getSessionVar('affiliation');
    $clientparams = json_decode(util::getSessionVar('clientparams'),true);
    $failureuri   = util::getSessionVar('failureuri');

    // Check for OIDC redirect_uri or OAuth 1.0a failureuri.
    // If found, set "Proceed" button redirect appropriately.
    $redirect = '';
    // First, check for OIDC redirect_uri
    if (isset($clientparams['redirect_uri'])) {
        $redirect = $clientparams['redirect_uri'] .
            (preg_match('/\?/',$clientparams['redirect_uri']) ? '&' : '?') .
            'error=access_denied&error_description=' . 
            'Missing%20attributes' .
            ((isset($clientparams['state'])) ? 
                '&state='.$clientparams['state'] : '');
    }
    // Next, check for OAuth 1.0a 
    if ((strlen($redirect) == 0) && (strlen($failureuri) > 0)) {
        $redirect = $failureuri. "?reason=missing_attributes";
    }

    // Check if this was an OIDC transaction, and if the 
    // 'getcert' scope was requested. Utilized to print error message 
    // to eduGAIN users without REFEDS R&S and SIRTFI.
    $oidcscopegetcert = false;
    $oidctrans = false;
    if (isset($clientparams['scope'])) {
        $oidctrans = true;
        if (preg_match('/edu\.uiuc\.ncsa\.myproxy\.getcert/',
            $clientparams['scope'])) {
            $oidcscopegetcert = true;
        }
    }

    // If empty 'uid' or 'status' or odd-numbered status code, error!
    if ((strlen($uid) == 0) || (strlen($status) == 0) || ($status & 1)) {
        // Got all session vars by now, so okay to unset.
        util::unsetAllUserSessionVars();

        $log->error('Failed to getuser.');

        printHeader('Error Logging On');

        echo '
        <div class="boxed">
        ';

        if ($status == dbservice::$STATUS['STATUS_MISSING_PARAMETER_ERROR']) {

            // Check if the problem IdP was Google: probably no first/last name
            if ($idpname == 'Google') {
                printErrorBox('
                <p>
                There was a problem logging on. It appears that you have
                attempted to use Google as your identity provider, but your
                name or email address was missing. To rectify this problem,
                go to the <a target="_blank"
                href="https://myaccount.google.com/privacy#personalinfo">Google
                Account Personal Information page</a>, and enter your First
                Name, Last Name, and email address. (All other Google
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
                printFormHead($redirect);
                echo '
                <p class="centered">
                <input type="hidden" name="providerId" value="' ,
                GOOGLE_OIDC , '" />
                <input type="submit" name="submit" class="submit"
                value="Proceed" />
                </p>
                </form>
                </div>
                ';
            } else { // Problem was missing SAML attribute from Shib IdP
                printAttributeReleaseErrorMessage(
                    $ePPN,$ePTID,$firstname,$lastname,$displayname,$emailaddr,
                    $idp,$idpname,$affiliation,$clientparams,$redirect);
            }
        } else {
            printErrorBox('An internal error has occurred. System
                administrators have been notified. This may be a temporary
                error. Please try again later, or contact us at the the email
                address at the bottom of the page.');

            echo '
            <div>
            ';
            printFormHead($redirect);
            echo '
            <input type="submit" name="submit" class="submit" value="Proceed" />
            </form>
            </div>
            ';
        }

        echo '
        </div>
        ';
        printFooter();
    } elseif (
        // Got all session vars by now, so okay to unset.
        util::unsetAllUserSessionVars();

        // Here, the dbservice did not return an error, so check to see
        // if the IdP was an eduGAIN IdP which does not have the
        // REFEDS R&S and SIRTFI metadata attributes, AND the 
        // transaction could be used to fetch an X509 certificate.
        (strlen($idp) > 0) &&  // First, make sure $idp was set
            (
                // Next, check for eduGAIN without REFEDS R&S and SIRTFI
                ((!$idplist->isRegisteredByInCommon($idp)) &&
                       ((!$idplist->isREFEDSRandS($idp)) ||
                        (!$idplist->isSIRTFI($idp))
                       )
                ) &&
                // Next, check if user could get X509 cert,
                // i.e., OIDC getcert scope, or a non-OIDC
                // transaction such as PKCS12, JWS, or OAuth 1.0a
                ($oidcscopegetcert || !$oidctrans)
            )
        ) {
        $log->error('Failed to getuser due to eduGAIN IdP restriction.');

        printHeader('Error Logging On');

        echo '
        <div class="boxed">
        ';
        printAttributeReleaseErrorMessage(
            $ePPN,$ePTID,$firstname,$lastname,$displayname,$emailaddr,
            $idp,$idpname,$affiliation,$clientparams,$redirect);

        echo '
        </div>
        ';
        printFooter();
    } else { // Got one of the STATUS_OK status codes
        // Extra security check: Once the user has successfully authenticated
        // with an IdP, verify that the chosen IdP was actually whitelisted.
        // If not, then set error message and show Select an Identity Provider
        // page again.
        $skin->init();  // Check for forced skin
        $idps = getCompositeIdPList();
        $providerId = util::getSessionVar('idp');
        if ((strlen($providerId) > 0) && (!isset($idps[$providerId]))) {
            util::setSessionVar('logonerror',
                'Invalid IdP selected. Please try again.');
            util::sendErrorAlert('Authentication attempt using non-whitelisted IdP',
    'A user successfully authenticated with an IdP, however, the 
selected IdP was not in the list of whitelisted IdPs as determined 
by the current skin. This might indicate the user attempted to 
circumvent the security check in "handleGotUser()" for valid 
IdPs for the skin.');
            util::unsetCookieVar('providerId');
            util::unsetAllUserSessionVars();
            printLogonPage();
        } else { // Check if two-factor authn is enabled and proceed accordingly
            if (twofactor::getEnabled() == 'none') {
                gotUserSuccess();
            } else {
                twofactor::printPage();
            }
        }
    }
}

/************************************************************************
 * Function  : gotUserSuccess                                           *
 * This function is called after the user has been successfully         *
 * authenticated. In the case of two-factor authentication, the user    *
 * is first authenticated by the IdP, and then by the configured        *
 * two-factor authentication method.                                    *
 * If the 'status' session variable is STATUS_OK then it checks if we   *
 * have a new or changed user and prints that page as appropriate.      *
 * Otherwise it continues to the MainPage.                              *
 ************************************************************************/
function gotUserSuccess() {
    global $skin;

    $status = util::getSessionVar('status');

    // If this is the first time the user has used the CILogon Service, we
    // skip the New User page under the following circumstances.
    // (1) We are using the OIDC authorization endpoint code flow (check for
    // 'clientparams' session variable);
    // (2) We are using the 'delegate' code flow (check for 'callbackuri'
    // session variable), and one of the following applies:
    //    (a) Skin has 'forceremember' set or
    //    (b) Skin has 'initialremember' set and there is no cookie for the
    //        current portal
    // In these cases, we skip the New User page and proceed directly to the
    // main page. Note that we still want to show the User Changed page to
    // inform the user about updated DN strings.
    $clientparams = json_decode(util::getSessionVar('clientparams'),true);
    $callbackuri = util::getSessionVar('callbackuri');
    $forceremember = $skin->getConfigOption('delegate','forceremember');

    if (($status == dbservice::$STATUS['STATUS_NEW_USER']) &&
        ((strlen($callbackuri) > 0) || 
         (isset($clientparams['code'])))) {
        // Extra check for new users: see if any HTML entities
        // are in the user name. If so, send an email alert.
        $dn = util::getSessionVar('dn');
        $dn = reformatDN(preg_replace('/\s+email=.+$/','',$dn));
        $htmldn = util::htmlent($dn);
        if (strcmp($dn,$htmldn) != 0) {
            util::sendErrorAlert('New user DN contains HTML entities',
                "htmlentites(DN) = $htmldn\n");
        }

        if (isset($clientparams['code'])) {
            // OIDC authorization code flow always skips New User page
            $status = dbservice::$STATUS['STATUS_OK'];
        } elseif (strlen($callbackuri) > 0) {
            // Delegation code flow might skip New User page
            if ((!is_null($forceremember)) && ((int)$forceremember == 1)) {
            // Check forcerememeber skin option to skip new user page
                $status = dbservice::$STATUS['STATUS_OK'];
            } else {
                // Check initialremember skin option PLUS no portal cookie
                $initialremember = 
                    $skin->getConfigOption('delegate','initialremember');
                if ((!is_null($initialremember)) && ((int)$initialremember==1)){
                    $pc = new portalcookie();
                    $portallifetime = $pc->get('lifetime');
                    if ((strlen($portallifetime)==0) || ($portallifetime==0)) {
                        $status = dbservice::$STATUS['STATUS_OK'];
                    }
                }
            }
        }
    }

    // If the user got a new DN due to changed SAML attributes,
    // print out a notification page.
    if ($status == dbservice::$STATUS['STATUS_NEW_USER']) {
        printNewUserPage();
    } elseif ($status == dbservice::$STATUS['STATUS_USER_UPDATED']) {
        printUserChangedPage();
    } else { // STATUS_OK
        printMainPage();
    }
}

/************************************************************************
 * Function   : printNewUserPage                                        *
 * This function prints out a notification page to new users showing    *
 * that this is the first time they have logged in with a particular    *
 * identity provider.                                                   *
 ************************************************************************/
function printNewUserPage() {
    global $log;

    $log->info('New User page.');

    $dn = util::getSessionVar('dn');
    $dn = reformatDN(preg_replace('/\s+email=.+$/','',$dn));

    printHeader('New User');

    echo '
    <div class="boxed">
    <br class="clear"/>
    <p>
    Welcome! Your new certificate subject is as follows. 
    </p>
    <p>
    <blockquote><tt>' , util::htmlent($dn) , '</tt></blockquote>
    </p>
    <p>
    You may need to register this certificate subject with relying parties.
    </p>
    <p>
    You will not see this page again unless the CILogon Service assigns you
    a new certificate subject. This may occur in the following situations:
    </p>
    <ul>
    <li>You log on to the CILogon Service using an identity provider other
    than ' , util::getSessionVar('idpname') , '.
    </li>
    <li>You log on using a different ' , util::getSessionVar('idpname') , '
    identity.
    </li>
    <li>The CILogon Service has experienced an internal error.
    </li>
    </ul>
    <p>
    Click the "Proceed" button to continue. If you have any questions,
    please contact us at the email address at the bottom of the page.
    </p>
    <div>
    ';
    printFormHead();
    echo '
    <p class="centered">
    <input type="submit" name="submit" class="submit" value="Proceed" />
    </p>
    </form>
    </div>
    </div>
    ';
    printFooter();
}

/************************************************************************
 * Function   : printUserChangedPage                                    *
 * This function prints out a notification page informing the user that *
 * some of their attributes have changed, which will affect the         *
 * contents of future issued certificates.  This page shows which       *
 * attributes are different (displaying both old and new values) and    *
 * what portions of the certificate are affected.                       *
 ************************************************************************/
function printUserChangedPage() {
    global $log;
    $errstr = '';

    $log->info('User IdP attributes changed.');

    $uid = util::getSessionVar('uid');
    $dbs = new dbservice();
    if (($dbs->getUser($uid)) && 
        (!($dbs->status & 1))) {  // STATUS_OK codes are even

        $idpname = $dbs->idp_display_name;
        $first   = $dbs->first_name;
        $last    = $dbs->last_name;
        $email   = $dbs->email;
        $dn      = $dbs->distinguished_name;
        $dn      = reformatDN(preg_replace('/\s+email=.+$/','',$dn));

        if (($dbs->getLastArchivedUser($uid)) &&
            (!($dbs->status & 1))) {  // STATUS_OK codes are even

            $previdpname = $dbs->idp_display_name;
            $prevfirst   = $dbs->first_name;
            $prevlast    = $dbs->last_name;
            $prevemail   = $dbs->email;
            $prevdn      = $dbs->distinguished_name;
            $prevdn      = reformatDN(
                               preg_replace('/\s+email=.+$/','',$prevdn));

            $tablerowodd = true;

            printHeader('Certificate Information Changed');

            echo '
            <div class="boxed">
            <br class="clear"/>
            <p>
            One or more of the attributes released by your organization has
            changed since the last time you logged on to the CILogon
            Service. This will affect your certificates as described below.
            </p>

            <div class="userchanged">
            <table cellpadding="5">
              <tr class="headings">
                <th>Attribute</th>
                <th>Previous Value</th>
                <th>Current Value</th>
              </tr>
            ';

            if ($idpname != $previdpname) {
                echo '
                <tr' , ($tablerowodd ? ' class="odd"' : '') , '>
                  <th>Organization Name:</th>
                  <td>'.$previdpname.'</td>
                  <td>'.$idpname.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($first != $prevfirst) {
                echo '
                <tr' , ($tablerowodd ? ' class="odd"' : '') , '>
                  <th>First Name:</th>
                  <td>'.util::htmlent($prevfirst).'</td>
                  <td>'.util::htmlent($first).'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($last != $prevlast) {
                echo '
                <tr' , ($tablerowodd ? ' class="odd"' : '') , '>
                  <th>Last Name:</th>
                  <td>'.util::htmlent($prevlast).'</td>
                  <td>'.util::htmlent($last).'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            if ($email != $prevemail) {
                echo '
                <tr' , ($tablerowodd ? ' class="odd"' : '') , '>
                  <th>Email Address:</th>
                  <td>'.$prevemail.'</td>
                  <td>'.$email.'</td>
                </tr>
                ';
                $tablerowodd = !$tablerowodd;
            }

            echo '
            </table>
            </div>
            ';

            if (($idpname != $previdpname) ||
                ($first != $prevfirst) ||
                ($last != $prevlast)) {
                echo '
                <p>
                The above changes to your attributes will cause your
                <strong>certificate subject</strong> to change. You may be
                required to re-register with relying parties using this new
                certificate subject.
                </p>
                <p>
                <blockquote>
                <table cellspacing="0">
                  <tr>
                    <td>Previous Subject DN:</td>
                    <td>' , util::htmlent($prevdn) , '</td>
                  </tr>
                  <tr>
                    <td>Current Subject DN:</td>
                    <td>' , util::htmlent($dn) , '</td>
                  </tr>
                </table>
                </blockquote>
                </p>
                ';
            }

            if ($email != $prevemail) {
                echo '
                <p>
                Your new certificate will contain your <strong>updated email
                address</strong>.
                This may change how your certificate may be used in email
                clients. Possible problems which may occur include:
                </p>
                <ul>
                <li>If your "from" address does not match what is
                    contained in the certificate, recipients may fail to
                    verify your signed email messages.</li>
                <li>If the email address in the certificate does not
                    match the destination address, senders may have
                    difficulty encrypting email addressed to you.</li>
                </ul>
                ';
            }

            echo '
            <p>
            If you have any questions, please contact us at the email
            address at the bottom of the page.
            </p>
            <div>
            ';
            printFormHead();
            echo '
            <p class="centered">
            <input type="submit" name="submit" class="submit"
            value="Proceed" />
            </p>
            </form>
            </div>
            </div>
            ';
            printFooter();

            
        } else {  // Database error, should never happen
            if (!is_null($dbs->status)) {
                $errstr = array_search($dbs->status,dbservice::$STATUS);
            }
            $log->error('Database error reading last archived ' . 
                        'user attributes. ' . $errstr);
            util::sendErrorAlert('dbService Error',
                'Error calling dbservice action "getLastArchivedUser" in ' .
                'printUserChangedPaged() method. ' . $errstr);
            util::unsetAllUserSessionVars();
            printLogonPage();
        }
    } else {  // Database error, should never happen
        if (!is_null($dbs->status)) {
            $errstr = array_search($dbs->status,dbservice::$STATUS);
        }
        $log->error('Database error reading current user attributes. ' .
                    $errstr);
        util::sendErrorAlert('dbService Error',
            'Error calling dbservice action "getUser" in ' .
            'printUserChangedPaged() method. ' . $errstr);
        util::unsetAllUserSessionVars();
        printLogonPage();
    }
}

/************************************************************************
 * Function   : generateP12                                             *
 * This function is called when the user clicks the "Get New            *
 * Certificate" button. It first reads in the password fields and       *
 * verifies that they are valid (i.e. they are long enough and match).  *
 * Then it gets a credential from the MyProxy server and converts that  *
 * certificate into a PKCS12 which is written to disk.  If everything   *
 * succeeds, the temporary pkcs12 directory and lifetime is saved to    *
 * the 'p12' PHP session variable, which is read later when the Main    *
 * Page HTML is shown.                                                  *
 ************************************************************************/
function generateP12() {
    global $log;
    global $skin;

    /* Get the entered p12lifetime and p12multiplier and set the cookies. */
    list($minlifetime,$maxlifetime) = getMinMaxLifetimes('pkcs12',9516);
    $p12lifetime   = util::getPostVar('p12lifetime');
    $p12multiplier = util::getPostVar('p12multiplier');
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
    util::setCookieVar('p12lifetime',$p12lifetime);
    util::setCookieVar('p12multiplier',$p12multiplier);
    util::setSessionVar('p12lifetime',$p12lifetime);
    util::setSessionVar('p12multiplier',$p12multiplier);

    /* Verify that the password is at least 12 characters long. */
    $password1 = util::getPostVar('password1');
    $password2 = util::getPostVar('password2');
    $p12password = util::getPostVar('p12password');  // For ECP clients
    if (strlen($p12password) > 0) {
        $password1 = $p12password;
        $password2 = $p12password;
    }
    if (strlen($password1) < 12) {   
        util::setSessionVar('p12error',
            'Password must have at least 12 characters.');
        return; // SHORT PASSWORD - NO FURTHER PROCESSING NEEDED!
    }

    /* Verify that the two password entry fields matched. */
    if ($password1 != $password2) {
        util::setSessionVar('p12error','Passwords did not match.');
        return; // MISMATCHED PASSWORDS - NO FURTHER PROCESSING NEEDED!
    }

    /* Set the port based on the Level of Assurance */
    $port = 7512;
    $loa = util::getSessionVar('loa');
    if ($loa == 'http://incommonfederation.org/assurance/silver') {
        $port = 7514;
    } elseif ($loa == 'openid') {
        $port = 7516;
    }

    $dn = util::getSessionVar('dn');
    if (strlen($dn) > 0) {
        /* Append extra info, such as 'skin', to be processed by MyProxy. */
        $myproxyinfo = util::getSessionVar('myproxyinfo');
        if (strlen($myproxyinfo) > 0) {
            $dn .= " $myproxyinfo";
        }
        /* Attempt to fetch a credential from the MyProxy server */
        $cert = getMyProxyCredential($dn,'',
            'myproxy.cilogon.org,myproxy2.cilogon.org',
            $port,$lifetime,'/var/www/config/hostcred.pem','');

        /* The 'openssl pkcs12' command is picky in that the private  *
         * key must appear BEFORE the public certificate. But MyProxy *
         * returns the private key AFTER. So swap them around.        */
        $cert2 = '';
        if (preg_match('/-----BEGIN CERTIFICATE-----([^-]+)' . 
                       '-----END CERTIFICATE-----[^-]*' . 
                       '-----BEGIN RSA PRIVATE KEY-----([^-]+)' .
                       '-----END RSA PRIVATE KEY-----/',$cert,$match)) {
            $cert2 = "-----BEGIN RSA PRIVATE KEY-----" .
                     $match[2] . "-----END RSA PRIVATE KEY-----\n".
                     "-----BEGIN CERTIFICATE-----" .
                     $match[1] . "-----END CERTIFICATE-----";
        }

        if (strlen($cert2) > 0) { // Successfully got a certificate!
            /* Create a temporary directory in /var/www/html/pkcs12/ */
            $tdirparent = '/var/www/html/pkcs12/';
            $polonum = '3';   // Prepend the polo? number to directory
            if (preg_match('/(\d+)\./',php_uname('n'),$polomatch)) {
                $polonum = $polomatch[1];
            }
            $tdir = util::tempDir($tdirparent,$polonum);
            $p12dir = str_replace($tdirparent,'',$tdir);
            $p12file = $tdir . '/usercred.p12';

            /* Call the openssl pkcs12 program to convert certificate */
            exec('/bin/env ' .
                 'CILOGON_PKCS12_PW=' . escapeshellarg($password1) . ' ' .
                 '/usr/bin/openssl pkcs12 -export ' .
                 '-passout env:CILOGON_PKCS12_PW ' .
                 "-out $p12file " .
                 '<<< ' . escapeshellarg($cert2)
                );

            /* Verify the usercred.p12 file was actually created */
            $size = @filesize($p12file);
            if (($size !== false) && ($size > 0)) {
                $p12link = 'https://' . getMachineHostname() . 
                           '/pkcs12/' . $p12dir . '/usercred.p12';
                $p12 = (time()+300) . " " . $p12link;
                util::setSessionVar('p12',$p12);
                $log->info('Generated New User Certificate="'.$p12link.'"');
            } else { // Empty or missing usercred.p12 file - shouldn't happen!
                util::setSessionVar('p12error',
                    'Error creating certificate. Please try again.');
                util::deleteDir($tdir); // Remove the temporary directory
                $log->info("Error creating certificate - missing usercred.p12");
            }
        } else { // The myproxy-logon command failed - shouldn't happen!
            util::setSessionVar('p12error',
                'Error! MyProxy unable to create certificate.');
            $log->info("Error creating certificate - myproxy-logon failed");
        }
    } else { // Couldn't find the 'dn' PHP session value - shouldn't happen!
        util::setSessionVar('p12error',
            'Missing username. Please enable cookies.');
        $log->info("Error creating certificate - missing dn session variable");
    }
}

/************************************************************************
 * Function   : getLogOnButtonText                                      *
 * Returns    : The text of the "Log On" button for the WAYF, as        *
 *              configured for the skin.  Defaults to "Log On".         *
 * This function checks the current skin to see if <logonbuttontext>    *
 * has been configured.  If so, it returns that value.  Otherwise,      *
 * it returns "Log On".                                                 *
 ************************************************************************/
function getLogOnButtonText() {
    global $skin;

    $retval = 'Log On';
    $lobt = $skin->getConfigOption('logonbuttontext');
    if (!is_null($lobt))  {
        $retval = (string)$lobt;
    }
    return $retval;
}

/************************************************************************
 * Function   : getSerialStringFromDN                                   *
 * Parameter  : The certificate subject DN (typically found in the      *
 *              session 'dn' variable)                                  *
 * Returns    : The serial string extracted from the subject DN, or     *
 *              empty string if DN is empty or wrong format.            *
 * This function takes in a CILogon subject DN and returns just the     *
 * serial string part (e.g., A325). This function is needed since the   *
 * serial_string is not stored in the PHP session as a separate         *
 * variable since it is always available in the 'dn' session variable.  *
 ************************************************************************/
function getSerialStringFromDN($dn) { 
    $serial = ''; // Return empty string upon error

    // Strip off the email address, if present
    $dn = preg_replace('/\s+email=.+$/','',$dn);
    // Find the "CN=" entry
    if (preg_match('%/DC=org/DC=cilogon/C=US/O=.*/CN=(.*)%',$dn,$match)) {
        $cn = $match[1];
        if (preg_match('/\s+([^\s]+)$/',$cn,$match)) {
            $serial = $match[1];
        }
    }
    return $serial;
}

/************************************************************************
 * Function   : getEmailFromDN                                          *
 * Parameter  : The certificate subject DN (typically found in the      *
 *              session 'dn' variable)                                  *
 * Returns    : The email address extracted from the subject DN, or     *
 *              empty string if DN is empty or wrong format.            *
 * This function takes in a CILogon subject DN and returns just the     *
 * email address part. This function is needed since the email address  *
 * is not stored in the PHP session as a separate variable since it is  *
 * always available in the 'dn' session variable.                       *
 ************************************************************************/
function getEmailFromDN($dn) {
    $email = ''; // Return empty string upon error
    if (preg_match('/\s+email=(.+)$/',$dn,$match)) {
        $email = $match[1];
    }
    return $email;
}

/************************************************************************
 * Function   : reformatDN                                              *
 * Parameter  : The certificate subject DN (without the email=... part) *
 * Returns    : The certificate subject DN transformed according to     *
 *              the value of the <dnformat> skin config option.         *
 * This function takes in a certificate subject DN with the email=...   * 
 * part already removed. It checks the skin to see if <dnformat> has    *
 * been set. If so, it reformats the DN appropriately.                  *
 ************************************************************************/
function reformatDN($dn) {
    global $skin;

    $newdn = $dn;
    $dnformat = (string)$skin->getConfigOption('dnformat');
    if (!is_null($dnformat)) {
        if (($dnformat == 'rfc2253') &&
            (preg_match('%/DC=(.*)/DC=(.*)/C=(.*)/O=(.*)/CN=(.*)%',
                        $dn,$match))) {
            array_shift($match);
            require_once('Net/LDAP2/Util.php');
            $m = array_reverse(Net_LDAP2_Util::escape_dn_value($match));
            $newdn = "CN=$m[0],O=$m[1],C=$m[2],DC=$m[3],DC=$m[4]";
        }
    }
    return $newdn;
}

/************************************************************************
 * Function   : getMinMaxLifetimes                                      *
 * Parameters : (1) The XML section block from which to read the        *
 *                  minlifetime and maxlifetime values. Can be one of   *
 *                  the following: 'pkcs12', 'gsca', or 'delegate'.     *
 *              (2) Default maxlifetime (in hours) for the credential.  *
 * Returns    : An array consisting of two entries: the minimum and     *
 *              maximum lifetimes (in hours) for a credential.          *
 * This function checks the skin's configuration to see if either or    *
 * both of minlifetime and maxlifetime in the specified config.xml      *
 * block have been set. If not, default to minlifetime of 1 (hour) and  *
 * the specified defaultmaxlifetime.                                    *
 ************************************************************************/
function getMinMaxLifetimes($section,$defaultmaxlifetime) {
    global $skin;

    $minlifetime = 1;    // Default minimum lifetime is 1 hour
    $maxlifetime = $defaultmaxlifetime;
    $skinminlifetime = $skin->getConfigOption($section,'minlifetime');
    // Read the skin's minlifetime value from the specified section
    if ((!is_null($skinminlifetime)) && ((int)$skinminlifetime > 0)) {
        $minlifetime = max($minlifetime,(int)$skinminlifetime);
        // Make sure $minlifetime is less than $maxlifetime;
        $minlifetime = min($minlifetime,$maxlifetime);
    }
    // Read the skin's maxlifetime value from the specified section
    $skinmaxlifetime = $skin->getConfigOption($section,'maxlifetime');
    if ((!is_null($skinmaxlifetime)) && ((int)$skinmaxlifetime) > 0) {
        $maxlifetime = min($maxlifetime,(int)$skinmaxlifetime);
        // Make sure $maxlifetime is greater than $minlifetime
        $maxlifetime = max($minlifetime,$maxlifetime);
    }

    return array($minlifetime,$maxlifetime);
}

/********************************************************************
 * Function   : getMachineHostname                                  *
 * Returns    : The full cilogon-specific hostname of this host.    *
 * This function is utilized in the formation of the URL for the    *
 * PKCS12 credential download link.  It returns a host-specific     *
 * URL hostname by mapping the local machine hostname (as returned  *
 * by 'uname -n') to an InCommon metadata cilogon.org hostname      *
 * (e.g., polo2.cilogon.org). This function contains an array       *
 * '$hostnames' where the values are the local machine hostname and *
 * the keys are the *.cilogon.org hostname. Since this array is     *
 * fairly static, I didn't see the need to read it in from a config *
 * file. In case the local machine hostname cannot be found in the  *
 * $hostnames array, 'cilogon.org' is returned by default.          *
 ********************************************************************/
function getMachineHostname() {
    $retval = 'cilogon.org';
    $hostnames = array(
        "polo1.ncsa.illinois.edu"        => "polo1.cilogon.org" ,
        "poloa.ncsa.illinois.edu"        => "polo1.cilogon.org" ,
        "polo2.ncsa.illinois.edu"        => "polo2.cilogon.org" ,
        "polob.ncsa.illinois.edu"        => "polo2.cilogon.org" ,
        "fozzie.nics.utk.edu"            => "polo3.cilogon.org" ,
        "poloc.ncsa.illinois.edu"        => "test.cilogon.org" ,
        "polot.ncsa.illinois.edu"        => "test.cilogon.org" ,
        "polo-staging.ncsa.illinois.edu" => "test.cilogon.org" ,
    );
    $localhost = php_uname('n');
    if (array_key_exists($localhost,$hostnames)) {
        $retval = $hostnames[$localhost];
    }
    return $retval;
}

/************************************************************************
 * Function   : getCompositeIdPList                                     *
 * Parameter  : Show all InCommon IdPs in selection list? True or       *
 *              false. Defaults to false, which means show only         *
 *              whitelisted IdPs.                                       *
 * This function generates a list of IdPs to display in the "Select     *
 * An Identity Provider" box on the main CILogon page or on the         *
 * TestIdP page. For the main CILogon page, this is a filtered list of  *
 * IdPs based on the skin's whitelist/blacklist and the global          *
 * blacklist file. For the TestIdP page, the list is all InCommon IdPs. *
 ************************************************************************/
function getCompositeIdPList($incommonidps=false) {
    global $skin;
    global $idplist;

    $retarray = array();

    if ($incommonidps) { /* Get all InCommon IdPs only */
        $retarray = $idplist->getInCommonIdPs();
    } else { /* Get the whitelisted InCommon IdPs, plus maybe Google  */
        $retarray = $idplist->getWhitelistedIdPs();

        /* Add Google to the list */
        $retarray[GOOGLE_OIDC] = 'Google';

        /* Check to see if the skin's config.xml has a whitelist of IDPs.  */
        /* If so, go thru master IdP list and keep only those IdPs in the  */
        /* config.xml's whitelist.                                         */
        if ($skin->hasIdpWhitelist()) {
            foreach ($retarray as $entityId => $displayName) {
                if (!$skin->idpWhitelisted($entityId)) {
                    unset($retarray[$entityId]);
                }
            }
        }
        /* Next, check to see if the skin's config.xml has a blacklist of  */
        /* IdPs. If so, cull down the master IdP list removing 'bad' IdPs. */
        if ($skin->hasIdpBlacklist()) {
            $idpblacklist = $skin->getConfigOption('idpblacklist');
            foreach ($idpblacklist->idp as $blackidp) {
                unset($retarray[(string)$blackidp]);
            }
        }

        /* Check the global blacklist.txt file and remove any IdPs listed. */
        $globalblacklistfile = '/var/www/html/include/blacklist.txt';
        $globalblackidps = util::readArrayFromFile($globalblacklistfile);
        foreach (array_keys($globalblackidps) as $blackidp) {
            unset($retarray[(string)$blackidp]);
        }
    }

    // Fix for CIL-174 - As suggested by Keith Hazelton, replace commas and
    // hyphens with just commas. Resort list for correct alphabetization.
    $regex = '/(University of California)\s*[,-]\s*/';
    foreach ($retarray as $entityId => $idpName) {
        if (preg_match($regex,$idpName)) {
            $retarray[$entityId] = preg_replace($regex,'$1, ',$idpName);
        }
    }
    uasort($retarray,'strcasecmp');

    return $retarray;
}

/************************************************************************
 * Function   : printAttributeReleaseErrorMessage                       *
 * Parameters : The various parameters for the user set by the getuser  *
 *              endpoints.                                              *
 * This is a convenience method called by handleGotUser to print out    *
 * the attribute release error page to the user.                        *
 ************************************************************************/
function printAttributeReleaseErrorMessage(
    $ePPN,$ePTID,$firstname,$lastname,$displayname,$emailaddr,
    $idp,$idpname,$affiliation,$clientparams,$redirect) {

    global $idplist;

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
        $missingattrs .= '%0D%0A    eduPersonPrincipalName'. 
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
    if ((strlen($displayname) == 0) &&
        ((strlen($firstname) == 0) || (strlen($lastname) == 0))) {
        $errorboxstr .= 
        '<tr><th>Display Name:</th><td>MISSING</td></tr>';
        $missingattrs .= '%0D%0A    displayName'; 
    }
    $emailvalid = filter_var($emailaddr,FILTER_VALIDATE_EMAIL);
    if ((strlen($emailaddr) == 0) || (!$emailvalid)) {
        $errorboxstr .= 
        '<tr><th>Email Address:</th><td>' . 
        ((strlen($emailaddr) == 0) ? 'MISSING' : 'INVALID') .
        '</td></tr>';
        $missingattrs .= '%0D%0A    mail (email address)'; 
    }
    // CIL-326 - For eduGAIN IdPs, check for R&S and SIRTFI
    if (!$idplist->isRegisteredByInCommon($idp)) {
        if (!$idplist->isREFEDSRandS($idp)) {
            $errorboxstr .= 
            '<tr><th><a target="_blank"
            href="http://refeds.org/category/research-and-scholarship">Research and Scholarship</a>:</th><td>MISSING</td></tr>';
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
    if ((strlen($emailaddr) == 0 ) && 
        (preg_match('/student@/',$affiliation))) {
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
    'https://cilogon.org/ using the ' . $idpname .
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

    $namefound = false;
    $name = @$shibarray['Support Name'];
    $addr = @$shibarray['Support Address'];
    $addr = preg_replace('/^mailto:/','',$addr);
    if ((strlen($name) > 0) && (strlen($addr) > 0)) {
        $namefound = true;
        $errorboxstr .= '<li> Support Contact: ' .
            $name . ' &lt;<a href="mailto:' . 
            $addr . $emailmsg . '">' . 
            $addr . '</a>&gt;</li>';
    }

    if (!$namefound) {
        $name = @$shibarray['Technical Name'];
        $addr = @$shibarray['Technical Address'];
        $addr = preg_replace('/^mailto:/','',$addr);
        if ((strlen($name) > 0) && (strlen($addr) > 0)) {
            $namefound = true;
            $errorboxstr .= '<li> Technical Contact: ' .
                $name . ' &lt;<a href="mailto:' . 
                $addr . $emailmsg . '">' . 
                $addr . '</a>&gt;</li>';
        }
    }

    if (!$namefound) {
        $name = @$shibarray['Administrative Name'];
        $addr = @$shibarray['Administrative Address'];
        $addr = preg_replace('/^mailto:/','',$addr);
        if ((strlen($name) > 0) && (strlen($addr) > 0)) {
            $errorboxstr .= '<li>Administrative Contact: ' .
                $name . ' &lt;<a href="mailto:' . 
                $addr . $emailmsg.'">' . 
                $addr . '</a>&gt</li>';
        }
    }

    $errorboxstr .= '</ul></blockquote>
    
    <p> Alternatively, you can contact us at the email address
    at the bottom of the page.</p>
    ';

    printErrorBox($errorboxstr);
    
    echo '
    <div>
    ';

    printFormHead($redirect);
    echo '
    <input type="submit" name="submit" class="submit"
    value="Proceed" />
    </form>
    </div>
    ';
}

?>
