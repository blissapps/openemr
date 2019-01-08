<?php

/**
 * Created by PhpStorm.
 * User: tjanela
 * Date: 07/01/2019
 * Time: 16:27
 */
error_reporting(E_ALL);

require __DIR__.'/../../../phpfhir/vendor/autoload.php';

use HL7\FHIR\STU3\PHPFHIRResponseParser;
use HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient;
use HL7\FHIR\STU3\FHIRElement\FHIRHumanName;

class FHIRParserTest extends PHPUnit_Framework_TestCase
{
    var $patientRequest = "{\"name\":[{\"use\":\"official\",\"given\":[\"TJ\"]}],\"telecom\":[{\"value\":\"(857) 285-0000\",\"system\":\"phone\"}],\"gender\":\"male\",\"birthDate\":\"1984-10-04\",\"address\":[{\"line\":[\"30 Bowdoin St\",\"10\"],\"city\":\"Boston\",\"state\":\"MA\",\"postalCode\":\"02114\"}],\"contact\":[{\"relationship\":[{\"coding\":[{\"system\":\"http://hl7.org/fhir/v2/0131\",\"code\":\"C\",\"display\":\"Emergency Contact\"}],\"text\":\"Aunt\"}],\"name\":{\"use\":\"official\",\"given\":[\"ndkdk\"]},\"telecom\":[{\"value\":\"(123) 456-7890\",\"system\":\"phone\"}],\"gender\":\"female\"}],\"resourceType\":\"Patient\"}";
    var $parser = null;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->parser = new PHPFHIRResponseParser();
    }

    public function testPatientParser( )
    {
        $decodedRequest = json_decode($this->patientRequest);
        $patient = $this->parser->parse($this->patientRequest);
        $name = $patient->getName()[0]->getGiven()[0];
        $encodedParse = $patient->jsonSerialize();
        $this->assertNotNull($encodedParse);
        $expectedGivenName = $decodedRequest->name[0]->given[0];
        $this->assertEquals($expectedGivenName, $name);

        return null;
    }
}
