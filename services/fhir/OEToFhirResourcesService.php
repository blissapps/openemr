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
use HL7\FHIR\STU3\FHIRDomainResource\FHIROperationOutcome;
use HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient;
use HL7\FHIR\STU3\FHIRDomainResource\FHIRPractitioner;
use HL7\FHIR\STU3\FHIRDomainResource\FHIRCondition;
use HL7\FHIR\STU3\FHIRElement\FHIRAddress;
use HL7\FHIR\STU3\FHIRElement\FHIRAdministrativeGender;
use HL7\FHIR\STU3\FHIRElement\FHIRCodeableConcept;
use HL7\FHIR\STU3\FHIRElement\FHIRHumanName;
use HL7\FHIR\STU3\FHIRElement\FHIRId;
use HL7\FHIR\STU3\FHIRElement\FHIRIssueSeverity;
use HL7\FHIR\STU3\FHIRElement\FHIRIssueType;
use HL7\FHIR\STU3\FHIRElement\FHIRReference;
use HL7\FHIR\STU3\FHIRResource\FHIREncounter\FHIREncounterParticipant;
use HL7\FHIR\STU3\FHIRResource\FHIRBundle;
use HL7\FHIR\STU3\FHIRResource\FHIRBundle\FHIRBundleLink;
use HL7\FHIR\STU3\FHIRResource\FHIROperationOutcome\FHIROperationOutcomeIssue;
use HL7\FHIR\STU3\PHPFHIRResponseParser;

//use HL7\FHIR\STU3\FHIRResource\FHIREncounter\FHIREncounterLocation;
//use HL7\FHIR\STU3\FHIRResource\FHIREncounter\FHIREncounterDiagnosis;
//use HL7\FHIR\STU3\FHIRElement\FHIRPeriod;
//use HL7\FHIR\STU3\FHIRElement\FHIRParticipantRequired;

class OEToFhirResourcesService
{
    public function createBundle($resource = '', $resource_array = [], $encode = true)
    {
        $bundleUrl = \RestConfig::$REST_FULL_URL;
        $nowDate = date("Y-m-d\TH:i:s");
        $meta = array('lastUpdated' => $nowDate);
        $bundleLink = new FHIRBundleLink(array('relation' => 'self', 'url' => $bundleUrl));
        // set bundle type default to collection so may include different
        // resource types. at least I hope thats how it works....
        $bundleInit = array(
            'identifier' => $resource . "bundle",
            'type' => 'collection',
            'total' => count($resource_array),
            'meta' => $meta);
        $bundle = new FHIRBundle($bundleInit);
        $bundle->addLink($bundleLink);
        foreach ($resource_array as $addResource) {
            $bundle->addEntry($addResource);
        }

        if ($encode) {
            return json_encode($bundle);
        }

        return $bundle;
    }

    public function createPatientResource($pid = '', $data = '', $encode = true)
    {
        // @todo add display text after meta
        $nowDate = date("Y-m-d\\TH:i:s");
        $id = new FhirId();
        $id->setValue($pid);
        $name = new FHIRHumanName();
        $address = new FHIRAddress();
        $gender = new FHIRAdministrativeGender();
        $meta = array('versionId' => '1', 'lastUpdated' => $nowDate);
        $initResource = array('id' => $id, 'meta' => $meta);
        $name->setUse('official');
        $name->setFamily($data['lname']);
        $name->given = [];
        $name->given[] = $data['fname'];
        if($data['mname'] != null && $data['mname'] != '') {
            $name->given[] = $data['mname'];
        }
        $address->addLine($data['street']);
        $address->setCity($data['city']);
        $address->setState($data['state']);
        $address->setPostalCode($data['postal_code']);
        $gender->setValue(strtolower($data['sex']));

        $patientResource = new FHIRPatient($initResource);
        $patientResource->setId($id);
        $patientResource->setActive(true);
        $patientResource->setGender($gender);
        $patientResource->addName($name);
        $patientResource->addAddress($address);

        if ($encode) {
            return json_encode($patientResource);
        } else {
            return $patientResource;
        }
    }

    public function createPractitionerResource($id = '', $data = '', $encode = true)
    {
        $resource = new FHIRPractitioner();
        $id = new FhirId();
        $name = new FHIRHumanName();
        $address = new FHIRAddress();
        $id->setValue($id);
        $name->setUse('official');
        $name->setFamily($data['lname']);
        $name->given = [$data['fname'], $data['mname']];
        $address->addLine($data['street']);
        $address->setCity($data['city']);
        $address->setState($data['state']);
        $address->setPostalCode($data['zip']);
        $resource->setId($id);
        $resource->setActive(true);
        $gender = new FHIRAdministrativeGender();
        $gender->setValue('unknown');
        $resource->setGender($gender);
        $resource->addName($name);
        $resource->addAddress($address);

        if ($encode) {
            return json_encode($resource);
        } else {
            return $resource;
        }
    }

