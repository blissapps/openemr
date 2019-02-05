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
use HL7\FHIR\STU3\FHIRDomainResource\FHIRCondition;
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

    /**
     * @param $fhirConditionResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRCondition
     * @return array
     */
    public function createOeListResourceFromFhirCondition($fhirConditionResource) {
        $data = array();

        $fhirSubjectId = $fhirConditionResource->getSubject()->getReference()->getValue();
        $pid = str_ireplace("Patient/","", $fhirSubjectId);
        $title = $fhirConditionResource->getCode()->getText()->getValue();
        $date = $fhirConditionResource->getOnsetDateTime()->getValue();
        $diagnosis = $title;

        error_log("SubjectId: ". $fhirSubjectId, 0);
        error_log("Pid: ". $pid, 0);
        error_log("Title: ". $title, 0);
        error_log("Date: ". $date, 0);
        error_log("Diagnosis: ". $diagnosis, 0);

        $data['pid'] = $pid;
        $data['type'] = "medical_problem";
        $data["title"] = $title;
        $data["begdate"] = $date;
        $data["enddate"] = $date;
        $data["diagnosis"] = $diagnosis;

        return $data;
    }

    /**
     * @param $fhirProcedureResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRProcedure
     * @return array
     */
    public function createOeListResourceFromFhirProcedure($fhirProcedureResource) {
        $data = array();

        $fhirSubjectId = $fhirProcedureResource->getSubject()->getReference()->getValue();
        $pid = str_ireplace("Patient/","", $fhirSubjectId);
        $title = $fhirProcedureResource->getCode()->getText()->getValue();
        $date = $fhirProcedureResource->getPerformedDateTime()->getValue();
        $procedure = $title;

        error_log("SubjectId: ". $fhirSubjectId, 0);
        error_log("Pid: ". $pid, 0);
        error_log("Title: ". $title, 0);
        error_log("Date: ". $date, 0);
        error_log("Procedure: ". $procedure, 0);

        $data['pid'] = $pid;
        $data['type'] = "surgery";
        $data["title"] = $title;
        $data["begdate"] = $date;
        $data["enddate"] = $date;
        $data["diagnosis"] = $procedure;

        return $data;
    }

    /**
     * @param $fhirMedicationAdministration \HL7\FHIR\STU3\FHIRDomainResource\FHIRMedicationAdministration
     * @return array
     */
    public function createOeListResourceFromFhirMedicationAdministration($fhirMedicationAdministration) {
        $data = array();

        $fhirSubjectId = $fhirMedicationAdministration->getSubject()->getReference()->getValue();
        $pid = str_ireplace("Patient/","", $fhirSubjectId);
        $title = $fhirMedicationAdministration->getMedicationCodeableConcept()->getText()->getValue();
        $date = $fhirMedicationAdministration->getEffectiveDateTime()->getValue();
        $procedure = $title;

        error_log("SubjectId: ". $fhirSubjectId, 0);
        error_log("Pid: ". $pid, 0);
        error_log("Title: ". $title, 0);
        error_log("Date: ". $date, 0);
        error_log("Medication: ". $procedure, 0);

        $data['pid'] = $pid;
        $data['type'] = "medication";
        $data["title"] = $title;
        $data["begdate"] = $date;
        $data["enddate"] = $date;
        $data["diagnosis"] = $procedure;

        return $data;
    }

    /**
     * @param $fhirAllergyIntolerance \HL7\FHIR\STU3\FHIRDomainResource\FHIRAllergyIntolerance
     * @return array
     */
    public function createOeListResourceFromFhirAllergyIntolerance($fhirAllergyIntolerance) {
        $data = array();

        $fhirSubjectId = $fhirAllergyIntolerance->getSubject()->getReference()->getValue();
        $pid = str_ireplace("Patient/","", $fhirSubjectId);
        $title = $fhirAllergyIntolerance->getCode()->getText()->getValue();
        $date = $fhirAllergyIntolerance->getOnsetDateTime()->getValue();
        $procedure = $title;

        error_log("SubjectId: ". $fhirSubjectId, 0);
        error_log("Pid: ". $pid, 0);
        error_log("Title: ". $title, 0);
        error_log("Date: ". $date, 0);
        error_log("Allergy: ". $procedure, 0);

        $data['pid'] = $pid;
        $data['type'] = "allergy";
        $data["title"] = $title;
        $data["begdate"] = $date;
        $data["enddate"] = $date;
        $data["diagnosis"] = $procedure;

        return $data;
    }

    public function firstItemValue($array, $default = ""){
        return empty($array) ? $default : $array[0]->getValue();
    }

    public function itemValue($fhirString, $default = ""){
        return $fhirString === null ? $default : $fhirString->getValue();
    }
}
