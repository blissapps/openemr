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

class FhirAllergyIntoleranceRestController
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
     * @param $fhirAllergyIntoleranceResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRAllergyIntolerance The allergyIntolerance to create
     * @return \HL7\FHIR\STU3\FHIRDomainResource\FHIROperationOutcome | \HL7\FHIR\STU3\FHIRDomainResource\FHIRAllergyIntolerance
     */
    public function post($fhirAllergyIntoleranceResource)
    {
        try {
            $oeList = $this->fhirToOeService->createOeListResourceFromFhirAllergyIntolerance($fhirAllergyIntoleranceResource);
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
                $result = $this->oeToFhirService->createOperationOutcomeFromGeneralError("Couldn't insert allergyIntolerance.", false);
                return RestControllerHelper::responseHandler($result, null, 500);
            }

            return $this->getOne($lid);
        } catch (Throwable $exception) {
            $result = $this->oeToFhirService->createOperationOutcomeFromException($exception, false);
            return RestControllerHelper::responseHandler($result, null, 500);
        }
    }

    /**
     * @param $fhirAllergyIntoleranceResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRAllergyIntolerance The allergyIntolerance to update
     * @return \HL7\FHIR\STU3\FHIRDomainResource\FHIROperationOutcome | \HL7\FHIR\STU3\FHIRDomainResource\FHIRAllergyIntolerance
     */
    public function put($fhirAllergyIntoleranceResource)
    {
        try {
            $oeList = $this->fhirToOeService->createOeListResourceFromFhirAllergyIntolerance($fhirAllergyIntoleranceResource);
            $validationResult = $this->listService->validate($oeList);

            if ($validationResult->isNotValid()) {
                $result = $this->oeToFhirService->createOperationOutcomeFromValidationResult($validationResult, false);
                return RestControllerHelper::responseHandler($result, null, 400);
            }

            $lid = $fhirAllergyIntoleranceResource->getId()->getValue();

            $result = $this->listService->update($oeList);

            if ($result === false) {
                $result = $this->oeToFhirService->createOperationOutcomeFromGeneralError("Couldn't update allergyIntolerance.", false);
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
        $oeList = $this->listService->getOneByListTypeAndListId($lid, "allergy");
        $allergyIntoleranceResource = $this->oeToFhirService->createAllergyIntoleranceResource($lid, $oeList, false);

        return RestControllerHelper::responseHandler($allergyIntoleranceResource, null, 200);
    }

    public function getAll($search)
    {
        $resourceURL = \RestConfig::$REST_FULL_URL;
        if (strpos($resourceURL, '?') > 0) {
            $resourceURL = strstr($resourceURL, '?', true);
        }

        $pid = $search['pid'];

        $searchResult = $this->listService->getAll($pid, "allergy");
        if ($searchResult === false) {
            http_response_code(404);
            exit;
        }
        $entries = array();
        foreach ($searchResult as $oeList) {
            $entryResource = $this->oeToFhirService->createAllergyIntoleranceResource($oeList['id'], $oeList, false);
            $entry = array(
                'fullUrl' => $resourceURL . "/" . $oeList['id'],
                'resource' => $entryResource
            );
            $entries[] = new FHIRBundleEntry($entry);
        }
        $searchResult = $this->oeToFhirService->createBundle('AllergyIntolerance', $entries, false);
        return RestControllerHelper::responseHandler($searchResult, null, 200);
    }
}
