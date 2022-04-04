<?php

namespace App\Tests\Support;

use Symfony\Component\HttpFoundation\Response;

trait MockSimproApiClientTrait
{
    use MockHttpRequestServiceTrait;

    protected function mockSendJobToSimproNewSite()
    {
        $this->mockHttpRequestService([
            $this->findSiteIdByUprnSiteNotFound(),
            $this->postSiteStage1PostSite(),
            $this->postSiteStage2PatchSiteCustomField(),
            $this->postJob(1234)
        ]);
    }

    protected function mockSendJobToSimproSiteExists()
    {
        $this->mockHttpRequestService([
            $this->findSiteIdByUprnSiteExists(),
            $this->patchSite(),
            $this->postJob(2345)
        ]);
    }

    protected function mockSendJobToSimproPostSiteFailed()
    {
        $this->mockHttpRequestService([
            $this->findSiteIdByUprnSiteNotFound(),
            $this->postSiteStage1PostSite(Response::HTTP_INTERNAL_SERVER_ERROR),
        ]);
    }

    protected function mockSendJobToSimproFindSiteFailed()
    {
        $this->mockHttpRequestService([
            $this->findSiteIdByUprnSiteNotFound(Response::HTTP_INTERNAL_SERVER_ERROR),
        ]);
    }

    protected function mockSendJobToSimproPostJobFailed()
    {
        $this->mockHttpRequestService([
            $this->findSiteIdByUprnSiteNotFound(),
            $this->postSiteStage1PostSite(),
            $this->postSiteStage2PatchSiteCustomField(),
            $this->postJob(1234, Response::HTTP_UNPROCESSABLE_ENTITY)
        ]);
    }

    protected function postJob(int $siteId, int $statusCode = Response::HTTP_CREATED)
    {
        return [
            'type' => 'post',
            'arguments' => [
                'https://gassure.simprocloud.com/api/v1.0/companies/0/jobs/',
                [
                    'Site' => $siteId,
                    'Type' => 'Service',
                    'Stage' => 'Pending',
                    'Customer' => 83,
                    'Status' => 7,
                    'OrderNo' => 'test_job_no',
                    'Name' => 'test_short_description',
                    'Description' => 'test_job_type test_long_description test_sub_location_description',
                    'Notes' => 'test_Access_info test_element_code test_element_description',
                    'DueDate' => '2018-11-11',
                    'DueTime' => '15:11',
                    'ResponseTime' => 3,
                    'Sections' => [
                        [
                            'CostCenters' => [
                                [
                                    'CostCenter' => 4,
                                    'Description' => 'SORJ',
                                    'Stage' => 'Pending',
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer fake_simpro_token',
                ],
            ],
            'response' => [
                'status_code' => $statusCode,
                'fixture' => [
                    'content' => [
                        'ID' => 9999
                    ],
                    'headers' => [],
                ],
            ],
        ];
    }

    protected function patchSite(int $statusCode = Response::HTTP_NO_CONTENT)
    {
        return [
            'type' => 'patch',
            'arguments' => [
                'https://gassure.simprocloud.com/api/v1.0/companies/0/sites/2345',
                [
                    'Name' => 'test_address1',
                    'PrimaryContact' => [
                        'Email' => 'test_Contact_Details',
                        'PreferredNotificationMethod' => 'Telephone',
                        'GivenName' => 'test_Person_Forenames',
                        'FamilyName' => 'test_Person_Surname',
                    ],
                    'Address' => [
                        'Address' => 'test_address1',
                        'City' => 'test_address2',
                        'State' => 'test_address3',
                        'PostalCode' => 'test_post_code',
                    ],
                    'Customers' => [
                        83
                    ],
                ],
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer fake_simpro_token',
                ],
            ],
            'response' => [
                'status_code' => $statusCode,
                'fixture' => [
                    'content' => [],
                    'headers' => [],
                ],
            ],
        ];
    }

    protected function postSiteStage2PatchSiteCustomField(int $statusCode = Response::HTTP_NO_CONTENT)
    {
        return [
            'type' => 'patch',
            'arguments' => [
                'https://gassure.simprocloud.com/api/v1.0/companies/0/sites/1234/customFields/2',
                [
                    'Value' => 'test_place_ref',
                ],
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer fake_simpro_token',
                ],
            ],
            'response' => [
                'status_code' => $statusCode,
                'fixture' => [
                    'content' => [],
                    'headers' => [],
                ],
            ],
        ];
    }

    protected function postSiteStage1PostSite(int $statusCode = Response::HTTP_CREATED)
    {
        return [
            'type' => 'post',
            'arguments' => [
                'https://gassure.simprocloud.com/api/v1.0/companies/0/sites/',
                [
                    'Name' => 'test_address1',
                    'PrimaryContact' => [
                        'Email' => 'test_Contact_Details',
                        'PreferredNotificationMethod' => 'Telephone',
                        'GivenName' => 'test_Person_Forenames',
                        'FamilyName' => 'test_Person_Surname',
                    ],
                    'Address' => [
                        'Address' => 'test_address1',
                        'City' => 'test_address2',
                        'State' => 'test_address3',
                        'PostalCode' => 'test_post_code',
                    ],
                    'Customers' => [
                        83
                    ],
                ],
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer fake_simpro_token',
                ],
            ],
            'response' => [
                'status_code' => $statusCode,
                'fixture' => [
                    'content' => [
                        'ID' => 1234,
                    ],
                    'headers' => [],
                ],
            ],
        ];
    }

    protected function findSiteIdByUprnSiteNotFound(int $statusCode = Response::HTTP_OK)
    {
        return [
            'type' => 'get',
            'arguments' => [
                'https://gassure.simprocloud.com/api/v1.0/companies/0/sites/',
                [
                    'CustomFields.Value' => 'test_place_ref',
                    'CustomFields.CustomField.ID' => 2,
                    'columns' => 'ID'
                ],
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer fake_simpro_token',
                ],
            ],
            'response' => [
                'status_code' => $statusCode,
                'fixture' => [
                    'content' => [],
                    'headers' => [],
                ],
            ],
        ];
    }

    protected function findSiteIdByUprnSiteExists(int $statusCode = Response::HTTP_OK)
    {
        return [
            'type' => 'get',
            'arguments' => [
                'https://gassure.simprocloud.com/api/v1.0/companies/0/sites/',
                [
                    'CustomFields.Value' => 'test_place_ref',
                    'CustomFields.CustomField.ID' => 2,
                    'columns' => 'ID'
                ],
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer fake_simpro_token',
                ],
            ],
            'response' => [
                'status_code' => $statusCode,
                'fixture' => [
                    'content' => [
                        [
                            'ID' => 2345
                        ]
                    ],
                    'headers' => [],
                ],
            ],
        ];
    }
}
