<?php
namespace verbb\formie\integrations\emailmarketing;

use verbb\formie\base\Integration;
use verbb\formie\base\EmailMarketing;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;
use verbb\formie\errors\IntegrationException;
use verbb\formie\events\SendIntegrationPayloadEvent;
use verbb\formie\models\EmailMarketingField;
use verbb\formie\models\EmailMarketingList;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\web\View;

class Autopilot extends EmailMarketing
{
    // Properties
    // =========================================================================

    public $handle = 'autopilot';


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return Craft::t('formie', 'Autopilot');
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return Craft::t('formie', 'Sign up users to your Autopilot lists to grow your audience for campaigns.');
    }

    /**
     * @inheritDoc
     */
    public function beforeSave(): bool
    {
        if ($this->enabled) {
            $apiKey = $this->settings['apiKey'] ?? '';

            if (!$apiKey) {
                $this->addError('apiKey', Craft::t('formie', 'API key is required.'));
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function fetchLists()
    {
        $allLists = [];

        try {
            $response = $this->_request('GET', 'lists');

            $lists = $response['lists'] ?? [];

            foreach ($lists as $list) {
                // While we're at it, fetch the fields for the list
                $fields = $this->_request('GET', 'contacts/custom_fields');

                $listFields = [
                    new EmailMarketingField([
                        'tag' => 'Email',
                        'name' => Craft::t('formie', 'Email'),
                        'type' => 'email',
                        'required' => true,
                    ]),
                    new EmailMarketingField([
                        'tag' => 'FirstName',
                        'name' => Craft::t('formie', 'First Name'),
                        'type' => 'FirstName',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'LastName',
                        'name' => Craft::t('formie', 'Last Name'),
                        'type' => 'LastName',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'Company',
                        'name' => Craft::t('formie', 'Company'),
                        'type' => 'Company',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'Phone',
                        'name' => Craft::t('formie', 'Phone'),
                        'type' => 'Phone',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'MobilePhone',
                        'name' => Craft::t('formie', 'Mobile Phone'),
                        'type' => 'MobilePhone',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'Website',
                        'name' => Craft::t('formie', 'Website'),
                        'type' => 'Website',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'LeadSource',
                        'name' => Craft::t('formie', 'Lead Source'),
                        'type' => 'LeadSource',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'Status',
                        'name' => Craft::t('formie', 'Status'),
                        'type' => 'Status',
                    ]),
                ];
            
                foreach ($fields as $field) {
                    $listFields[] = new EmailMarketingField([
                        'tag' => $field['fieldType'] . '--' . str_replace(' ', '--', $field['name']),
                        'name' => $field['name'],
                        'type' => $field['fieldType'],
                    ]);
                }

                $allLists[] = new EmailMarketingList([
                    'id' => $list['list_id'],
                    'name' => $list['title'],
                    'fields' => $listFields,
                ]);
            }
        } catch (\Throwable $e) {
            Integration::error($this, Craft::t('formie', 'API error: “{message}” {file}:{line}', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));
        }

        return $allLists;
    }

    /**
     * @inheritDoc
     */
    public function sendPayload(Submission $submission): bool
    {
        try {
            $fieldValues = $this->getFieldMappingValues($submission);

            // Pull out email, as it needs to be top level
            $email = ArrayHelper::remove($fieldValues, 'Email');
            $firstName = ArrayHelper::remove($fieldValues, 'FirstName');
            $lastName = ArrayHelper::remove($fieldValues, 'LastName');
            $company = ArrayHelper::remove($fieldValues, 'Company');
            $phone = ArrayHelper::remove($fieldValues, 'Phone');
            $mobilePhone = ArrayHelper::remove($fieldValues, 'MobilePhone');
            $website = ArrayHelper::remove($fieldValues, 'Website');
            $leadSource = ArrayHelper::remove($fieldValues, 'LeadSource');
            $status = ArrayHelper::remove($fieldValues, 'Status');

            $payload = [
                'contact' => [
                    'Email' => $email,
                    'FirstName' => $firstName,
                    'LastName' => $lastName,
                    'Company' => $company,
                    'Phone' => $phone,
                    'MobilePhone' => $mobilePhone,
                    'Website' => $website,
                    'LeadSource' => $leadSource,
                    'Status' => $status,
                ],
                '_autopilot_list' => $this->listId,
                'custom' => $fieldValues,
            ];

            // Allow events to cancel sending
            if (!$this->beforeSendPayload($submission, $payload)) {
                return false;
            }

            // Add or update
            $response = $this->_request('POST', 'contact', [
                'json' => $payload,
            ]);

            // Allow events to say the response is invalid
            if (!$this->afterSendPayload($submission, $payload, $response)) {
                return false;
            }

            $contactId = $response['contact_id'] ?? '';

            if (!$contactId) {
                Integration::error($this, Craft::t('formie', 'API error: “{response}”', [
                    'response' => Json::encode($response),
                ]));

                return false;
            }
        } catch (\Throwable $e) {
            Integration::error($this, Craft::t('formie', 'API error: “{message}” {file}:{line}', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]));

            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function fetchConnection(): bool
    {
        try {
            $response = $this->_request('GET', 'account');
            $accountId = $response['instance_id'] ?? '';

            if (!$accountId) {
                Integration::error($this, 'Unable to find “{instance_id}” in response.', true);
                return false;
            }
        } catch (\Throwable $e) {
            Integration::error($this, Craft::t('formie', 'API error: “{message}” {file}:{line}', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]), true);

            return false;
        }

        return true;
    }


    // Private Methods
    // =========================================================================

    private function _getClient()
    {
        if ($this->_client) {
            return $this->_client;
        }

        $apiKey = $this->settings['apiKey'] ?? '';

        if (!$apiKey) {
            Integration::error($this, 'Invalid API Key for Autopilot', true);
        }

        return $this->_client = Craft::createGuzzleClient([
            'base_uri' => 'https://api2.autopilothq.com/v1/',
            'headers' => ['autopilotapikey' => $apiKey],
        ]);
    }

    private function _request(string $method, string $uri, array $options = [])
    {
        $response = $this->_getClient()->request($method, trim($uri, '/'), $options);

        return Json::decode((string)$response->getBody());
    }
}