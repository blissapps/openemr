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
use OpenEMR\Services\FhirResourcesService;
use OpenEMR\RestControllers\RestControllerHelper;
use HL7\FHIR\STU3\FHIRResource\FHIRBundle\FHIRBundleEntry;

use Particle\Validator\ValidationResult;

class FhirPatientRestController
{
    private $patientService;
    private $fhirService;

    public function __construct($pid)
    {
        $this->patientService = new PatientService();
        $this->patientService->setPid($pid);
        $this->fhirService = new FhirResourcesService();
    }

    /**
     * @param $fhirPatientResource \HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient The patient to create
     * @return \ValidationResponse | \HL7\FHIR\STU3\FHIRDomainResource\FHIRPatient
     */
    public function post($fhirPatientResource)
    {
        $oePatient = $this->fhirService->createOePatientResource($fhirPatientResource);
        $validationResult = $this->patientService->validate($oePatient);
        if($validationResult->isNotValid()){
            return RestControllerHelper::responseHandler($validationResult, null, 400);
        }
        $pid = $this->patientService->insert($oePatient);

        if($pid === false){
            return RestControllerHelper::responseHandler("Couldn't insert patient.", null, 400);
        }

        $this->patientService->setPid($pid);

        return $this->getOne();

    }

    public function getOne()
    {
        $oept = $this->patientService->getOne();
        $pid = $this->patientService->getPid();
        $patientResource = $this->fhirService->createPatientResource($pid, $oept, false);

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
            $entryResource = $this->fhirService->createPatientResource($oept['pid'], $oept, false);
            $entry = array(
                'fullUrl' => $resourceURL . "/" . $oept['pid'],
                'resource' => $entryResource
            );
            $entries[] = new FHIRBundleEntry($entry);
        }
        $searchResult = $this->fhirService->createBundle('Patient', $entries, false);
        return RestControllerHelper::responseHandler($searchResult, null, 200);
    }
}
