<?php

/***** ***** ***** ***** *****
* Send xAPI traces for LimeSurvey responses
*
* @originalauthor Santilario Berthilier, Julio - eUCM Team <jsantila@ucm.es>
* @license GPL v3
* @version 1.0.4
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
***** ***** ***** ***** *****/

class LimeSurveyXAPITracker extends PluginBase
	{
		protected $storage = 'DbStorage';
		static protected $description = 'A simple xAPI Tracker for LimeSurvey';
		static protected $name = 'LimeSurveyXAPITracker';
        protected $sessionKey = '';

		public function init()
		{ 
            $this->subscribe('beforeSurveySettings');
            $this->subscribe('newSurveySettings');
            $this->subscribe('afterSurveyComplete');
            $this->subscribe('beforeSurveyPage');
            $this->subscribe('afterResponseSave');
        }

        public function afterSurveyComplete() {
            $this->sendXAPIDataFromSurvey('afterSurveyComplete');
            return;
        }

        public function beforeSurveyPage() {
            $this->sendXAPIDataFromSurvey('beforeSurveyPage');
            return;
        }

        public function afterResponseSave() {
            $this->sendXAPIDataFromSurvey('afterResponseSave');
            return;
        }

        public function setSurveySettings($surveyId, $settingsArray) {
            if($surveyId === 0) {
                $this->setGlobalSettings($settingsArray);
            } else {
                $this->customLog($surveyId);
                foreach($settingsArray as $key => $value) {
                    $this->customLog("key: $key, value: $value\n");
                    $this->set($key, $value, "Survey", $surveyId);
                }
            };
        }

        public function setGlobalSettings($settingsArray) {
            foreach($settingsArray as $key => $value) {
                $this->customLog("key: $key, value: $value\n");
                $this->set($name, $value, "global", null);
            }
        }

        public function newSurveySettings()
        {
            $event = $this->event;
            foreach ($event->get('settings') as $name => $value)
            {
                $this->set($name, $value, 'Survey', $event->get('survey'));
            }
        } 

        public function beforeSurveySettings() {
            $event = $this->event;
            $surveyId = $event->get('survey');
            $settings=array();
            if((boolean)$this->getGlobalSetting('surveylrsendpoint', false)) {
                $endpoint= $this->get('lrs-endpoint', 'Survey', $surveyId);
                $settings=array(
                    'info1' => array(
                        'type' => 'info',
                        'content' => '<h4>LRS INFO</h4>',
                    ),
                    'lrs-endpoint'=>array(
                        'type'=>'string',
                        'label'=>'LRS Endpoint',
                        'help'=>'LRS Endpoint value',
                        'current' => $endpoint,
                        'htmlOptions' => [
                            'readonly' => !empty($endpoint)
                        ],
                        'default' => '',
                    )
                );
            } else {
                $settings=array(
                    'info1' => array(
                        'type' => 'info',
                        'content' => '<h4>LRS INFO DEFINED GLOBALLY</h4>',
                    ),
                    'lrs-endpoint'=>array(
                        'type'=>'string',
                        'label'=>'LRS Endpoint',
                        'help'=>'LRS Endpoint value',
                        'current' => $this->getGlobalSetting("lrsEndpoint"),
                        'htmlOptions' => [
                            'readonly' => true
                        ],
                        'default' => '',
                    )
                );
            }

            $event->set("surveysettings.{$this->id}", array(
                'name' => get_class($this),
                'settings' => $settings
            ));
        }

        protected $limesurveyToXapiInteractionTypes = [
            // 5-point choice (radio)
            '5' => 'choice',

            // Array (10-point choice)
            'B' => 'likert',

            // Array (5-point choice)
            'A' => 'likert',

            // Array (Flexible Labels) dual scale
            '1' => 'likert',

            // Array (Increase, Same, Decrease)
            'E' => 'likert',

            // Array (Multi Flexible) (Numbers)
            ':' => 'likert',

            // Array (Multi Flexible) (Text)
            ';' => 'likert',

            // Array (Yes/No/Uncertain)
            'C' => 'likert',

            // Array (flexible labels)
            'F' => 'likert',

            // Array (flexible labels) by column
            'H' => 'likert',

            // Boilerplate question (no direct xAPI equivalent, treated as text)
            'X' => 'other',

            // Date
            'D' => 'fill-in',

            // Gender (single choice)
            'G' => 'choice',

            // Huge free text
            'U' => 'long-fill-in',

            // Language switch (treated as text)
            'I' => 'other',

            // List (dropdown)
            '!' => 'choice',

            // List (radio)
            'L' => 'choice',

            // List with comment (choice + text)
            'O' => 'choice', // (Comment can be stored separately)

            // Long free text
            'T' => 'long-fill-in',

            // Multiple numerical input
            'K' => 'numeric',

            // Multiple options (checkboxes)
            'M' => 'choice',

            // Multiple options with comments (choice + text)
            'P' => 'choice', // (Comments can be stored separately)

            // Multiple short text
            'Q' => 'fill-in',

            // Numerical input
            'N' => 'numeric',

            // Ranking
            'R' => 'sequencing',

            // Short free text
            'S' => 'fill-in',

            // Yes/No
            'Y' => 'true-false',
        ];

        protected $settings = [];

        /**
        * @param mixed $getValues
        */
        public function getPluginSettings($getValues = true) {
            /* Definition and default */
            $fixedPluginSettings = $this->getFixedGlobalSetting();
		    $this->settings = array(
                'baseUrlLRC' => array(
                    'type' => 'string',
                    'label' => 'The default Remote Control URL',
                    'default' => $this->getGlobalSetting('baseUrlLRC', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('baseUrlLRC', $fixedPluginSettings)
                    ],
                    'help' => 'The default Remote Control URL'
                ),
                'usernameLRC' => array(
                    'type' => 'string',
                    'label' => 'Remote Control Username',
                    'default' => $this->getGlobalSetting('usernameLRC', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('usernameLRC', $fixedPluginSettings)
                    ],
                    'help' => 'Remote Control Username'
                ),
                'passwordLRC' => array(
                    'type' => 'string',
                    'label' => 'Remote Control Password',
                    'default' => $this->getGlobalSetting('passwordLRC', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('passwordLRC', $fixedPluginSettings)
                    ],
                    'help' => 'Remote Control Password'
                ),
                'sId' => array(
                    'type' => 'string',
                    'label' => 'The ID of the surveys:',
                    'default' => $this->getGlobalSetting('sId', '000000'),
                    'htmlOptions' => [
                        'readonly' => in_array('sId', $fixedPluginSettings)
                    ],
                    'help' => 'The unique number of the surveys. You can set multiple surveys with an "," as separator. Example: 123456, 234567, 345678. Let empty to treat all'
                ),
                'surveylrsendpoint' => array(
                    'type' => 'checkbox',
                    'default' => $this->getGlobalSetting('surveylrsendpoint', false),
                    'htmlOptions' => [
                        'readonly' => in_array('surveylrsendpoint', $fixedPluginSettings)
                    ],
                    'label' => 'Enable Survey LRS Endpoint Mode',
                    'help' => 'Enable Survey LRS Endpoint Mode.'
                ),
                'lrsEndpoint' => array(
                    'type' => 'string',
                    'label' => 'The default URL to send the xapi data to:',
                    'default' => $this->getGlobalSetting('lrsEndpoint', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('lrsEndpoint', $fixedPluginSettings)
                    ],
                    'help' => 'To test get one from https://webhook.site'
                ),
                'actorHomepage' => array(
                    'type' => 'string',
                    'label' => 'The actor homepage of the xapi data:',
                    'default' => $this->getGlobalSetting('actorHomepage', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('actorHomepage', $fixedPluginSettings)
                    ],
                    'help' => 'The actor homepage of the xapi data'
                ),
                'oAuthType' => array(
                    'type' => 'select',
                    'label' => 'oAuth Type',
                    'default' => $this->getGlobalSetting('oAuthType', ''),
                    'options' => [
                        'oauth1' => 'OAuth 1',
                        'oauth2' => 'OAuth 2',
                    ],
                    'htmlOptions' => [
                        'readonly' => in_array('oAuthType', $fixedPluginSettings)
                    ],
                    'help' => 'OAuth Type'
                ),
                'usernameOAuth' => array(
                    'type' => 'string',
                    'label' => 'OAuth Username',
                    'default' => $this->getGlobalSetting('usernameOAuth', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('usernameOAuth', $fixedPluginSettings)
                    ],
                    'help' => 'OAuth Username'
                ),
                'passwordOAuth' => array(
                    'type' => 'string',
                    'label' => 'OAuth Password',
                    'default' => $this->getGlobalSetting('passwordOAuth', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('passwordOAuth', $fixedPluginSettings)
                    ],
                    'help' => 'OAuth Password'
                ),
                'OAuth2TokenEndpoint' => array(
                    'type' => 'string',
                    'label' => 'OAuth2 Token Endpoint',
                    'default' => $this->getGlobalSetting('OAuth2TokenEndpoint', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('OAuth2TokenEndpoint', $fixedPluginSettings)
                    ],
                    'help' => 'OAuth2 Token Endpoint'
                ),
                'OAuth2LogoutEndpoint' => array(
                    'type' => 'string',
                    'label' => 'OAuth2 Logout Endpoint',
                    'default' => $this->getGlobalSetting('OAuth2LogoutEndpoint', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('OAuth2LogoutEndpoint', $fixedPluginSettings)
                    ],
                    'help' => 'OAuth2 Logout Endpoint'
                ),
                'OAuth2ClientId' => array(
                    'type' => 'string',
                    'label' => 'OAuth2 Client ID',
                    'default' => $this->getGlobalSetting('OAuth2ClientId', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('OAuth2ClientId', $fixedPluginSettings)
                    ],
                    'help' => 'OAuth2 Client ID'
                ),
                'sBug' => array(
                    'type' => 'checkbox',
                    'default' => $this->getGlobalSetting('sBug', false),
                    'htmlOptions' => [
                        'readonly' => in_array('sBug', $fixedPluginSettings)
                    ],
                    'label' => 'Enable Debug Mode',
                    'help' => 'Enable debugmode to see what data is transmitted. Respondents will see this as well so you should turn this off for live surveys'
                )
		    );

            /* Get current */
            $pluginSettings = parent::getPluginSettings($getValues);
            /* Update current for fixed one */
            if ($getValues) {
                foreach ($fixedPluginSettings as $setting) {
                    $pluginSettings[$setting]['current'] = $this->getGlobalSetting($setting);
                }
            }
            /* Remove hidden */
            foreach ($this->getHiddenGlobalSetting() as $setting) {
                unset($pluginSettings[$setting]);
            }
            return $pluginSettings;
        }

        function limesurvey_api_request($method, $params = [])
        {
            $request = json_encode([
                'method' => $method,
                'params' => $params,
                'id'     => 1,
            ]);

            $ch = curl_init($this->getGlobalSetting('baseUrlLRC'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            curl_close($ch);
            #$this->customLog($response);
            return json_decode($response, true);
        }

        function auth_LRC() {
            $response = $this->limesurvey_api_request('get_session_key', [
                $this->getGlobalSetting("usernameLRC"),
                $this->getGlobalSetting("passwordLRC")
            ]);
            if (isset($response['result'])) {
                $this->sessionKey = $response['result'];
            } else {
                throw new Exception("Error obtaining session key: " . $response);
            }
        }

        function release_LRC() {
            $this->limesurvey_api_request('release_session_key', [
                $this->sessionKey,
            ]);
        }

        function exportFullResponseLRC($surveyId, $lang, $token) {
            $response = $this->limesurvey_api_request('export_responses_by_token', [
                $this->sessionKey, //sSessionKey
                $surveyId, //iSurveyID
                'json', // sDocumentType Format: json, csv, or xml
                $token, // $token
                $lang,  // sLanguageCodeReplace with your survey language code
                'all', // sCompletionStatus Options: 'complete', 'incomplete', 'all'
                'code', // sHeadingType Options: 'code', 'full', 'abbreviated'
                'short', // sResponseType Options: 'short', 'long'
            ]);
        
            if (isset($response['result'])) {
                $responsesJson = base64_decode($response['result']);
                $responses =json_decode($responsesJson, true)['responses'];
                #$this->customLog("Survey Responses: " . json_encode($responses));
                return $responses[0];
            } else {
                throw new Exception("Error fetching responses: " . $response['error']);
            }
        }

        function exportQuestionGroupSurveyLRC($surveyId, $lang, $lastpage) {
            // Initialize an array to hold the result
            $groupedQuestions = [];
            
            // Get groups
            $groupsResult = $this->limesurvey_api_request('list_groups', [
                $this->sessionKey, 
                $surveyId,
                $lang
            ]);
            $groups=$groupsResult['result'];
            // Get questions for each group
            foreach ($groups as $group) {
                $gid = $group['gid'];
                $groupName = $group['group_name'];
                #$this->customLog(json_encode($group));
                #$this->customLog($gid . " | " . $groupName . " Last page : ". $lastpage);
                if($group["group_order"] == $lastpage) {
                    $questionsResult = $this->limesurvey_api_request('list_questions', [
                        $this->sessionKey, 
                        $surveyId,
                        $gid,
                        $lang
                    ]);
                    $questions=$questionsResult['result'];
                    return $questions;
                }
            }
            return array();
        }

        function exportQuestionPropertiesLRC($qid, $lang, $surveyLanguages) {
            // Validate that the language is in the survey languages array
            if (!in_array($lang, $surveyLanguages)) {
                $this->customLog("Warning: Language $lang not found in survey languages. Using first language.");
                $lang = $surveyLanguages[0];
            }
            
            // Fetch properties for the specified language
            $questionPropertiesResult = $this->limesurvey_api_request('get_question_properties', [
                $this->sessionKey, 
                $qid,
                ["answeroptions", "question_theme_name", "type"],
                $lang
            ]);
            $questionProperties = [];
            if (
                is_array($questionPropertiesResult)
                && array_key_exists('result', $questionPropertiesResult)
                && is_array($questionPropertiesResult['result'])
            ) {
                $questionProperties = $questionPropertiesResult['result'];
            } else {
                $this->customLog("get_question_properties returned invalid payload for qid " . $qid . ": " . json_encode($questionPropertiesResult));
            }

            $this->customLog(json_encode($questionProperties));
            $questionType = $questionProperties["type"] ?? null;
            $questionProperties["interactionType"] = $this->limesurveyToXapiInteractionTypes[$questionType] ?? "other";
            $this->customLog(json_encode($questionProperties));
            switch ($questionProperties["interactionType"]) {
                case "likert":
                case "sequencing":
                case "choice":
                    $choices = array();
                    $choicesNumbers = 0;
                    $answersById = array();
                    // Fetch answeroptions for all languages and merge
                    foreach ($surveyLanguages as $langCode) {
                        if ($langCode === $lang) {
                            $langPropsResult = array('result' => array('answeroptions' => $questionProperties['answeroptions'] ?? null));
                        } else {
                            $langPropsResult = $this->limesurvey_api_request('get_question_properties', [
                                $this->sessionKey,
                                $qid,
                                ["answeroptions"],
                                $langCode
                            ]);
                        }
                        if (
                            is_array($langPropsResult)
                            && array_key_exists('result', $langPropsResult)
                            && is_array($langPropsResult['result'])
                            && isset($langPropsResult['result']['answeroptions'])
                        ) {
                            // Check if answeroptions is actually an array (not a string like "No available answer options")
                            if (is_array($langPropsResult['result']['answeroptions'])) {
                                foreach ($langPropsResult['result']['answeroptions'] as $choiceId => $choiceData) {
                                    if (!isset($answersById[$choiceId])) {
                                        $answersById[$choiceId] = array(
                                            'id' => (string)$choiceId,
                                            'description' => array()
                                        );
                                    }
                                    $answersById[$choiceId]['description'][$langCode] = (string)$choiceData['answer'];
                                }
                            }
                        }
                    }
                    // Add numeric choices for likert if needed
                    if ($questionType == "A" || $questionType == "5") {
                        $choicesNumbers = 5;
                    } elseif ($questionType == "B") {
                        $choicesNumbers = 10;
                    }
                    if ($choicesNumbers !== 0) {
                        for ($i = 1; $i <= $choicesNumbers; $i++) {
                            if (!isset($answersById[$i])) {
                                $answersById[$i] = array(
                                    'id' => (string)$i,
                                    'description' => array()
                                );
                            }
                            foreach ($surveyLanguages as $langCode) {
                                $answersById[$i]['description'][$langCode] = (string)$i;
                            }
                        }
                    }
                    // Re-index as array
                    $choices = array_values($answersById);
                    $questionProperties['answers'] = $choices;
                    break;
                default:
                    $this->customLog("Nothing to do");
                    //code block
            }
            return $questionProperties;
        }

        function loginViaOAuth() {
            $oauthType = $this->getGlobalSetting('oAuthType','');
            #$this->customLog($oauthType);
            $username=$this->getGlobalSetting('usernameOAuth');
            $password=$this->getGlobalSetting('passwordOAuth');
            if($oauthType == "oauth2") {
                $tokenEndpoint=$this->getGlobalSetting('OAuth2TokenEndpoint');
                $clientId=$this->getGlobalSetting('OAuth2ClientId');
                $expire_at=$this->get("expire_at", null, null, "");
                if($expire_at != "") {
                    $access_token=$this->get("access_token", null, null, "");
                    $time_start=microtime(true);
                    #$this->customLog("Expire at : " . $expire_at . " start : " . $time_start);
                    if((int)$time_start > (int)$expire_at) {
                        $refresh_expires_at=$this->get("refresh_expires_at", null, null, "");
                        if((int)$time_start > (int)$refresh_expires_at) {
                            $access_token=$this->authOAuth2ViaUserAndPassword($clientId, $username, $password);
                        } else {
                            $refresh_token=$this->get("refresh_token", null, null, "");
                            $authParams = array(
                                "grant_type" => "refresh_token",
                                "client_id" => $clientId,
                                "refresh_token" => $refresh_token,
                            );
                            #$this->customLog($tokenEndpoint . "Params : " . http_build_query($authParams));
                            $res = $this->httpPost($tokenEndpoint, http_build_query($authParams), false, "application/x-www-form-urlencoded");
                            #$this->customLog($res);
                            $decoded=json_decode($res, true);
                            $access_token=$decoded["access_token"];
                            $this->set("access_token", $access_token);
                            $timestamp= (int)microtime(true) + (int)$decoded["expires_in"];
                            $this->set("expire_at", $timestamp);
                        }
                    }
                } else {
                    $access_token=$this->authOAuth2ViaUserAndPassword($clientId, $username, $password);
                }
                $auth="Bearer " . $access_token;
            } else {
                $combinedString = $username . ":" . $password;
                $token = base64_encode($combinedString);
                $auth="Basic " . $token;
            }
            #$this->customLog("Type : " . $oauthType . " | Auth : " . $auth);
            return $auth;
        }

        function authOAuth2ViaUserAndPassword($clientId, $username, $password) {
            $tokenEndpoint=$this->getGlobalSetting('OAuth2TokenEndpoint');
            $authParams = array(
                "grant_type" => "password",
                "client_id" => $clientId,
                "username" => $username,
                "password" => $password,
            );
            #$this->customLog($tokenEndpoint . "Params : " . http_build_query($authParams));
            $res = $this->httpPost($tokenEndpoint, http_build_query($authParams), false, "application/x-www-form-urlencoded");
            $time_start = microtime(true);
            $decoded = json_decode($res, true);
            if (!is_array($decoded) || !isset($decoded["access_token"])) {
                throw new Exception("OAuth2 token endpoint returned invalid payload: " . (string)$res);
            }
            $expires_in = isset($decoded["expires_in"]) ? (int)$decoded["expires_in"] : 0;
            $refresh_expires_in = isset($decoded["refresh_expires_in"]) ? (int)$decoded["refresh_expires_in"] : 0;
            $refresh_token = $decoded["refresh_token"] ?? null;
            $access_token = (string)$decoded["access_token"];
            $timestamp = (int)$time_start + $expires_in;
            $refreshtimestamp = (int)$time_start + $refresh_expires_in;
            $this->set("expire_at", $timestamp);
            $this->set("refresh_expires_at", $refreshtimestamp);
            $this->set("refresh_token", $refresh_token);
            $this->set("access_token", $access_token);
            return $access_token;
        }

        function logoutViaOAuth() {
            $oauthType = $this->getGlobalSetting('oAuthType','');
            #$this->customLog($oauthType);
            if($oauthType == "oauth2") {
                $logoutEndpoint=$this->getGlobalSetting('OAuth2LogoutEndpoint');
                $clientId=$this->getGlobalSetting('OAuth2ClientId');
                $authParams = array(
                    "grant_type" => "refresh_token",
                    "client_id" => $clientId,
                    "refresh_token" => $this->get("refresh_token", null, null, ""),
                );
                #$this->customLog($logoutEndpoint . "Params : " . http_build_query($authParams));
                $res = $this->httpPost($logoutEndpoint, http_build_query($authParams), false, "application/x-www-form-urlencoded");
                #$this->customLog("Res : " . $res);
                $decoded=json_decode($res, true);
                #$this->customLog("Decoded : " . $decoded);
                $this->set("refresh_token", NULL);
                $this->set("access_token", NULL);
                $this->set("refresh_expires_at", NULL);
                $this->set("expire_at", NULL);
            }
        }

		/***** ***** ***** ***** *****
		* Send XAPI Data From Survey Result
		* @return array | response
		***** ***** ***** ***** *****/
		private function sendXAPIDataFromSurvey($comment)
		{
            $time_start=microtime(true);
            $bug = (boolean)$this->getGlobalSetting('sBug');
            
            $event = $this->getEvent();
            $surveyId = $event->get('surveyId');
            $hookSurveyId = $this->getGlobalSetting('sId','');
            $hookSurveyIdArray = explode(',', preg_replace('/\s+/', '', $hookSurveyId));
            
            if ($hookSurveyId != '') {
                if(!in_array($surveyId, $hookSurveyIdArray)) {
                    return;
                }
            }
            
            $surveyUrl = Yii::app()->getController()->createAbsoluteUrl('survey/index', array('sid' => $surveyId)); // Adjust 'lang' as needed.

            // Try to fetch the current from the URL manually or default language
            $surveyInfo = Survey::model()->findByPk($surveyId);
            $languageRequest=Yii::app()->request->getParam('lang', null);
            
            // Validate that the requested language is available for this survey
            $availableLanguages = array();
            if (isset($surveyInfo->additional_languages) && !empty($surveyInfo->additional_languages)) {
                $availableLanguages = explode(' ', trim($surveyInfo->additional_languages));
            }
            $availableLanguages[] = $surveyInfo->language; // Add default language
            
            // If language requested and available, use it; otherwise use survey default
            if ($languageRequest !== null && in_array($languageRequest, $availableLanguages)) {
                $lang = $languageRequest;
            } else {
                $lang = $surveyInfo->language;
            }
            
            // Log the language being used for debugging
            $this->customLog("Using language: " . $lang . " for survey " . $surveyId);
            $this->customLog("Available languages for survey: " . implode(', ', $availableLanguages));

            // Get token from the URL manually
            $token=Yii::app()->request->getParam('token', null);
            $registrationIdKey="registration_" . $token ;
            $registrationId=$this->get($registrationIdKey, 'Survey', $surveyId);
            if ($comment === 'afterSurveyComplete') {
                if($registrationId == null) {
                    $this->customLog($registrationIdKey . "not found"); 
                }
                $this->customLog("Found " . $registrationIdKey . "set to " . $registrationId);
                $this->customLog("Unset " . $registrationIdKey);
                $this->set($registrationIdKey, NULL, "Survey", $surveyId);
            } else if($comment === 'afterResponseSave') {
                if($registrationId == null) {
                    $this->customLog($registrationIdKey . "not found"); 
                }
                $this->customLog("Found " . $registrationIdKey . "set to " . $registrationId);
            } else {
                if($registrationId == null) {
                    $registrationId=$this->uuidv4();
                    $this->customLog("Setting " . $registrationIdKey . "to " . $registrationId);
                    $this->set($registrationIdKey, $registrationId, "Survey", $surveyId);
                } else {
                    $this->customLog("Already found " . $registrationIdKey . " set to " . $registrationId);
                    $this->customLog("Start statement already sent.");
                    return;
                }
            }
            $actor=array(
                "account" => 
                    array(
                        "name" => $token,
                        "homePage" => $this->getGlobalSetting('actorHomepage')
                    )
            );
            $context=array(
                "contextActivities"=> array(
                    "category"=> array(
                        array("id"=>"https://w3id.org/xapi/scorm")
                    ),
                ),
                "registration"=>$registrationId,
                "language"=>$lang
            );
            $surveyObject=array(
                "id" => $surveyUrl,
                "definition" => array(
                    "type" => "http://adlnet.gov/expapi/activities/assessment"
                )
            );
            $questionsContext=$context;
            $questionsContext["contextActivities"]["parent"]=array($surveyObject);
            $date = DateTime::createFromFormat('U.u', sprintf('%.6f', $time_start), new DateTimeZone('UTC'));
            $stringTimestampUTC=$date->format('Y-m-d\TH:i:s.v\Z');
            // Access the API
            $api = $this->pluginManager->getAPI();
            $groups = QuestionGroup::model()->findAllByAttributes(array(
                'sid' => $surveyId
            ));
            $total_pagecount = count($groups);
            // Include response data only for completion
            if ($comment === 'afterSurveyComplete') {
                $responseId = $event->get('responseId');
                // Fetch response data manually from the survey table
                $response = $api->getResponse($surveyId, $responseId);
                $progressedStatement=array(
                    "id"=>$this->uuidv4(),
                    "actor" => $actor,
                    "object" => $surveyObject,
                    "verb" => array("id" => "http://adlnet.gov/expapi/verbs/progressed"),
                    "result" => array(
                        "score" => array(
                            "scaled" => 1,
                            "raw" => $total_pagecount,
                            "min" => 0,
                            "max" => $total_pagecount
                        )
                    ),
                    "context" => $context,
                    "timestamp" => $stringTimestampUTC
                );
                $completedStatement=array(
                    "id"=>$this->uuidv4(),
                    "actor" => $actor,
                    "verb" => array("id" => "http://adlnet.gov/expapi/verbs/terminated"),
                    "result" => array("success" => true, "completion" => true),
                    "object" => $surveyObject,
                    "context" => $context,
                    "timestamp" => $stringTimestampUTC
                );
                $statements=array($progressedStatement, $completedStatement);
            } else if($comment === 'afterResponseSave') {
                // Get the responses for the survey with the specified condition
                $responses = $this->getLastResponse($surveyId, $token);
                $lastpage = isset($responses["lastpage"]) ? (int)$responses["lastpage"] : 0;
                try {
                    // Step 1: Get a session key
                    $this->auth_LRC();
                    // Step 2: Export responses
                    $fullResponse=$this->exportFullResponseLRC($surveyId, $lang, $token);
                    $questions=$this->exportQuestionGroupSurveyLRC($surveyId, $lang, $lastpage);
                    $multiLanguagesQuestions = array();
                    $ResponsesStatement=array();
                    $isMulti=false;
                    $multiTitle=array();
                    // Get all available languages for the survey
                    $surveyLanguages = array();
                    if (isset($surveyInfo->additional_languages) && !empty($surveyInfo->additional_languages)) {
                        $surveyLanguages = explode(' ', trim($surveyInfo->additional_languages));
                    }
                    // Ensure base language is first and remove duplicates
                    $baseLanguage = $surveyInfo->language;
                    $surveyLanguages = array_unique(array_merge(array($baseLanguage), $surveyLanguages));
                    foreach($surveyLanguages as $langCode) {
                        if($langCode == $lang) {
                            $langQuestions=$questions;
                        } else {
                            $langQuestions=$this->exportQuestionGroupSurveyLRC($surveyId, $langCode, $lastpage);
                        }
                        $multiLanguagesQuestions[$langCode]=array();
                        foreach($langQuestions as $question) {
                            $multiLanguagesQuestions[$langCode][$question["id"]]=$question;
                        }
                    }
                    $this->customLog("Survey languages array: " . json_encode($surveyLanguages));
                    foreach($questions as $question) {
                        $this->customLog("Question: " . json_encode($question));
                        $questionProperties=$this->exportQuestionPropertiesLRC($question["id"], $lang,$surveyLanguages);
                        $this->customLog("Question Properties: " . json_encode($questionProperties));
                        $response="";
                        if($question["question_theme_name"] === "arrays/array") {
                            $isMulti=true;
                            $multiTitle[$question["id"]]=$question;
                            #$this->customLog($question["id"] . " is multi.");
                        } elseif($isMulti) {
                            $title=$question["title"];
                            if(array_key_exists($question["parent_qid"], $multiTitle)) {
                                $tmpTitle=$title;
                                $foundMultiTitle=$multiTitle[$question["parent_qid"]]["title"];
                                $title=$foundMultiTitle . "[" . $tmpTitle . "]";
                                $titleUrl = $foundMultiTitle . "/" . $tmpTitle;
                                #$this->customLog($title);
                                if(array_key_exists($title,$fullResponse)) {
                                    $questionProperties=$this->exportQuestionPropertiesLRC($question["parent_qid"], $lang,$surveyLanguages);
                                    $response=$fullResponse[$title];
                                    #$this->customLog($response);
                                } else {
                                    #$this->customLog($title .  "not found in response!");
                                }
                            } else {
                                $titleUrl=$title;
                                if(array_key_exists($title,$fullResponse)) {
                                    $response=$fullResponse[$title];
                                    #$this->customLog($response);
                                } else {
                                    #$this->customLog($title .  "not found in response!");
                                }
                            }
                        } else {
                            $title=$question["title"];
                            $titleUrl=$title;
                            if(array_key_exists($title,$fullResponse)) {
                                $response=$fullResponse[$title];
                                #$this->customLog($response);
                            } else {
                                #$this->customLog($title .  " not found in response!");
                            }
                        }
                        if($response !== "") {
                            // Build name and description arrays for all languages
                            $nameLangs = array();
                            $descLangs = array();
                            $this->customLog("Building multilingual content for question " . $question["id"] . " in language: " . $lang);
                            $this->customLog("Survey question to process: " . json_encode($question));
                            $defaultQuestionTitle = isset($question["title"]) ? $question["title"] : $title;
                            $defaultQuestionText = isset($question["question"]) ? $question["question"] : $title;
                            foreach ($surveyLanguages as $langCode) {
                                if($langCode == $lang) {
                                    $this->customLog("Processing selected language: " . $langCode . " for question " . $question["id"]);
                                    // Default to question title for name and question text for description
                                    $questionTitle = $defaultQuestionTitle;
                                    $questionText = $defaultQuestionText;
                                } else {
                                    $questionInLang = $multiLanguagesQuestions[$langCode][$question["id"]] ?? null;
                                    if ($questionInLang === null) {
                                        $this->customLog("Warning: Question with ID " . $question["id"] . " not found for language " . $langCode . ". Using defaults.");
                                        $questionTitle = $defaultQuestionTitle;
                                        $questionText = $defaultQuestionText;
                                    } else {
                                        $this->customLog("Found question for language " . $langCode . ": " . json_encode($questionInLang));
                                        $questionTitle = isset($questionInLang["title"]) ? $questionInLang["title"] : $defaultQuestionTitle;
                                        $questionText = isset($questionInLang["question"]) ? $questionInLang["question"] : $defaultQuestionText;
                                    }
                                }
                                // Ensure we're using the question title as the name (as requested in the example)
                                $nameLangs[$langCode] = $questionTitle;
                                $descLangs[$langCode] = $questionText;
                            }
                            $this->customLog("Name langs: " . json_encode($nameLangs));
                            $this->customLog("Desc langs: " . json_encode($descLangs));

                            $questionObject = array(
                                "id" => "$surveyUrl/interactions/$titleUrl",
                                "definition" => array(
                                    "name" => $nameLangs,
                                    "description" => $descLangs,
                                    "interactionType" => $questionProperties["interactionType"],
                                    "type" => "http://adlnet.gov/expapi/activities/interaction"
                                ),
                            );
                            
                            // Debug logging to verify structure
                            $this->customLog("Created questionObject: " . json_encode($questionObject));
                            switch ($questionProperties["interactionType"]) {
                                case "likert":
                                    if(isset($questionProperties["answers"])) {
                                        $questionObject["definition"]["scale"] = $questionProperties["answers"];
                                    }
                                    break;
                                case "sequencing":
                                case "choice":
                                    if(isset($questionProperties["answers"])) {
                                        $questionObject["definition"]["choices"] = $questionProperties["answers"];
                                    }
                                    break;
                                default:
                                    //code block
                            }
                            $statement = array(
                                "id"=>$this->uuidv4(),
                                "actor" => $actor,
                                "verb" => array("id"=> "http://adlnet.gov/expapi/verbs/responded"),
                                "object" => $questionObject,
                                "result" => array(
                                    "response" => "$response"
                                ),
                                "context" => $questionsContext,
                                "timestamp" => "$stringTimestampUTC"
                            );
                            
                            // Debug logging to verify final statement structure
                            $this->customLog("Final statement object: " . json_encode($statement["object"]));
                            
                            array_push($ResponsesStatement,$statement);
                        }
                    }
                    if($lastpage === $total_pagecount) {
                        $statements=$ResponsesStatement;
                    } else {
                        $res=$lastpage/$total_pagecount;
                        $progressedStatement = array(
                            "id"=>$this->uuidv4(),
                            "actor" => $actor,
                            "verb" => array("id"=> "http://adlnet.gov/expapi/verbs/progressed"),
                            "object" => $surveyObject,
                            "result" => array(
                                "score" => array(
                                    "scaled" => round($res, 2),
                                    "raw" => $lastpage,
                                    "min" => 0,
                                    "max" => $total_pagecount
                                )
                            ),
                            "context" => $context,
                            "timestamp" => "$stringTimestampUTC"
                        );
                        array_push($ResponsesStatement,$progressedStatement);
                        $statements=$ResponsesStatement;
                    }
                    
                    // Step 3: Release the session key
                    $this->release_LRC();
                } catch(e) {
                    $this->customLog(e);
                }
            } else {
                $startedStatement = array(
                    "id"=>$this->uuidv4(),
                    "actor" => $actor,
                    "verb" => array("id"=> "http://adlnet.gov/expapi/verbs/initialized"),
                    "object" => $surveyObject,
                    "context" => $context,
                    "timestamp" => "$stringTimestampUTC"
                );
                $progressedStatement=array(
                    "id"=>$this->uuidv4(),
                    "actor" => $actor,
                    "object" => $surveyObject,
                    "verb" => array("id" => "http://adlnet.gov/expapi/verbs/progressed"),
                    "result" => array(
                        "score" => array(
                            "scaled" => 0,
                            "raw" => 0,
                            "min" => 0,
                            "max" => $total_pagecount
                        )
                    ),
                    "context" => $context,
                    "timestamp" => "$stringTimestampUTC"
                );
                $statements=array($startedStatement, $progressedStatement);
            }
            $postData=json_encode($statements);
            if((boolean)$this->getGlobalSetting('surveylrsendpoint', false)) {
                $endpoint= $this->get('lrs-endpoint', 'Survey', $surveyId);
            } else {
                $endpoint = $this->getGlobalSetting('lrsEndpoint');
            }
            $url = $endpoint . "/statements";
            $result = $this->httpPost($url, $postData, true);
            $this->debug($url, $statements, $result, $time_start, $comment);
            return;
        }

        private function getLastResponse($surveyId, $token)
        {
            $responseTable = $this->api->getResponseTable($surveyId);
            $query = "SELECT * FROM {$responseTable} WHERE token = '$token' ORDER BY submitdate DESC LIMIT 1";
            $rawResult = Yii::app()->db->createCommand($query)->queryRow();
            $result = $rawResult;
            return $result;
        }

        private function uuidv4() {
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }

        /***** ***** ***** ***** *****
        * httpPost function http://hayageek.com/php-curl-post-get/
        * creates and executes a POST request
        * returns the output
        ***** ***** ***** ***** *****/
        private function httpPost($url, $postData, $authorization, $contentType="application/json")
        {
            $bug = $this->getGlobalSetting('sBug');
            $this->customLog('HTTP call started');
            if (empty($url)) {
                $this->customLog('HTTP call failed: No URL defined!');
                return; // No URL defined
            }

            // Validate URL
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $this->customLog('Invalid URL: ' . $url);
                return; // Exit if the URL is not valid
            }

            // Initialize cURL session
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $headers=[
                "Content-Type: " . $contentType,
                "Content-Length: " . strlen($postData), // Helps some servers parse JSON correctly
            ];
            if($authorization) {
                array_push($headers, "Authorization: " . $this->loginViaOAuth());
            }
            
            $this->customLog(implode(" , ", $headers));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $output = curl_exec($ch);

            // Handle errors (optional)
            if (curl_errno($ch)) {
                $this->customLog('HTTP call failed: ' . curl_error($ch));
            }
            curl_close($ch);

            //if($authorization) {
            //    $this->logoutViaOAuth();
            //}

            return $output;
        }

        /***** ***** ***** ***** *****
        * debugging
        ***** ***** ***** ***** *****/
        private function debug($url, $parameters, $response, $time_start, $comment)
        {
            $bug=(boolean)$this->getGlobalSetting('sBug');
            if ($bug)
              {
                $this->customLog($comment . " | Url sent : ". $url . " | Params: ". json_encode($parameters) . " | Response received : " . json_encode($response));
                $html = '<pre><br><br>----------------------------- DEBUG ----------------------------- <br><br>';
                $html .= 'Comment: <br>' . print_r($comment, true);
                $html .= '<br><br>Parameters: <br>' . print_r($parameters, true);
                $html .= '<br><br>Response: <br>' . print_r($response, true);
                $html .= "<br><br> ----------------------------- <br><br>";
                $html .= 'HTTP sent to: ' . print_r($url, true) . '<br>';
                $html .= 'Total execution time in seconds: ' . (microtime(true) - $time_start);
                $html .= '</pre>';
                $event = $this->getEvent();
                $event->getContent($this)->addContent($html);
              }
		}

        /**
         * get settings according to current DB and fixed config.php
         * @param string $setting
         * @param mixed $default
         * @return mixed
         */
        private function getGlobalSetting($setting, $default = null)
        {
            $WebhookSettings = App()->getConfig('XAPITrackerSettings');
            if (isset($WebhookSettings['fixed'][$setting])) {
                return $WebhookSettings['fixed'][$setting];
            }
            if (isset($WebhookSettings[$setting])) {
                return $this->get($setting, null, null, $WebhookSettings[$setting]);
            }
            return $this->get($setting, null, null, $default);
        }

        /**
         * Get the fixed settings name
         * @return string[]
         */
        private function getFixedGlobalSetting()
        {
            $WebhookSettings = App()->getConfig('XAPITrackerSettings');
            if (isset($WebhookSettings['fixed'])) {
                return array_keys($WebhookSettings['fixed']);
            }
            return [];
        }

        /**
         * Get the hidden settings name
         * @return string[]
         */
        private function getHiddenGlobalSetting()
        {
            $WebhookSettings = App()->getConfig('XAPITrackerSettings');
            if (isset($WebhookSettings['hidden'])) {
                return $WebhookSettings['hidden'];
            }
            return [];
        }

        private function customLog($message) {
            $bug=(boolean)$this->getGlobalSetting('sBug');
            if ($bug) {
                error_log("[XAPITracker] " . $message);
            }
        }
    }
