<?php
/**
 * FhirPatientRestController
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2018 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\RestControllers;

use HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\FHIR\OEToFhirResourcesService;
use OpenEMR\Services\FHIR\FhirToOEResourcesService;
use OpenEMR\RestControllers\RestControllerHelper;
use HL7\FHIR\STU3\FHIRResource\FHIRBundle\FHIRBundleEntry;

use Particle\Validator\ValidationResult;

class FhirPatientRestController
{
    private $patientService;
    private $oeToFhirService;
    private $fhirToOeService;

    public function __construct($pid)
    {
        $this->patientService = new PatientService();
        $this->patientService->setPid($pid);
        $this->oeToFhirService = new OEToFhirResourcesService();
        $this->fhirToOeService = new FhirToOEResourcesService();
    }

    /**
     * @param $fhirPatientResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient The patient to create
     * @return \HL7\FHIR\STU3\FHIRDomainResource\FHIROperationOutcome | \HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient
     */
    public function post($fhirPatientResource)
    {
        try {
            $oePatient = $this->fhirToOeService->createOePatientResource($fhirPatientResource);
            $validationResult = $this->patientService->validate($oePatient);

            if ($validationResult->isNotValid()) {
                $result = $this->oeToFhirService->createOperationOutcomeFromValidationResult($validationResult, false);
                return RestControllerHelper::responseHandler($result, null, 400);
            }

            $pid = $this->patientService->insert($oePatient);


            if ($pid === false) {
                $result = $this->oeToFhirService->createOperationOutcomeFromGeneralError("Couldn't insert patient.", false);
                return RestControllerHelper::responseHandler($result, null, 500);
            }

            $this->patientService->setPid($pid);

            return $this->getOne();
        } catch (Throwable $exception) {
            $result = $this->oeToFhirService->createOperationOutcomeFromException($exception, false);
            return RestControllerHelper::responseHandler($result, null, 500);
        }
    }

    /**
     * @param $fhirPatientResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient The patient to update
     * @return \HL7\FHIR\STU3\FHIRDomainResource\FHIROperationOutcome | \HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient
     */
    public function put($fhirPatientResource)
    {
        try {
            $oePatient = $this->fhirToOeService->createOePatientResource($fhirPatientResource);
            $validationResult = $this->patientService->validate($oePatient);

            if ($validationResult->isNotValid()) {
                $result = $this->oeToFhirService->createOperationOutcomeFromValidationResult($validationResult, false);
                return RestControllerHelper::responseHandler($result, null, 400);
            }

            $pid = $fhirPatientResource->getId()->getValue();

            $result = $this->patientService->update($pid, $oePatient);

            if ($result === false) {
                $result = $this->oeToFhirService->createOperationOutcomeFromGeneralError("Couldn't update patient.", false);
                return RestControllerHelper::responseHandler($result, null, 500);
            }

            $this->patientService->setPid($pid);

            return $this->getOne();
        } catch (Throwable $exception) {
            $result = $this->oeToFhirService->createOperationOutcomeFromException($exception, false);
            return RestControllerHelper::responseHandler($result, null, 500);
        }
    }

    public function getOne()
    {
        $oept = $this->patientService->getOne();
        $pid = $this->patientService->getPid();
        $patientResource = $this->oeToFhirService->createPatientResource($pid, $oept, false);

        return RestControllerHelper::responseHandler($patientResource, null, 200);
    }

    public function getAll($search)
    {
        $resourceURL = \RestConfig::$REST_FULL_URL;
        if (strpos($resourceURL, '?') > 0) {
            $resourceURL = strstr($resourceURL, '?', true);
        }

        $searchParam = array(
            'name' => $search['name'],
            'dob' => $search['birthdate']);

        $searchResult = $this->patientService->getAll($searchParam);
        if ($searchResult === false) {
            http_response_code(404);
            exit;
        }
        $entries = array();
        foreach ($searchResult as $oept) {
            $entryResource = $this->oeToFhirService->createPatientResource($oept['pid'], $oept, false);
            $entry = array(
                'fullUrl' => $resourceURL . "/" . $oept['pid'],
                'resource' => $entryResource
            );
            $entries[] = new FHIRBundleEntry($entry);
        }
        $searchResult = $this->oeToFhirService->createBundle('Patient', $entries, false);
        return RestControllerHelper::responseHandler($searchResult, null, 200);
    }
}