    public function createEncounterResource($eid = '', $data = '', $encode = true)
    {
        $pid = $data['pid'];
        $temp = $data['provider_id'];
        //$r = $this->createPractitionerResource($data['provider_id'], $temp);
        $resource = new FHIREncounter();
        $id = new FhirId();
        $id->setValue($eid);
        $resource->setId($id);
        $participant = new FHIREncounterParticipant();
        $prtref = new FHIRReference;
        $temp = 'Practitioner/' . $data['provider_id'];
        $prtref->setReference($temp);
        $participant->setIndividual($prtref);
        $date = date('Y-m-d', strtotime($data['date']));
        $participant->setPeriod(['start' => $date]);

        $resource->addParticipant($participant);
        $reason = new FHIRCodeableConcept();
        $reason->setText($data['reason']);
        $resource->addReason($reason);
        $resource->status = 'finished';
        $resource->setSubject(['reference' => "Patient/$pid"]);

        if ($encode) {
            return json_encode($resource);
        } else {
            return $resource;
        }
    }

    public function createConditionResource($lid = '', $data = '', $encode = true)
    {
        $resource = new FHIRCondition();
        $id = new FhirId();
        $id->setValue($lid);
        $resource->setId($id);

        if ($encode) {
            return json_encode($resource);
        } else {
            return $resource;
        }
    }

    /**
     * This method translates a ValidationResult object from services' validate methods into FHIROperationOutcome
     * Since this typically means a validation error the severity is error and the issue type is invariant:
     * https://www.hl7.org/fhir/valueset-issue-severity.html
     * https://www.hl7.org/fhir/valueset-issue-type.html
     * @param \Particle\Validator\ValidationResult $validationResult
     * @param bool $encode Should the returned object be encoded
     * @returns \HL7\FHIR\STU3\FHIRResource\FHIROperationOutcome
     */

    public function createOperationOutcomeFromValidationResult($validationResult, $encode = true)
    {
        $resource = new FHIROperationOutcome();
        $id = new FHIRId();
        if ($validationResult->isValid()) {
            $id->setValue("allok");
        } else {
            $id->setValue("validationfail");

            foreach ($validationResult->getFailures() as $failure) {
                $issue = new FHIROperationOutcomeIssue();
                $severity = new FHIRIssueSeverity();
                $severity->setValue("error");
                $issue->setSeverity($severity);

                $issueType = new FHIRIssueType();
                $issueType->setValue("invariant");
                $issue->setCode($issueType);

                $details = new FHIRCodeableConcept();
                $details->setText($failure->getReason());
                $issue->setDetails($details);

                $resource->addIssue($issue);
            }
        }
        $resource->setId($id);

        if ($encode) {
            return json_encode($resource);
        } else {
            return $resource;
        }
    }

    /**
     * This method creates a FHIROperationOutcome that is meant to be used when no Exception occurs and no better way
     * of coding an error exists.
     * Since this typically means that an unexplainable error has occurred the severity is error and the issue type is
     * exception:
     * https://www.hl7.org/fhir/valueset-issue-severity.html
     * https://www.hl7.org/fhir/valueset-issue-type.html
     * @param string $errorMessage The message to output
     * @param bool $encode Should the returned object be encoded
     * @returns \HL7\FHIR\STU3\FHIRResource\FHIROperationOutcome | string
     */
    public function createOperationOutcomeFromGeneralError($errorMessage, $encode = true)
    {
        $resource = new FHIROperationOutcome();
        $id = new FHIRId();
        $id->setValue("exception");
        $resource->setId($id);

        $issue = new FHIROperationOutcomeIssue();
        $severity = new FHIRIssueSeverity();
        $severity->setValue("error");
        $issue->setSeverity($severity);

        $issueType = new FHIRIssueType();
        $issueType->setValue("exception");
        $issue->setCode($issueType);

        $details = new FHIRCodeableConcept();
        $details->setText($errorMessage);
        $issue->setDetails($details);

        $resource->addIssue($issue);

        if ($encode) {
            return json_encode($resource);
        } else {
            return $resource;
        }
    }

    /**
     * This method creates a FHIROperationOutcome that is meant to be used when an Exception occurs and has been caught.
     * Since this typically means that an error has occurred the severity is error and the issue type is exception:
     * @param $exception \Throwable
     * @param bool $encode Should the returned object be encoded
     * @return FHIROperationOutcome|string
     */
    public function createOperationOutcomeFromException($exception, $encode = true)
    {
        $resource = new FHIROperationOutcome();
        $id = new FHIRId();
        $id->setValue("exception");
        $resource->setId($id);

        $issue = new FHIROperationOutcomeIssue();
        $severity = new FHIRIssueSeverity();
        $severity->setValue("error");
        $issue->setSeverity($severity);

        $issueType = new FHIRIssueType();
        $issueType->setValue("exception");
        $issue->setCode($issueType);

        $details = new FHIRCodeableConcept();
        $details->setText($exception->getMessage() + "\n" + $exception->getTraceAsString());
        $issue->setDetails($details);

        $resource->addIssue($issue);

        if ($encode) {
            return json_encode($resource);
        } else {
            return $resource;
        }
    }

    public function parseResource($rjson = '', $scheme = 'json')
    {
        $parser = new PHPFHIRResponseParser(false);
        if ($scheme == 'json') {
            $class_object = $parser->parse($rjson);
        } else {
            // @todo xml- not sure yet.
        }
        return $class_object; // feed to resource class or use as is object
    }
}
