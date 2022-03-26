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
        ]);
    }

    protected function mockSendJobToSimproSiteExists()
    {
        $this->mockHttpRequestService([
            $this->findSiteIdByUprnSiteExists(),
            $this->patchSite(),
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
