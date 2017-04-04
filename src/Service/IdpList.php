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
 * This class manages the list of InCommon IdPs and their
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
 * There are several constants in the class that you
 * should set for your particular set up:
 *
 * DEFAULTIDPFILENAME - this is the full path and name
 *     of the processed IdP list file used by the CILogon
 *     Service. It should have read/write permissions for
 *     apache (via either owner or group).
 *
 * DEFAULTINCOMMONFILENAME - this is the full path and
 *     name of the InCommon metadata file used by the
 *     CILogon Service. It should have read permissions
 *     for apache (via either owner or group).
 *
 * TESTIDPFILENAME - this is the full path and name
 *     of an XML-formatted list of test IdPs. If found,
 *     these test IdPs will be added to the full IdP
 *     list when create()/write() is called. This file
 *     should have read/write permissions for apache.
 *
 * Note that this class previously defaulted to writing the idplist
 * as an XMl file and then reading that XML file back in. When all
 * InCommon and eduGAIN IdPs were 'whitelisted', the xpath queries
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

    // Set the constants to correspond to your particular set up.
    /**
     * @var string DEFAULTIDPFILENAME The full path/filename of the
     *      generated list of IdPs in JSON format
     */
    const DEFAULTIDPFILENAME = '/var/www/html/include/idplist.json';

    /**
     * @var string DEFAULTINCOMMONFILENAME The full path/filename of the
     *      InCommon metadata XML file.
     */
    const DEFAULTINCOMMONFILENAME =
        '/var/cache/shibboleth/InCommon-metadata.xml';

    /**
     * @var string TESTIDPFILENAME The fill path/filename of the XML file
     *      containing test IdPs.
     */
    const TESTIDPFILENAME = '/var/www/html/include/testidplist.xml';

    /**
     * @var DOMDocument $idpdom A DOMDocument which holds the list of IdP
     *      entityIDs and their corresponding attributes.
     */
    protected $idpdom = null;

    /**
     * @var array $idparray An array version of $idpdom. It is used
     * primarily since searching an array is faster than xpath query.
     */
    protected $idparray = null;

    /**
     * @var string $idpfilename The name of the IdP list in JSON format.
     *      Defaults to DEFAULTIDPFILENAME.
     */
    protected $idpfilename;

    /**
     * @var string $incommonfilename The name of the InCommon metadata XML
     *      file. Defaults to DEFAULTINCOMMONFILENAME.
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
     *        read/write. Defaults to DEFAULTIDPFILENAME.
     * @param string $incommonfilename (Optional) The name of the InCommon
     *        metadata file to read. Defaults to DEFAULTINCOMMONFILENAME.
     * @param bool $createfile (Optional) Create idplist file if it doesn't
     *         exist? Defaults to true.
     * @param string $filetype (Optional) The type of file to read/write,
     *        one of 'xml' or 'json'. Defaults to 'json'.
     * @return IdpList A new IdpList object.
     */
    public function __construct(
        $idpfilename = self::DEFAULTIDPFILENAME,
        $incommonfilename = self::DEFAULTINCOMMONFILENAME,
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
        if ((is_readable($filename)) &&
            (($dom = DOMDocument::load($filename, LIBXML_NOBLANKS)) !== false)) {
            $this->idpdom = $dom;
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
        if ((is_readable($filename)) &&
            (($contents = file_get_contents($filename)) !== false) &&
            (($tempjson = json_decode($contents, true)) !== null)) {
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
            $tmpfname = tempnam('/tmp', 'IDP');
            if (($this->idpdom->save($tmpfname) > 0) &&
                (@rename($tmpfname, $filename))) {
                chmod($filename, 0664);
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
            $tmpfname = tempnam('/tmp', 'JSON');
            $json = json_encode($this->idparray, JSON_FORCE_OBJECT);
            if (((file_put_contents($tmpfname, $json)) !== false) &&
                (@rename($tmpfname, $filename))) {
                chmod($filename, 0664);
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
     * @param DOMDocument $dom A DOMDocument object
     * @param DOMElement $idpnode A pointer to a parent <idp> DOMElement
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
     * DOMDocument <idp> nodes by Organization_Name. It uses an XSL
     * transformation to do the work. A new DOMDocument is created
     * and returned.
     *
     * @param DOMDocument $dom A DOMDocument to be sorted by Organization_Name
     * @return DOMDocument A new DOMDocument with the <idp> elements sorted by
     *         Organization_Name.
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
                  <xsl:sort select="translate(Organization_Name,
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
                $dom = DOMImplementation::createDocument(null, 'idps');
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

                    // Create an <idp> element to hold sub elements
                    $idp = $dom->createElement('idp');
                    $idp->setAttribute('entityID', $entityID);
                    $idps->appendChild($idp);

                    // Search for the desired <idp> attribute sub-blocks

                    // CIL-367 Prefer <OrganizationDisplayName>
                    $xp = $idx[0]->xpath(
                        "Organization/OrganizationDisplayName[@xml:lang='en']"
                    );
                    if (($xp !== false) && (count($xp) > 0)) {
                        $this->addNode(
                            $dom,
                            $idp,
                            'Organization_Name',
                            (string)$xp[0]
                        );
                    } else {
                        // If we didn't find the OrganizationDisplayName,
                        // look for mdui:DisplayName instead
                        $xp = $sxe->xpath(
                            "IDPSSODescriptor/Extensions/mdui:UIInfo/mdui:DisplayName[@xml:lang='en']"
                        );
                        if (($xp !== false) && (count($xp) > 0)) {
                            $this->addNode(
                                $dom,
                                $idp,
                                'Organization_Name',
                                (string)$xp[0]
                            );
                        }
                    }

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
                    $sirtfiadded = false;
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
                                $sirtfiadded = true;
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
                            if ((!$addedrands) &&
                                ($incommonrands || $refedsrands)) {
                                $addedrands = true;
                                $this->addNode($dom, $idp, 'RandS', '1');
                            }
                        }
                    }

                    // Add a <Whitelisted> block for all IdPs
                    $this->addNode($dom, $idp, 'Whitelisted', '1');
                }

                // Read in any test IdPs and add them to the list
                if ((is_readable(static::TESTIDPFILENAME)) &&
                    (($dom2 = DOMDocument::load(
                        static::TESTIDPFILENAME
                    )) !== false)) {
                    $idpnodes = $dom2->getElementsByTagName('idp');
                    foreach ($idpnodes as $idpnode) {
                        $node = $dom->importNode($idpnode, true);
                        $idps->appendChild($node);

                        // Add the testidplist nodes to the $idparray
                        $entityID = $node->attributes->item(0)->value;
                        foreach ($node->childNodes as $child) {
                            if ($child->nodeName != '#text') {
                                $this->idparray[$entityID][$child->nodeName] =
                                    $child->nodeValue;
                            }
                        }
                    }
                }

                // Sort the DOMDocument and idparray by Organization_Name
                $this->idpdom = $this->sortDOM($dom);
                uasort($this->idparray, function ($a, $b) {
                    return strcasecmp(
                        $a['Organization_Name'],
                        $b['Organization_Name']
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
        if (isset($this->idparray[$entityID]['Organization_Name'])) {
            $retval = $this->idparray[$entityID]['Organization_Name'];
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
        return (isset($this->idparray[$entityID][$attr]) &&
                     ($this->idparray[$entityID][$attr] == 1));
    }

    /**
     * isWhitelisted
     *
     * This method searches for the given entityID and checks if the
     *'Whitelisted' entry has been set to '1'.
     *
     * @param string $entityID The enityID to search for
     * @return bool True if the given entityID is marked 'Whitelisted'.
     *         False otherwise.
     */
    public function isWhitelisted($entityID)
    {
        return $this->isAttributeSet($entityID, 'Whitelisted');
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
     * getInCommonIdPs
     *
     * This method returns an array of InCommon IdPs where the keys
     * of the array are the entityIDs and the values are the pretty
     * print Organization Names. If a non-null parameter is passed in
     * it returns a subset of the InCommon IdPs. 0 means list only
     * non-whitelisted IdPs, 1 means list only whitelisted IdPs,
     * 2 means list only R&S IdPs.
     *
     * @param int $filter
     *        null => all InCommonIdPs
     *        0    => non-whitelisted InCommon IdPs
     *        1    => whitelisted InCommon IdPs
     *        2    => R&S InCommon IdPs
     * $return array An array of InCommon IdPs, possibly filtered by
     *         whitelisted / non-whitelisted / R&S.
     */
    public function getInCommonIdPs($filter = null)
    {
        $retarr = array();

        foreach ($this->idparray as $key => $value) {
            if ((!is_null($filter)) &&
                (($filter === 0) &&
                 ($this->isWhitelisted($key))) ||
                (($filter === 1) &&
                 (!$this->isWhitelisted($key))) ||
                (($filter === 2) &&
                 (!$this->isRandS($key)))) {
                continue;
            }
            $retarr[$key] = $this->idparray[$key]['Organization_Name'];
        }

        return $retarr;
    }

    /**
     * getNonWhitelistedIdPs
     *
     * This method returns an array of non-whitelisted IdPs where the
     * keys of the array are the entityIDs and the values are the
     * pretty print Organization Names.
     *
     * @return array An array of non-whitelisted IdPs.
     */
    public function getNonWhitelistedIdPs()
    {
        return $this->getInCommonIdPs(0);
    }

    /**
     * getWhitelistedIdPs
     *
     * This method returns an array of whitelisted IdPs where the keys
     * of the array are the entityIDs and the values are the
     * pretty print Organization Names.
     *
     * @return array An array of whitelisted IdPs.
     */
    public function getWhitelistedIdPs()
    {
        return $this->getInCommonIdPs(1);
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
        return $this->getInCommonIdPs(2);
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
        if (preg_match(
            '%(https://login)[^\.]*(.ligo.org/idp/shibboleth)%',
            $entityID,
            $matches
        )) {
            $entityID = $matches[1] . $matches[2];
        }
        $shibarray['Identity Provider'] = $entityID;
        $shibarray['User Identifier'] = Util::getServerVar('HTTP_REMOTE_USER');
        $shibarray['ePPN'] = Util::getServerVar('HTTP_EPPN');
        $shibarray['ePTID'] = Util::getServerVar('HTTP_PERSISTENT_ID');
        $shibarray['First Name'] = Util::getServerVar('HTTP_GIVENNAME');
        $shibarray['Last Name'] = Util::getServerVar('HTTP_SN');
        $shibarray['Display Name'] = Util::getServerVar('HTTP_DISPLAYNAME');
        $shibarray['Email Address'] = Util::getServerVar('HTTP_MAIL');
        $shibarray['Level of Assurance'] = Util::getServerVar('HTTP_ASSURANCE');
        $shibarray['Affiliation'] = Util::getServerVar('HTTP_AFFILIATION');
        $shibarray['OU'] = Util::getServerVar('HTTP_OU');
        $shibarray['Authn Context'] = Util::getServerVar('HTTP_SHIB_AUTHNCONTEXT_CLASS');

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
            if (isset($this->idparray[$entityID][$attr])) {
                $shibarray[preg_replace('/_/', ' ', $attr)] =
                    $this->idparray[$entityID][$attr];
            }
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
     * @param DOMDocument The DOM containing the list of IdPs to convert to
     *        an array. Returns null on error.
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
     * @param array $arr An array corresponding to the idplist.
     * @return DOMDocument A DOM for the idplist which can be written to XML.
     */
    public function array2DOM($arr)
    {
        $retdom = null;

        if (!is_null($arr)) {
            $dom = DOMImplementation::createDocument(null, 'idps');
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