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

use HL7\FHIR\STU3\FHIRDomainResource\FHIRCondition;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\ListService;
use OpenEMR\Services\FHIR\OEToFhirResourcesService;
use OpenEMR\Services\FHIR\FhirToOEResourcesService;
use OpenEMR\RestControllers\RestControllerHelper;
use HL7\FHIR\STU3\FHIRResource\FHIRBundle\FHIRBundleEntry;

use Particle\Validator\ValidationResult;

class FhirConditionRestController
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
     * @param $fhirPatientResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRCondition The condition to create
     * @return \HL7\FHIR\STU3\FHIRDomainResource\FHIROperationOutcome | \HL7\FHIR\STU3\FHIRDomainResource\FHIRCondition
     */
    public function post($fhirConditionResource)
    {
        try {
            $oeList = $this->fhirToOeService->createOeListResourceFromFhirCondition($fhirConditionResource);
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
                $result = $this->oeToFhirService->createOperationOutcomeFromGeneralError("Couldn't insert condition.", false);
                return RestControllerHelper::responseHandler($result, null, 500);
            }

            return $this->getOne($lid);
        } catch (Throwable $exception) {
            $result = $this->oeToFhirService->createOperationOutcomeFromException($exception, false);
            return RestControllerHelper::responseHandler($result, null, 500);
        }
    }

    /**
     * @param $fhirPatientResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRCondition The condition to update
     * @return \HL7\FHIR\STU3\FHIRDomainResource\FHIROperationOutcome | \HL7\FHIR\STU3\FHIRDomainResource\FHIRCondition
     */
    public function put($fhirConditionResource)
    {
        try {
            $oeList = $this->fhirToOeService->createOeListResourceFromFhirCondition($fhirConditionResource);
            $validationResult = $this->listService->validate($oeList);

            if ($validationResult->isNotValid()) {
                $result = $this->oeToFhirService->createOperationOutcomeFromValidationResult($validationResult, false);
                return RestControllerHelper::responseHandler($result, null, 400);
            }

            $lid = $fhirConditionResource->getId()->getValue();

            $result = $this->listService->update($lid, $oeList);

            if ($result === false) {
                $result = $this->oeToFhirService->createOperationOutcomeFromGeneralError("Couldn't update condition.", false);
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
        $oeList = $this->listService->getOneByListTypeAndListId($lid, "medical_problem");
        $conditionResource = $this->oeToFhirService->createConditionResource($lid, $oeList, false);

        return RestControllerHelper::responseHandler($conditionResource, null, 200);
    }

    public function getAll($search)
    {
        $resourceURL = \RestConfig::$REST_FULL_URL;
        if (strpos($resourceURL, '?') > 0) {
            $resourceURL = strstr($resourceURL, '?', true);
        }

        $pid = $search['pid'];

        $searchResult = $this->listService->getAll($pid, "medical_problem");
        if ($searchResult === false) {
            http_response_code(404);
            exit;
        }
        $entries = array();
        foreach ($searchResult as $oeList) {
            $entryResource = $this->oeToFhirService->createConditionResource($oeList['id'], $oeList, false);
            $entry = array(
                'fullUrl' => $resourceURL . "/" . $oeList['id'],
                'resource' => $entryResource
            );
            $entries[] = new FHIRBundleEntry($entry);
        }
        $searchResult = $this->oeToFhirService->createBundle('Condition', $entries, false);
        return RestControllerHelper::responseHandler($searchResult, null, 200);
    }
}
