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

class Ontraport extends EmailMarketing
{
    // Properties
    // =========================================================================

    public $handle = 'ontraport';


    // Public Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return Craft::t('formie', 'Ontraport');
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return Craft::t('formie', 'Sign up users to your Ontraport lists to grow your audience for campaigns.');
    }

    /**
     * @inheritDoc
     */
    public function beforeSave(): bool
    {
        if ($this->enabled) {
            $apiKey = $this->settings['apiKey'] ?? '';
            $appId = $this->settings['appId'] ?? '';

            if (!$apiKey) {
                $this->addError('apiKey', Craft::t('formie', 'API key is required.'));
                return false;
            }

            if (!$appId) {
                $this->addError('appId', Craft::t('formie', 'App ID is required.'));
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
            $response = $this->_request('GET', 'Groups');

            $lists = $response['data'] ?? [];

            foreach ($lists as $list) {
                $listFields = [
                    new EmailMarketingField([
                        'tag' => 'email',
                        'name' => Craft::t('formie', 'Email'),
                        'type' => 'email',
                        'required' => true,
                    ]),
                    new EmailMarketingField([
                        'tag' => 'firstname',
                        'name' => Craft::t('formie', 'First Name'),
                        'type' => 'firstname',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'lastname',
                        'name' => Craft::t('formie', 'Last Name'),
                        'type' => 'lastname',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'address',
                        'name' => Craft::t('formie', 'Address'),
                        'type' => 'address',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'address2',
                        'name' => Craft::t('formie', 'Address 2'),
                        'type' => 'address2',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'city',
                        'name' => Craft::t('formie', 'City'),
                        'type' => 'city',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'state',
                        'name' => Craft::t('formie', 'State'),
                        'type' => 'state',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'zip',
                        'name' => Craft::t('formie', 'Zip'),
                        'type' => 'zip',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'country',
                        'name' => Craft::t('formie', 'Country'),
                        'type' => 'country',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'birthday',
                        'name' => Craft::t('formie', 'Birthday'),
                        'type' => 'birthday',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'status',
                        'name' => Craft::t('formie', 'Status'),
                        'type' => 'status',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'home_phone',
                        'name' => Craft::t('formie', 'Home Phone'),
                        'type' => 'home_phone',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'office_phone',
                        'name' => Craft::t('formie', 'Office Phone'),
                        'type' => 'office_phone',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'fax',
                        'name' => Craft::t('formie', 'Fax'),
                        'type' => 'fax',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'company',
                        'name' => Craft::t('formie', 'Company'),
                        'type' => 'company',
                    ]),
                    new EmailMarketingField([
                        'tag' => 'website',
                        'name' => Craft::t('formie', 'Website'),
                        'type' => 'website',
                    ]),
                ];

                $allLists[] = new EmailMarketingList([
                    'id' => $list['id'],
                    'name' => $list['name'],
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

            $payload = $fieldValues;

            // Allow events to cancel sending
            if (!$this->beforeSendPayload($submission, $payload)) {
                return false;
            }

            // Add or update
            $response = $this->_request('POST', 'Contacts', [
                'json' => $payload,
            ]);

            // Allow events to say the response is invalid
            if (!$this->afterSendPayload($submission, $payload, $response)) {
                return false;
            }

            $contactId = $response['data']['unique_id'] ?? '';

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
            $response = $this->_request('GET', 'Groups');
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
        $appId = $this->settings['appId'] ?? '';

        if (!$apiKey) {
            Integration::error($this, 'Invalid API Key for Ontraport', true);
        }

        if (!$appId) {
            Integration::error($this, 'Invalid App ID for Ontraport', true);
        }

        return $this->_client = Craft::createGuzzleClient([
            'base_uri' => 'https://api.ontraport.com/1/',
            'headers' => [
                'Api-Key' => $apiKey,
                'Api-Appid' => $appId,
            ],
        ]);
    }

    private function _request(string $method, string $uri, array $options = [])
    {
        $response = $this->_getClient()->request($method, trim($uri, '/'), $options);

        return Json::decode((string)$response->getBody());
    }
}