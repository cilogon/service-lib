<?php

namespace CILogon\Service;

use CILogon\Service\Util;
use DOMDocument;
use DOMImplementation;
use XSLTProcessor;
use SimpleXMLElement;

/**
 * IdpList
 *
 * This class manages the list of SAML-based IdPs and their
 * attributes of interest. Since the InCommon-metadata.xml
 * file is rather large and slow to parse using xpath
 * queries, this class creates/reads/writes a smaller
 * file containing only the IdPs and the few attributes
 * needed by the CILogon Service.
 *
 * When you create a new instance of this class via
 * '$idplist = new idplist();', the code first tries to
 * read in a previously created idplist file. If no
 * such file can be read in successfully, the 'new' method
 * then reads in the big InCommon metadata in order to
 * create the class idparray and idpdom and write the
 * idplist file. You can (re)create the file at any time
 * by calling create() (which re-reads the InCommon
 * metadata) followed by write() (which writes the idplist
 * to file).
 *
 * Note that this class previously defaulted to writing the idplist
 * as an XML file and then reading that XML file back in. When all
 * InCommon and eduGAIN IdPs were allowed, the xpath queries
 * used on the read-in XML file were painfully slow. So now the default
 * is to read/write a JSON file and use a protected $idparray which is
 * an order of magnitude faster than xpath queries. You can still
 * read/write the XML file either by calling the associated read/write
 * methods (readXML/writeXML) or by setting $filetype='xml' in the
 * contructor. You will probably want to do this for the hourly cronjob
 * since the idplist.xml file is known to the world and should be
 * updated regularly.
 *
 * Example usage:
 *    require_once 'IdpList.php';
 *    // Read in extant idplist.json file, or create one from scratch
 *    $idplist = new IdpList();
 *    // Rescan InCommon metadata, update IdP list, and write to file
 *    $idplist->create();
 *    $idplist->setFilename('/tmp/newidplist.json');
 *    $idplist->write();
 */
class IdpList
{
    /**
     * @var DOMDocument $idpdom A DOMDocument which holds the list of IdP
     *      entityIDs and their corresponding attributes.
     */
    protected $idpdom = null;

    /**
     * @var mixed $idparray An array version of $idpdom. It is used
     * primarily since searching an array is faster than xpath query.
     */
    public $idparray = null;

    /**
     * @var string $idpfilename The name of the IdP list in JSON format.
     *      Defaults to DEFAULT_IDP_JSON.
     */
    protected $idpfilename;

    /**
     * @var string $incommonfilename The name of the InCommon metadata XML
     *      file. Defaults to DEFAULT_INCOMMON_XML.
     */
    protected $incommonfilename;

    /**
     * __construct
     *
     * Default constructor. This method first attempts to read in an
     * existing idplist from a file and store it in the idparray /
     * idpdom. If a valid idplist file cannot be read and
     * $createfile is true, neW idparray / idpdom is created and
     * written to file.
     *
     * @param string $idpfilename (Optional) The name of the idplist file to
     *        read/write. Defaults to DEFAULT_IDP_JSON.
     * @param string $incommonfilename (Optional) The name of the InCommon
     *        metadata file to read. Defaults to DEFAULT_INCOMMON_XML.
     * @param bool $createfile (Optional) Create idplist file if it doesn't
     *         exist? Defaults to true.
     * @param string $filetype (Optional) The type of file to read/write,
     *        one of 'xml' or 'json'. Defaults to 'json'.
     */
    public function __construct(
        $idpfilename = DEFAULT_IDP_JSON,
        $incommonfilename = DEFAULT_INCOMMON_XML,
        $createfile = true,
        $filetype = 'json'
    ) {
        $this->setFilename($idpfilename);
        $this->setInCommonFilename($incommonfilename);
        $result = $this->read($filetype);
        if (($result === false) && ($createfile)) {
            $this->create();
            $this->write($filetype);
        }
    }

    /**
     * read
     *
     * This reads in the idplixt file based on the input filetype.
     * Defaults to reading in a JSON file.
     *
     * @param string $filetype (Optional) Type type of file to read, either
     *        'xml' or 'json'. Defaults to 'json'.
     * @return bool True if the idplist was read from file. False otherwise.
     */
    public function read($filetype = 'json')
    {
        if ($filetype == 'xml') {
            return $this->readXML();
        } elseif ($filetype == 'json') {
            return $this->readJSON();
        }
    }

    /**
     * readXML
     *
     * This method attempts to read in an existing idplist XML file and
     * store its contents in the class $idpdom DOMDocument. It also
     * converts the $idpdom to the internal $idparray if not already
     * set.
     *
     * @return bool True if the idplist file was read in correctly.
     *         False otherwise.
     */
    public function readXML()
    {
        $retval = false;  // Assume read failed

        $filename = $this->getFilename();
        $doc = new DOMDocument();
        if (
            (is_readable($filename)) &&
            (($doc->load($filename, LIBXML_NOBLANKS)) !== false)
        ) {
            $this->idpdom = $doc;
            $this->idpdom->preserveWhiteSpace = false;
            $this->idpdom->formatOutput = true;
            // Convert the read-in DOM to idparray for later use
            if (is_null($this->idparray)) {
                $this->idparray = $this->DOM2Array($this->idpdom);
            }
            $retval = true;
        } else {
            $this->idpdom = null;
        }

        return $retval;
    }

