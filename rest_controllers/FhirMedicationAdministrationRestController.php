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

use OpenEMR\Services\PatientService;
use OpenEMR\Services\ListService;
use OpenEMR\Services\FHIR\OEToFhirResourcesService;
use OpenEMR\Services\FHIR\FhirToOEResourcesService;
use HL7\FHIR\STU3\FHIRResource\FHIRBundle\FHIRBundleEntry;

class FhirMedicationAdministrationRestController
{
    private $listService;
    private $patientService;
    private $oeToFhirService;
    private $fhirToOeService;

    public function __construct()
    {
        $this->patientService = new PatientService();
        $this->listService = new ListService();
        $this->oeToFhirService = new OEToFhirResourcesService();
        $this->fhirToOeService = new FhirToOEResourcesService();
    }

    /**
     * @param $fhirMedicationAdministrationResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRMedicationAdministration The medicationAdministration to create
     * @return \HL7\FHIR\STU3\FHIRDomainResource\FHIROperationOutcome | \HL7\FHIR\STU3\FHIRDomainResource\FHIRMedicationAdministration
     */
    public function post($fhirMedicationAdministrationResource)
    {
        try {
            $oeList = $this->fhirToOeService->createOeListResourceFromFhirMedicationAdministration($fhirMedicationAdministrationResource);
            $validationResult = $this->listService->validate($oeList);

            if ($validationResult->isNotValid()) {
                $result = $this->oeToFhirService->createOperationOutcomeFromValidationResult($validationResult, false);
                return RestControllerHelper::responseHandler($result, null, 400);
            }

            $existingOeList = $this->listService->getByPatientIdAndAndListTypeAndTitleAndBegDateAndEndDate($oeList["pid"],
                $oeList["type"],
                $oeList["title"],
                $oeList["begdate"],
                $oeList["enddate"]);

            if (count($existingOeList) > 0) {
                return $this->getOne($existingOeList[0]["id"]);
            }

            $lid = $this->listService->insert($oeList);

            if ($lid === false) {
                $result = $this->oeToFhirService->createOperationOutcomeFromGeneralError("Couldn't insert medicationAdministration.", false);
                return RestControllerHelper::responseHandler($result, null, 500);
            }

            return $this->getOne($lid);
        } catch (Throwable $exception) {
            $result = $this->oeToFhirService->createOperationOutcomeFromException($exception, false);
            return RestControllerHelper::responseHandler($result, null, 500);
        }
    }

    /**
     * @param $fhirMedicationAdministrationResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRMedicationAdministration The medicationAdministration to update
     * @return \HL7\FHIR\STU3\FHIRDomainResource\FHIROperationOutcome | \HL7\FHIR\STU3\FHIRDomainResource\FHIRMedicationAdministration
     */
    public function put($fhirMedicationAdministrationResource)
    {
        try {
            $oeList = $this->fhirToOeService->createOeListResourceFromFhirMedicationAdministration($fhirMedicationAdministrationResource);
            $validationResult = $this->listService->validate($oeList);

            if ($validationResult->isNotValid()) {
                $result = $this->oeToFhirService->createOperationOutcomeFromValidationResult($validationResult, false);
                return RestControllerHelper::responseHandler($result, null, 400);
            }

            $lid = $fhirMedicationAdministrationResource->getId()->getValue();

            $result = $this->listService->update($oeList);

            if ($result === false) {
                $result = $this->oeToFhirService->createOperationOutcomeFromGeneralError("Couldn't update medicationAdministration.", false);
                return RestControllerHelper::responseHandler($result, null, 500);
            }

            return $this->getOne($lid);
        } catch (Throwable $exception) {
            $result = $this->oeToFhirService->createOperationOutcomeFromException($exception, false);
            return RestControllerHelper::responseHandler($result, null, 500);
        }
    }

    public function getOne($lid)
    {
        $oeList = $this->listService->getOneByListTypeAndListId($lid, "medication");
        $medicationAdministrationResource = $this->oeToFhirService->createMedicationAdministrationResource($lid, $oeList, false);

        return RestControllerHelper::responseHandler($medicationAdministrationResource, null, 200);
    }

    public function getAll($search)
    {
        $resourceURL = \RestConfig::$REST_FULL_URL;
        if (strpos($resourceURL, '?') > 0) {
            $resourceURL = strstr($resourceURL, '?', true);
        }

        $pid = $search['pid'];

        $searchResult = $this->listService->getAll($pid, "medication");
        if ($searchResult === false) {
            http_response_code(404);
            exit;
        }
        $entries = array();
        foreach ($searchResult as $oeList) {
            $entryResource = $this->oeToFhirService->createMedicationAdministrationResource($oeList['id'], $oeList, false);
            $entry = array(
                'fullUrl' => $resourceURL . "/" . $oeList['id'],
                'resource' => $entryResource
            );
            $entries[] = new FHIRBundleEntry($entry);
        }
        $searchResult = $this->oeToFhirService->createBundle('MedicationAdministration', $entries, false);
        return RestControllerHelper::responseHandler($searchResult, null, 200);
    }
}
