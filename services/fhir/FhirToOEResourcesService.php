<?php
/**
 * FHIRResources service class
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2018 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


namespace OpenEMR\Services\FHIR;

// @TODO move to OpenEMR composer auto
require_once __DIR__ . "/../../phpfhir/vendor/autoload.php";

use HL7\FHIR\STU3\FHIRDomainResource\FHIREncounter;
use HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient;
use HL7\FHIR\STU3\FHIRDomainResource\FHIRPractitioner;
use HL7\FHIR\STU3\FHIRElement\FHIRAddress;
use HL7\FHIR\STU3\FHIRElement\FHIRAdministrativeGender;
use HL7\FHIR\STU3\FHIRElement\FHIRCodeableConcept;
use HL7\FHIR\STU3\FHIRElement\FHIRHumanName;
use HL7\FHIR\STU3\FHIRElement\FHIRId;
use HL7\FHIR\STU3\FHIRElement\FHIRReference;
use HL7\FHIR\STU3\FHIRResource\FHIREncounter\FHIREncounterParticipant;
use HL7\FHIR\STU3\FHIRResource\FHIRBundle;
use HL7\FHIR\STU3\FHIRResource\FHIRBundle\FHIRBundleLink;
use HL7\FHIR\STU3\PHPFHIRResponseParser;

//use HL7\FHIR\STU3\FHIRResource\FHIREncounter\FHIREncounterLocation;
//use HL7\FHIR\STU3\FHIRResource\FHIREncounter\FHIREncounterDiagnosis;
//use HL7\FHIR\STU3\FHIRElement\FHIRPeriod;
//use HL7\FHIR\STU3\FHIRElement\FHIRParticipantRequired;

class FhirToOEResourcesService
{
    /**
     * @param $fhirPatientResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient
     * @return array
     */
    public function createOePatientResource($fhirPatientResource)
    {
        $data = array();

        $fhirName = $fhirPatientResource->getName()[0];
        $fhirAddress = $fhirPatientResource->getAddress()[0];
        $fhirContact = $fhirPatientResource->getTelecom()[0];

        $data["title"] = $this->firstItemValue($fhirName->getPrefix());
        $data["fname"] = $this->firstItemValue($fhirName->getGiven());
        $data["mname"] = "";
        $data["lname"] = $this->itemValue($fhirName->getFamily(), "");
        $data["street"] = $this->firstItemValue($fhirAddress->getLine());
        $data["postal_code"] = $this->itemValue($fhirAddress->getPostalCode(), "");
        $data["city"] = $this->itemValue($fhirAddress->getCity(), "");
        $data["state"] = $this->itemValue($fhirAddress->getState(), "");
        $data["country_code"] = $fhirAddress->getCountry() ?? "";
        $data["phone_contact"] = $this->itemValue($fhirContact->getValue(), "");
        $data["dob"] = $this->itemValue($fhirPatientResource->getBirthDate(), "");
        $data["sex"] = $this->itemValue($fhirPatientResource->getGender(), "");
        //TODO: These fields should also be mapped, possibly extensions of PatientResource under FHIR
        $data["race"] = "";
        $data["ethnicity"] = "";

        return $data;
    }

    public function firstItemValue($array, $default = ""){
        return empty($array) ? $default : $array[0]->getValue();
    }

    public function itemValue($fhirString, $default = ""){
        return $fhirString === null ? $default : $fhirString->getValue();
    }
}