    /**
     * readJSON
     *
     * This method attempts to read in an existing idplist file
     * (containing JSON) and store its contents in the class $idparray.
     * Note that this does not update the internal $idpdom.
     *
     * @return bool True if the idplist file was read in correctly.
     *         False otherwise.
     */
    public function readJSON()
    {
        $retval = false;  // Assume read/json_decode failed

        $filename = $this->getFilename();
        if (
            (is_readable($filename)) &&
            (($contents = file_get_contents($filename)) !== false) &&
            (($tempjson = json_decode($contents, true)) !== null)
        ) {
            $this->idparray = $tempjson;
            $retval = true;
        } else {
            $this->idparray = null;
        }
        return $retval;
    }

    /**
     * write
     *
     * This writes out the idplixt file based on the input filetype.
     * Defaults to writing a JSON file.
     *
     * @param string $filetype (Optional) Type type of file to write, either
     *        'xml' or 'json'. Defaults to 'json'.
     * @return bool True if the idplist was written to file. False
     *         otherwise.
     */
    public function write($filetype = 'json')
    {
        if ($filetype == 'xml') {
            return $this->writeXML();
        } elseif ($filetype == 'json') {
            return $this->writeJSON();
        }
    }

    /**
     * writeXML
     *
     * This method writes the class $idpdom to an XML file. It does
     * this by first writing to a temporary file in /tmp, then renaming
     * the temp file to the final idplist XML filename. Note that if
     * the internal $idpdom does not exist, it attempts to first
     * convert the internal $idparray to DOM and then write it.
     *
     * @return bool True if the idpdom was written to the idplist XML
     *         file. False otherwise.
     */
    public function writeXML()
    {
        $retval = false; // Assume write failed

        // If no idpdom, convert idparray to DOM
        if (is_null($this->idpdom)) {
            $this->idpdom = $this->array2DOM($this->idparray);
        }

        if (!is_null($this->idpdom)) {
            $this->idpdom->preserveWhiteSpace = false;
            $this->idpdom->formatOutput = true;
            $filename = $this->getFilename();
            $tmpfname = tempnam(sys_get_temp_dir(), 'IDP');
            if (
                ($this->idpdom->save($tmpfname) > 0) &&
                (@rename($tmpfname, $filename))
            ) {
                @chmod($filename, 0664);
                $retval = true;
            } else {
                @unlink($tmpfname);
            }
        }

        return $retval;
    }

    /**
     * writeJSON
     *
     * This method writes the class $idparray to a JSON file
     * It does this by first writing to a temporary file in /tmp,
     * then renaming the temp file to the final idplist JSON filename.
     *
     * @return bool True if the idparray was written to the idplist
     *         JSON file. False otherwise.
     */
    public function writeJSON()
    {
        $retval = false; // Assume write failed

        if (!is_null($this->idparray)) {
            $filename = $this->getFilename();
            $tmpfname = tempnam(sys_get_temp_dir(), 'JSON');
            $json = json_encode(
                $this->idparray,
                JSON_FORCE_OBJECT | JSON_UNESCAPED_SLASHES
            );
            if (
                ((file_put_contents($tmpfname, $json)) !== false) &&
                (@rename($tmpfname, $filename))
            ) {
                @chmod($filename, 0664);
                $retval = true;
            } else {
                @unlink($tmpfname);
            }
        }

        return $retval;
    }

    /**
     * addNode
     *
     * This is a convenience method used by create() to add a new
     * child node (such as 'Organization_Name') to a parent idp node.
     * It also adds elements to the internal $idparray, thus creating
     * the internal idparray at the same time as the idpdom.
     *
     * @param \DOMDocument $dom A DOMDocument object
     * @param \DOMElement $idpnode A pointer to a parent <idp> DOMElement
     * @param string $nodename The name of the new child node DOMElement
     * @param string $nodevalue The value of the new child node DOMElement
     */
    private function addNode($dom, $idpnode, $nodename, $nodevalue)
    {
        $nodename = trim($nodename);    // Remove leading/trailing
        $nodevalue = trim($nodevalue);  // spaces, tabs, etc.
        $elemnode = $dom->createElement($nodename);
        $textnode = $dom->createTextNode($nodevalue);
        $elemnode->appendChild($textnode);
        $idpnode->appendChild($elemnode);
        // Also add element to the internal $idparray for later use
        $this->idparray[$idpnode->getAttribute('entityID')][$nodename] =
            $nodevalue;
    }

