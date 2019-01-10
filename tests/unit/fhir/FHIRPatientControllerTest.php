<?php

error_reporting(E_ALL ^ E_NOTICE);

$GLOBALS['OE_SITE_DIR']=__DIR__."/../../../sites/default";
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__.'/../../../');
require_once("library/sql.inc");

$site_id = "default";
require_once 'phpfhir/vendor/autoload.php';
require_once "sites/$site_id/sqlconf.php";
require_once '_rest_config.php';
use HL7\FHIR\STU3\PHPFHIRResponseParser;
use HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient;
use HL7\FHIR\STU3\FHIRElement\FHIRHumanName;
use HL7\FHIR\STU3\FHIRElement\FHIRId;
use OpenEMR\RestControllers\FhirPatientRestController;
use OpenEMR\Services\PatientService;

/**
 * Created by PhpStorm.
 * User: tjanela
 * Date: 04/01/2019
 * Time: 13:17
 */
class FHIRPatientControllerTest extends PHPUnit_Framework_TestCase
{

    var $parser = null;
    function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->parser = new PHPFHIRResponseParser();
    }

    function testPostSuccess() {
        $cut = new FhirPatientRestController(null);
        $patientRequest = $this->loadPatientFromResources("1");
        $fhirPatient = $this->parser->parse($patientRequest);
        $result = $cut->post($fhirPatient);
    }

    function testPutSuccess() {
        $cut = new FhirPatientRestController(null);
        $patientService = new PatientService();

        $results = $patientService->getAll([]);
        $patient = $results[0];

        $pid = $patient["pid"];

        $id = new FHIRId();
        $id->setValue($pid);

        $patientRequest = $this->loadPatientFromResources("3");
        $fhirPatient = $this->parser->parse($patientRequest);
        $fhirPatient->setId($id);

        $result = $cut->put($fhirPatient);
    }

    function testPostNoLastName() {
        $cut = new FhirPatientRestController(null);
        $patientRequest = $this->loadPatientFromResources("2");
        $fhirPatient = $this->parser->parse($patientRequest);
        $result = $cut->post($fhirPatient);
    }

    function testGetAll(){
        $cut = new FhirPatientRestController(null);
        $result = $cut->getAll(array("name"=>"", "birthdate"=>""));
    }

    function loadPatientFromResources($patient){
        $result = file_get_contents(__DIR__."/resources/patient_".$patient.".json");
        //$result = json_decode($result, false);
        return $result;
    }
}