    /**
     * sortDOM
     *
     * This method is called by create() to sort the newly created
     * DOMDocument <idp> nodes by Display_Name. It uses an XSL
     * transformation to do the work. A new DOMDocument is created
     * and returned.
     *
     * @param DOMDocument $dom A DOMDocument to be sorted by Display_Name
     * @return DOMDocument A new DOMDocument with the <idp> elements sorted by
     *         Display_Name.
     */
    private function sortDOM($dom)
    {
        $xsltsort = <<<EOT
            <xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                            version="1.0">
            <xsl:output method="xml" encoding="UTF-8"/>
            <xsl:template match="node() | @*">
              <xsl:copy>
                <xsl:apply-templates select="node() | @*">
                  <xsl:sort select="translate(Display_Name,
                      'abcdefghijklmnopqrstuvwxyz',
                      'ABCDEFGHIJKLMNOPQRSTUVWXYZ')"
                  data-type="text" order="ascending"/>
                </xsl:apply-templates>
              </xsl:copy>
            </xsl:template>
            </xsl:stylesheet>
EOT;
        $xsl = new DOMDocument('1.0');
        $xsl->loadXML($xsltsort);
        $proc = new XSLTProcessor();
        $proc->importStyleSheet($xsl);
        $newdom = $proc->transformToDoc($dom);
        return $newdom;
    }

    /**
     * create
     *
     * This method is used to populate the class $idpdom DOMDocument
     * using information from the InCommon metadata file. Note that
     * method updates $idpdom and $idparray. If you want to save either
     * to a file, be sure to call write() afterwards.
     *
     * @return bool True upon successful extraction of IdP information
     *         from the InCommon metadata file into the class
     *         $idpdom DOMDocument. False otherwise.
     */
    public function create()
    {
        $retval = false; // Assume create failed
        if (is_readable($this->getInCommonFilename())) {
            // Read in the InCommon metadata file
            $xmlstr = @file_get_contents($this->getInCommonFilename());
            if (strlen($xmlstr) > 0) {
                // Need to fix the namespace for Xpath queries to work
                $xmlstr = str_replace('xmlns=', 'ns=', $xmlstr);
                $xml = new SimpleXMLElement($xmlstr);

                // Select only IdPs from the InCommon metadata
                $result = $xml->xpath(
                    "//EntityDescriptor/IDPSSODescriptor" .
                    "/ancestor::EntityDescriptor"
                );

                // Create a DOMDocument to build up the list of IdPs.
                $domi = new DOMImplementation();
                $dom = $domi->createDocument(null, 'idps');
                $idps = $dom->documentElement; // Top level <idps> element

                // Loop through the IdPs searching for desired attributes
                foreach ($result as $idx) {
                    // Need to set namespace prefixes for xpath queries to work
                    $sxe = $idx[0];
                    $sxe->registerXPathNamespace(
                        'mdattr',
                        'urn:oasis:names:tc:SAML:metadata:attribute'
                    );
                    $sxe->registerXPathNamespace(
                        'saml',
                        'urn:oasis:names:tc:SAML:2.0:assertion'
                    );
                    $sxe->registerXPathNamespace(
                        'mdrpi',
                        'urn:oasis:names:tc:SAML:metadata:rpi'
                    );
                    $sxe->registerXPathNamespace(
                        'mdui',
                        'urn:oasis:names:tc:SAML:metadata:ui'
                    );

                    // Skip any hide-from-discovery entries
                    $xp = $sxe->xpath(
                        "Extensions/mdattr:EntityAttributes/" .
                        "saml:Attribute[@Name='" .
                        "http://macedir.org/entity-category']/" .
                        "saml:AttributeValue"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $hide = false;
                        foreach ($xp as $value) {
                            if ($value == 'http://refeds.org/category/hide-from-discovery') {
                                $hide = true;
                                break;
                            }
                        }
                        if ($hide) {
                            continue;
                        }
                    }

                    // Get the entityID of the IdP. Save it for later.
                    // The entityID will be the keys of the class idpdom.
                    $entityID = '';
                    $xp = $idx[0]->xpath('attribute::entityID');
                    if (($xp !== false) && (count($xp) > 0)) {
                        $entityID = (string)$xp[0]->entityID;
                    } else { // No entityID is bad!
                        continue;
                    }

                    // CIL-741 Omit IdPs in the global REDLIT_IDP_ARRAY
                    if (
                        (defined('REDLIT_IDP_ARRAY')) &&
                        (in_array($entityID, REDLIT_IDP_ARRAY))
                    ) {
                        continue;
                    }

                    // Create an <idp> element to hold sub elements
                    $idp = $dom->createElement('idp');
                    $idp->setAttribute('entityID', $entityID);
                    $idps->appendChild($idp);

                    // Search for the desired <idp> attribute sub-blocks

                    // Look for OrganizationDisplayName and mdui:DisplayName.
                    $Organization_Name = '';
                    $Display_Name = '';

                    $xp = $idx[0]->xpath(
                        "Organization/OrganizationDisplayName[starts-with(@xml:lang,'en')]"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $Organization_Name = (string)$xp[0];
                    }

                    $xp = $sxe->xpath(
                        "IDPSSODescriptor/Extensions/mdui:UIInfo/mdui:DisplayName[starts-with(@xml:lang,'en')]"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $Display_Name = (string)$xp[0];
                    }

                    // If neither OrganizationDisplayName nor mdui:DisplayName
                    // was found, then use the entityID as a last resort.
                    if (
                        (strlen($Organization_Name) == 0) &&
                        (strlen($Display_Name) == 0)
                    ) {
                        $Organization_Name = $entityID;
                        $Display_Name = $entityID;
                    }

                    // Add nodes for both Organization_Name and Display_Name,
                    // using the value of the other if one is empty.
                    $this->addNode(
                        $dom,
                        $idp,
                        'Organization_Name',
                        ((strlen($Organization_Name) > 0) ?
                            $Organization_Name :
                            $Display_Name)
                    );
                    $this->addNode(
                        $dom,
                        $idp,
                        'Display_Name',
                        ((strlen($Display_Name) > 0) ?
                            $Display_Name :
                            $Organization_Name)
                    );

                    $xp = $idx[0]->xpath('Organization/OrganizationURL');
                    if (($xp !== false) && (count($xp) > 0)) {
                        $this->addNode($dom, $idp, 'Home_Page', (string)$xp[0]);
                    }

                    $name = '';
                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='support']/GivenName"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $name = (string)$xp[0];
                    }
                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='support']/SurName"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $name .= ((strlen($name) > 0) ? ' ' : '') .
                            (string)($xp[0]);
                    }
                    if (strlen($name) > 0) {
                        $this->addNode($dom, $idp, 'Support_Name', $name);
                    }

                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='support']/EmailAddress"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $this->addNode(
                            $dom,
                            $idp,
                            'Support_Address',
                            (string)$xp[0]
                        );
                    }

                    $name = '';
                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='technical']/GivenName"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $name = (string)$xp[0];
                    }
                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='technical']/SurName"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $name .= ((strlen($name) > 0) ? ' ' : '') .
                            (string)($xp[0]);
                    }
                    if (strlen($name) > 0) {
                        $this->addNode($dom, $idp, 'Technical_Name', $name);
                    }

                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='technical']/EmailAddress"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $this->addNode(
                            $dom,
                            $idp,
                            'Technical_Address',
                            (string)$xp[0]
                        );
                    }

                    $name = '';
                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='administrative']/GivenName"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $name = (string)$xp[0];
                    }
                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='administrative']/SurName"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $name .= ((strlen($name) > 0) ? ' ' : '') .
                            (string)($xp[0]);
                    }
                    if (strlen($name) > 0) {
                        $this->addNode($dom, $idp, 'Administrative_Name', $name);
                    }

                    $xp = $idx[0]->xpath(
                        "ContactPerson[@contactType='administrative']/EmailAddress"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $this->addNode(
                            $dom,
                            $idp,
                            'Administrative_Address',
                            (string)$xp[0]
                        );
                    }

                    // Check for assurance-certification = silver, bronze, or SIRTFI
                    $xp = $sxe->xpath(
                        "Extensions/mdattr:EntityAttributes/" .
                        "saml:Attribute[@Name='" .
                        "urn:oasis:names:tc:SAML:attribute:" .
                        "assurance-certification']/saml:AttributeValue"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        foreach ($xp as $value) {
                            if ($value == 'http://id.incommon.org/assurance/silver') {
                                $this->addNode($dom, $idp, 'Silver', '1');
                            } elseif ($value == 'http://id.incommon.org/assurance/bronze') {
                                $this->addNode($dom, $idp, 'Bronze', '1');
                            } elseif ($value == 'https://refeds.org/sirtfi') {
                                $this->addNode($dom, $idp, 'SIRTFI', '1');
                            }
                        }
                    }

                    // Check for registered-by-incommon
                    $xp = $sxe->xpath(
                        "Extensions/mdattr:EntityAttributes/" .
                        "saml:Attribute[@Name='" .
                        "http://macedir.org/entity-category']/" .
                        "saml:AttributeValue"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        foreach ($xp as $value) {
                            if ($value == 'http://id.incommon.org/category/registered-by-incommon') {
                                $this->addNode(
                                    $dom,
                                    $idp,
                                    'Registered_By_InCommon',
                                    '1'
                                );
                                break;
                            }
                        }
                    }

                    // Check for research-and-scholarship
                    $xp = $sxe->xpath(
                        "Extensions/mdattr:EntityAttributes/" .
                        "saml:Attribute[@Name='" .
                        "http://macedir.org/entity-category-support']/" .
                        "saml:AttributeValue"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $addedrands = false;
                        $incommonrands = false;
                        $refedsrands = false;
                        foreach ($xp as $value) {
                            if ($value == 'http://id.incommon.org/category/research-and-scholarship') {
                                $incommonrands = true;
                                $this->addNode($dom, $idp, 'InCommon_RandS', '1');
                            }
                            if ($value == 'http://refeds.org/category/research-and-scholarship') {
                                $refedsrands = true;
                                $this->addNode($dom, $idp, 'REFEDS_RandS', '1');
                            }
                            if (
                                (!$addedrands) &&
                                ($incommonrands || $refedsrands)
                            ) {
                                $addedrands = true;
                                $this->addNode($dom, $idp, 'RandS', '1');
                            }
                        }
                    }

                    // CIL-558 Check for <SingleLogoutService>
                    $Logout = '';
                    // First, check for HTTP-Redirect version
                    $xp = $sxe->xpath("IDPSSODescriptor/SingleLogoutService" .
                        "[@Binding='urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect']");
                    if (($xp !== false) && (count($xp) > 0)) {
                        $Logout = (string)($xp[0]->attributes())['Location'];
                    }
                    // If no HTTP-Redirect, check for HTTP-POST
                    if (empty($Logout)) {
                        $xp = $sxe->xpath("IDPSSODescriptor/SingleLogoutService" .
                            "[@Binding='urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST']");
                        if (($xp !== false) && (count($xp) > 0)) {
                            $Logout = (string)($xp[0]->attributes())['Location'];
                        }
                    }
                    // Finally, a hack for Shibboleth-based IdPs.
                    // Check for <SingleSignOnService> HTTP-Redirect
                    // and regex for the built-in Simple Logout URL.
                    if (empty($Logout)) {
                        $xp = $sxe->xpath("IDPSSODescriptor/SingleSignOnService" .
                            "[@Binding='urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect']");
                        if (($xp !== false) && (count($xp) > 0)) {
                            $tmp = (string)($xp[0]->attributes())['Location'];
                            if (preg_match('|^(.*)/profile/|', $tmp, $matches)) {
                                $Logout = $matches[1] . '/profile/Logout';
                            }
                        }
                    }
                    if (!empty($Logout)) {
                        // If Shib IdP, transform URL into Simple Logout URL
                        // https://wiki.shibboleth.net/confluence/x/AAJSAQ
                        if (preg_match('|^(.*)/profile/|', $Logout, $matches)) {
                            $Logout = $matches[1] . '/profile/Logout';
                        }
                        $this->addNode($dom, $idp, 'Logout', $Logout);
                    }
                }

                // Read in any test IdPs and add them to the list
                $doc = new DOMDocument();
                if (
                    (defined('TEST_IDP_XML')) &&
                    (!empty(TEST_IDP_XML)) &&
                    (is_readable(TEST_IDP_XML)) &&
                    ((@$doc->load(TEST_IDP_XML)) !== false)
                ) {
                    $idpnodes = $doc->getElementsByTagName('idp');
                    foreach ($idpnodes as $idpnode) {
                        // Check if the entityID already exists. If so,
                        // delete it from both the idps DOM and the idparray
                        // and instead add the one from the testidplist.
                        $entityID = $idpnode->attributes->item(0)->value;
                        if (array_key_exists($entityID, $this->idparray)) {
                            // Easy - simply delete the array entry for the
                            // existing entityID
                            unset($this->idparray[$entityID]);

                            // Hard - search through the current DOM for a
                            // matching entityID to get the DOMNode, which
                            // can then be removed from the DOM.
                            $curridpnodes = $dom->getElementsByTagName('idp');
                            foreach ($curridpnodes as $curridpnode) {
                                $currEntityID =
                                    $curridpnode->attributes->item(0)->value;
                                if ($currEntityID == $entityID) {
                                    $idps->removeChild($curridpnode);
                                    break;
                                }
                            }
                        }

                        // Add the new idp node to the DOM
                        $node = $dom->importNode($idpnode, true);
                        $idps->appendChild($node);

                        // Add the testidplist nodes to the $idparray
                        foreach ($node->childNodes as $child) {
                            if ($child->nodeName != '#text') {
                                $this->idparray[$entityID][$child->nodeName] =
                                    $child->nodeValue;
                            }
                        }
                    }
                }

                // Sort the DOMDocument and idparray by Display_Name
                $this->idpdom = $this->sortDOM($dom);
                uasort($this->idparray, function ($a, $b) {
                    return strcasecmp(
                        $a['Display_Name'],
                        $b['Display_Name']
                    );
                });

                $retval = true;
            }
        }

        return $retval;
    }

    /**
     * getFilename
     *
     * This function returns a string of the full path of the IdP list
     * filename.  See also setFilename().
     *
     * @return string The IdP list filename.
     */
    public function getFilename()
    {
        return $this->idpfilename;
    }

    /**
     * setFilename
     *
     * This function sets the string of the full path of the IdP list
     * filename.  See also getFilename().
     *
     * @param string $filename he new name of the IdP list filename.
     */
    public function setFilename($filename)
    {
        $this->idpfilename = $filename;
    }

    /**
     * getInCommonFilename
     *
     * This function returns a string of the full path of the InCommon
     * metadata filename.  See also setInCommonFilename().
     *
     * @return string The InCommon metadata filename.
     */
    public function getInCommonFilename()
    {
        return $this->incommonfilename;
    }

    /**
     * setInCommonFilename
     *
     * This function sets the string of the full path of the InCommon
     * metadata filename.  See also getInCommonFilename().
     *
     * @param string $filename The new name of the InCommon metadata filename.
     */
    public function setInCommonFilename($filename)
    {
        $this->incommonfilename = $filename;
    }

    /**
     * getEntityIDs
     *
     * This method returns the entityIDs of the idplist as an array.
     *
     * @return array An array of the entityIDs
     */
    public function getEntityIDs()
    {
        $retarr = array();
        if (is_array($this->idparray)) {
            $retarr = array_keys($this->idparray);
        }
        return $retarr;
    }

    /**
     * getOrganizationName
     *
     * This function returns the Organization_Name of the selected
     * $entityID.
     *
     * @param string $entityID The entityID to search for
     * @return string The Organization_Name for the $entityID. Return
     *         string is empty if no matching $entityID found.
     */
    public function getOrganizationName($entityID)
    {
        $retval = '';

        if (
            ($this->exists($entityID)) &&
            (isset($this->idparray[$entityID]['Organization_Name']))
        ) {
            $retval = $this->idparray[$entityID]['Organization_Name'];
        }
        return $retval;
    }

    /**
     * getDisplayName
     *
     * This function returns the Display_Name of the selected
     * $entityID.
     *
     * @param string $entityID The entityID to search for
     * @return string The Display_Name for the $entityID. Return
     *         string is empty if no matching $entityID found.
     */
    public function getDisplayName($entityID)
    {
        $retval = '';
        if (
            ($this->exists($entityID)) &&
            (isset($this->idparray[$entityID]['Display_Name']))
        ) {
            $retval = $this->idparray[$entityID]['Display_Name'];
        }
        return $retval;
    }

    /**
     * getLogout
     *
     * This function returns the Logout URL of the selected $entityID.
     *
     * @param string $entityID The entityID to search for
     * @return string The Logout  URLfor the $entityID. Return
     *         string is empty if no matching $entityID found.
     */
    public function getLogout($entityID)
    {
        $retval = '';
        if (
            ($this->exists($entityID)) &&
            (isset($this->idparray[$entityID]['Logout']))
        ) {
            $retval = $this->idparray[$entityID]['Logout'];
        }
        return $retval;
    }

    /**
     * entityIDExists
     *
     * This function searchs for the given idp entityID.
     *
     * @param string $entityID The entityID to search for
     * @return bool True if the given entityID is found. False otherwise.
     */
    public function entityIDExists($entityID)
    {
        return (isset($this->idparray[$entityID]));
    }

    /**
     * exists
     *
     * This is simply a convenience function for entityIDExists.
     *
     * @param string $entityID The enityID to search for
     * @return bool True if the given entityID is found. False otherwise.
     */
    public function exists($entityID)
    {
        return $this->entityIDExists($entityID);
    }

    /**
     * isAttributeSet
     *
     * This function checks if the passed-in $attr is set to '1' for
     * the entityID, and returns true if so.
     *
     * @param string $entityID The enityID to search for.
     * @param string $attr The attribute in question.
     * @return bool True if the given attribute is '1' for the entityID.
     *         False otherwise.
     */
    public function isAttributeSet($entityID, $attr)
    {
        return (
            ($this->exists($entityID)) &&
            (isset($this->idparray[$entityID][$attr])) &&
            ($this->idparray[$entityID][$attr] == 1)
        );
    }

    /**
     * isSilver
     *
     * This method searches for the given entityID and checks if the
     *'Silver' entry has been set to '1'.
     *
     * @param string $entityID The enityID to search for
     * @return bool True if the given entityID is certified 'Silver'.
     *         False otherwise.
     */
    public function isSilver($entityID)
    {
        return $this->isAttributeSet($entityID, 'Silver');
    }

    /**
     * isBronze
     *
     * This method searches for the given entityID and checks if the
     *'Bronze' entry has been set to '1'.
     *
     * @param string $entityID The enityID to search for
     * @return bool True if the given entityID is certified 'Bronze'.
     *         False otherwise.
     */
    public function isBronze($entityID)
    {
        return $this->isAttributeSet($entityID, 'Bronze');
    }

    /**
     * isRandS
     *
     * This method searches for the given entityID and checks if the
     *'RandS' entry has been set to '1'.
     *
     * @param string $entityID The enityID to search for
     * @return bool True if the given entityID is listed as 'RandS'
     *         (research-and-scholarship). False otherwise.
     */
    public function isRandS($entityID)
    {
        return $this->isAttributeSet($entityID, 'RandS');
    }

    /**
     * isInCommonRandS
     *
     * This method searches for the given entityID and checks if the
     *'InCommon_RandS' entry has been set to '1'.
     *
     * @param string $entityID The enityID to search for
     * @return bool True if the given entityID is listed as
     *        'InCommon_RandS'. False otherwise.
     */
    public function isInCommonRandS($entityID)
    {
        return $this->isAttributeSet($entityID, 'InCommon_RandS');
    }

    /**
     * isREFEDSRandS
     *
     * This method searches for the given entityID and checks if the
     *'REFEDS_RandS' entry has been set to '1'.
     *
     * @param string $entityID The enityID to search for
     * @return bool True if the given entityID is listed as
     *         'REFEDS_RandS'. False otherwise.
     */
    public function isREFEDSRandS($entityID)
    {
        return $this->isAttributeSet($entityID, 'REFEDS_RandS');
    }

    /**
     * isRegisteredByInCommon
     *
     * This method searches for the given entityID and checks if the
     *'Registered_By_InCommon' entry has been set to '1'.
     *
     * @param string $entityID The enityID to search for
     * @return bool True if the given entityID is listed as
     *         'Registered_By_InCommon'. False otherwise.
     */
    public function isRegisteredByInCommon($entityID)
    {
        return $this->isAttributeSet($entityID, 'Registered_By_InCommon');
    }

    /**
     * isSIRTFI
     *
     * This method searches for the given entityID and checks if the
     *'SIRTFI' entry has been set to '1'.
     *
     * @param string $entityID The enityID to search for
     * @return bool True if the given entityID is listed as
     *         SIRTFI. False otherwise.
     */
    public function isSIRTFI($entityID)
    {
        return $this->isAttributeSet($entityID, 'SIRTFI');
    }

    /**
     * isOauth2
     *
     * This method returns true if the passed-in 'entitID' is one of the
     * supported OAuth2 Identity Providers. Otherwise, false.
     *
     * @param string $entityID The enityID to search for
     * @return bool True if the given entityID is an OAuth2 IdP.
     *         False otherwise.
     */
    public function isOAuth2($entityID)
    {
        $retval = false;
        if (
            ($entityID == Util::getOAuth2Url('Google')) ||
            ($entityID == Util::getOAuth2Url('GitHub')) ||
            ($entityID == Util::getOAuth2Url('ORCID')) ||
            ($entityID == Util::getOAuth2Url('Microsoft'))
        ) {
            $retval = true;
        }
        return $retval;
    }

    /**
     * getSAMLIdPs
     *
     * This method returns a two-dimensional array of SAML-based IdPs.
     * The primary key of the array is the entityID, the secondary key is
     * either 'Organization_Name' (corresponds to OrganizationDisplayName)
     * or 'Display_Name' (corresponds to mdui:DisplayName).
     * If a non-null parameter is passed in it returns a subset of the
     * InCommon IdPs. 2 means list only R&S IdPs, 3 means list only IdPs
     * marked as Registered By InCommon.
     *
     * @param int $filter
     *        null => all SAML-based IdPs
     *        2    => R&S SAML-based IdPs
     *        3    => "Registered By InCommon" IdPs
     * $return array An array of SAML-based IdP Organization Names and Display
     *         Names, possibly filtered R&S / Registered By InCommon.
     */
    public function getSAMLIdPs($filter = null)
    {
        $retarr = array();

        foreach ($this->idparray as $key => $value) {
            if (
                (!is_null($filter)) &&
                (($filter === 2) &&
                 (!$this->isRandS($key))) ||
                (($filter === 3) &&
                 (!$this->isRegisteredByInCommon($key)))
            ) {
                continue;
            }
            if (
                ($this->exists($key)) &&
                (isset($this->idparray[$key]['Organization_Name']))
            ) {
                $retarr[$key]['Organization_Name'] = $this->idparray[$key]['Organization_Name'];
            }
            if (
                ($this->exists($key)) &&
                (isset($this->idparray[$key]['Display_Name']))
            ) {
                $retarr[$key]['Display_Name'] = $this->idparray[$key]['Display_Name'];
            }
        }

        return $retarr;
    }

    /**
     * getRandSIdPs
     *
     * This method returns an array of R&S IdPs where the keys
     * of the array are the entityIDs and the values are the
     * pretty print Organization Names.
     *
     * @return array An array of Research and Scholarship (R&S) IdPs.
     */
    public function getRandSIdPs()
    {
        return $this->getSAMLIdPs(2);
    }

    /**
     * getRegisteredByInCommonIdPs
     *
     * This method returns an array of IdPs that have been tagged as
     * "Registered_By_InCommon". The keys of the array are the entityIDs
     * and the values are the pretty print Organization Names.
     *
     * @return array An array of Research and Scholarship (R&S) IdPs.
     */
    public function getRegisteredByInCommonIdPs()
    {
        return $this->getSAMLIdPs(3);
    }

    /**
     * getShibInfo
     *
     * This function returns an array with two types of Shibboleth
     * information.  The first set of info is specific to the user's
     * current Shibboleth session, such as REMOTE_USER. The second set
     * of info reads info from the passed-in metadata file specific to
     * the IdP, such as the pretty-print name of the IdP.
     *
     * @param string $entityID (Optional) The entityID to search for in
     *        the InCommon metadata. Defaults to the HTTP header
     *        HTTP_SHIB_IDENTITY_PROVIDER.
     * @return array  An array containing the various shibboleth
     *         attributes for the current Shibboleth session. The
     *         keys of the array are 'pretty print' names of the
     *         various attribute value names (such as
     *         'User Identifier' for REMOTE_USER) and the values
     *         of the array are the actual Shibboleth session values.
     */
    public function getShibInfo($entityID = '')
    {
        $shibarray = array();  // Array to be returned

        // Set the blob set of info, namely those shib attributes which
        // were given by the IdP when the user authenticated.
        if (strlen($entityID) == 0) {
            $entityID = Util::getServerVar('HTTP_SHIB_IDENTITY_PROVIDER');
        }
        // CIL-254 - For LIGO backup IdPs, remap entityID to the main IdP
        if (
            preg_match(
                '%(https://login)[^\.]*(.ligo.org/idp/shibboleth)%',
                $entityID,
                $matches
            )
        ) {
            $entityID = $matches[1] . $matches[2];
        }

        // CIL-959 Support for NSF.gov's AD-based IdP. As documented at
        // https://wiki.refeds.org/x/N4BRAg , AD-based IdPs cannot assert
        // MFA in the AuthnContextClassRef SAML attribute. Instead, the
        // IdP must be configured to assert a new 'authnmethodsreferences'
        // attribute. This is consumed by Shib SP as 'amr' (in the
        // attribute-map.xml file). Here, check to see if the 'amr'
        // attribute contains 'https://refeds.org/profile/mfa'. If so,
        // overwrite 'Authn Context' with this value.
        $acr = Util::getServerVar('HTTP_SHIB_AUTHNCONTEXT_CLASS');
        $amr = Util::getServerVar('HTTP_AMR');
        if (preg_match('%https://refeds.org/profile/mfa%', $amr)) {
            $acr = 'https://refeds.org/profile/mfa';
        }

        $shibarray['Identity Provider'] = $entityID;
        $shibarray['User Identifier'] = Util::getServerVar('REMOTE_USER');
        $shibarray['ePPN'] = Util::getServerVar('HTTP_EPPN');
        $shibarray['ePTID'] = Util::getServerVar('HTTP_PERSISTENT_ID');
        $shibarray['First Name'] = Util::getServerVar('HTTP_GIVENNAME');
        $shibarray['Last Name'] = Util::getServerVar('HTTP_SN');
        $shibarray['Display Name'] = Util::getServerVar('HTTP_DISPLAYNAME');
        $shibarray['Email Address'] = Util::getServerVar('HTTP_MAIL');
        $shibarray['Level of Assurance'] = Util::getServerVar('HTTP_ASSURANCE');
        $shibarray['Affiliation'] = Util::getServerVar('HTTP_AFFILIATION');
        $shibarray['OU'] = Util::getServerVar('HTTP_OU');
        $shibarray['Member'] = Util::getServerVar('HTTP_MEMBER');
        $shibarray['Authn Context'] = $acr;
        $shibarray['Entitlement'] = Util::getServerVar('HTTP_ENTITLEMENT');
        $shibarray['iTrustUIN'] = Util::getServerVar('HTTP_ITRUSTUIN');
        $shibarray['Subject ID'] = Util::getServerVar('HTTP_SUBJECT_ID');
        $shibarray['Pairwise ID'] = Util::getServerVar('HTTP_PAIRWISE_ID');

        // Make sure to use only the first of multiple values.
        $attrs = array('ePPN','ePTID','First Name','Last Name',
                       'Display Name','Email Address');
        foreach ($attrs as $attr) {
            if (($pos = strpos($shibarray[$attr], ';')) !== false) {
                $shibarray[$attr] = substr($shibarray[$attr], 0, $pos);
            }
        }

        // Next, read the attributes for the given IdP. This includes
        // values such as the display name for the IdP, the home page
        // of the organization, and contact information.
        $attrarray = array(
            'Organization_Name',
            'Home_Page',
            'Support_Name',
            'Support_Address',
            'Technical_Name',
            'Technical_Address',
            'Administrative_Name',
            'Administrative_Address'
        );

        foreach ($attrarray as $attr) {
            if (
                ($this->exists($entityID)) &&
                (isset($this->idparray[$entityID][$attr]))
            ) {
                $shibarray[preg_replace('/_/', ' ', $attr)] =
                    $this->idparray[$entityID][$attr];
            }
        }

        // Special processing for OAuth 2.0 IdPs
        if ($entityID == Util::getOAuth2Url('Google')) {
            $shibarray['Organization Name'] = 'Google';
            $shibarray['Home Page'] = 'https://myaccount.google.com';
            $shibarray['Support Name'] = 'Google Help';
            $shibarray['Support Address'] = 'help@google.com';
        } elseif ($entityID == Util::getOAuth2Url('GitHub')) {
            $shibarray['Organization Name'] = 'GitHub';
            $shibarray['Home Page'] = 'https://github.com';
            $shibarray['Support Name'] = 'GitHub Help';
            $shibarray['Support Address'] = 'help@github.com';
        } elseif ($entityID == Util::getOAuth2Url('ORCID')) {
            $shibarray['Organization Name'] = 'ORCID';
            $shibarray['Home Page'] = 'https://orcid.org';
            $shibarray['Support Name'] = 'ORCID Help';
            $shibarray['Support Address'] = 'help@orcid.org';
        } elseif ($entityID == Util::getOAuth2Url('Microsoft')) {
            $shibarray['Organization Name'] = 'Microsoft';
            $shibarray['Home Page'] = 'https://account.microsoft.com';
            $shibarray['Support Name'] = 'Microsoft Help';
            $shibarray['Support Address'] = 'help@microsoft.com';
        }

        return $shibarray;
    }

    /**
     * DOM2Array
     *
     * This function sorts the passed-in DOM corresponding to
     * idplist.xml and returns a 2D array where the keys are entityIDs
     * and the values are arrays of attributes for each IdP.
     *
     * @param DOMDocument $dom The DOM containing the list of IdPs to convert
     *        to an array. Returns null on error.
     * @return array An array corresponding to the DOM of the IdPs.
     */
    public function DOM2Array($dom)
    {
        $retarr = null;

        if (!is_null($dom)) {
            foreach ($dom->childNodes as $idps) {
                // Top-level DOM has 'idps' only
                foreach ($idps->childNodes as $idp) {
                    // Loop through each <idp> element
                    $entityID = $idp->attributes->item(0)->value;
                    foreach ($idp->childNodes as $attr) {
                        // Get all sub-attributes of the current <idp>
                        if ($attr->nodeName != '#text') {
                            $retarr[$entityID][$attr->nodeName] = $attr->nodeValue;
                        }
                    }
                }
            }
        }

        return $retarr;
    }

    /**
     * array2DOM
     *
     * This function takes an array of IdPs (such as idparray) and
     * returns a corresponding DOM which can be written to XML.
     *
     * @param array|null $arr An array corresponding to the idplist.
     * @return DOMDocument A DOM for the idplist which can be written to XML.
     */
    public function array2DOM($arr)
    {
        $retdom = null;

        if (!is_null($arr)) {
            $domi = new DOMImplementation();
            $dom = $domi->createDocument(null, 'idps');
            $idps = $dom->documentElement; // Top level <idps> element

            foreach ($arr as $entityID => $attrs) {
                // Create an <idp> element to hold sub elements
                $idp = $dom->createElement('idp');
                $idp->setAttribute('entityID', $entityID);
                $idps->appendChild($idp);
                foreach ($attrs as $attr => $value) {
                    $this->addNode($dom, $idp, $attr, $value);
                }
            }
            $retdom = $this->sortDOM($dom);
        }

        return $retdom;
    }
}
