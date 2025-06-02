<?php

/***** ***** ***** ***** *****
* Send a curl post request after each afterSurveyComplete event
*
* @originalauthor Stefan Verweij <stefan@evently.nl>
* @copyright 2016 Evently <https://www.evently.nl>
  @author IrishWolf
* @copyright 2023 Nerds Go Casual e.V.
* @license GPL v3
* @version 1.0.0
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
            $this->customLog($response);
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
                'long', // sResponseType Options: 'short', 'long'
            ]);
        
            if (isset($response['result'])) {
                $responsesJson = base64_decode($response['result']);
                $responses =json_decode($responsesJson, true)['responses'];
                $this->customLog("Survey Responses: " . json_encode($responses));
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
                $this->customLog(json_encode($group));
                $this->customLog($gid . " | " . $groupName . " Last page : ". $lastpage);
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
        }

        function loginViaOAuth() {
            $oauthType = $this->getGlobalSetting('oAuthType','');
            $this->customLog($oauthType);
            $username=$this->getGlobalSetting('usernameOAuth');
            $password=$this->getGlobalSetting('passwordOAuth');
            if($oauthType == "oauth2") {
                $tokenEndpoint=$this->getGlobalSetting('OAuth2TokenEndpoint');
                $clientId=$this->getGlobalSetting('OAuth2ClientId');
                $expire_at=$this->get("expire_at", null, null, "");
                if($expire_at != "") {
                    $access_token=$this->get("access_token", null, null, "");
                    $time_start=microtime(true);
                    $this->customLog("Expire at : " . $expire_at . " start : " . $time_start);
                    if((int)$time_start > (int)$expire_at) {
                        if((int)$time_start > (int)$refresh_expires_at) {
                            $access_token=$this->authOAuth2ViaUserAndPassword($clientId, $username, $password);
                        } else {
                            $refresh_token=$this->get("refresh_token", null, null, "");
                            $authParams = array(
                                "grant_type" => "refresh_token",
                                "client_id" => $clientId,
                                "refresh_token" => $refresh_token,
                            );
                            $this->customLog($tokenEndpoint . "Params : " . http_build_query($authParams));
                            $res = $this->httpPost($tokenEndpoint, http_build_query($authParams), false, "application/x-www-form-urlencoded");
                            $this->customLog($res);
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
            $this->customLog("Type : " . $oauthType . " | Auth : " . $auth);
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
            $this->customLog($tokenEndpoint . "Params : " . http_build_query($authParams));
            $res = $this->httpPost($tokenEndpoint, http_build_query($authParams), false, "application/x-www-form-urlencoded");
            $this->customLog($res);
            $time_start=microtime(true);
            $decoded=json_decode($res, true);
            $timestamp= (int)$time_start + (int)$decoded["expires_in"];
            $refreshtimestamp= (int)$time_start + (int)$decoded["refresh_expires_in"];
            $this->set("expire_at", $timestamp);
            $this->set("refresh_expires_at", $refreshtimestamp);
            $this->set("refresh_token", $decoded["refresh_token"]);
            $access_token=$decoded["access_token"];
            $this->set("access_token", $access_token);
            return $access_token;
        }

        function logoutViaOAuth() {
            $oauthType = $this->getGlobalSetting('oAuthType','');
            $this->customLog($oauthType);
            if($oauthType == "oauth2") {
                $logoutEndpoint=$this->getGlobalSetting('OAuth2LogoutEndpoint');
                $clientId=$this->getGlobalSetting('OAuth2ClientId');
                $authParams = array(
                    "grant_type" => "refresh_token",
                    "client_id" => $clientId,
                    "refresh_token" => $this->get("refresh_token", null, null, ""),
                );
                $this->customLog($logoutEndpoint . "Params : " . http_build_query($authParams));
                $res = $this->httpPost($logoutEndpoint, http_build_query($authParams), false, "application/x-www-form-urlencoded");
                $this->customLog("Res : " . $res);
                $decoded=json_decode($res, true);
                $this->customLog("Decoded : " . $decoded);
                $this->set("refresh_token", null);
                $this->set("access_token", null);
                $this->set("refresh_expires_at", null);
                $this->set("expire_at", null);
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
            
            if (!$hookSurveyId == '') {
                if(!in_array($surveyId, $hookSurveyIdArray)) {
                    return;
                }
            }

            if($bug) {
                $this->customLog($comment . " : " . $surveyId);
            }
            
            $surveyUrl = Yii::app()->getController()->createAbsoluteUrl('survey/index', array('sid' => $surveyId)); // Adjust 'lang' as needed.
            $this->customLog($surveyUrl);

            // Try to fetch the current from the URL manually or default language
            $surveyInfo = Survey::model()->findByPk($surveyId);
            $languageRequest=Yii::app()->request->getParam('lang', null);
            $lang = $languageRequest !== null ? $languageRequest : $surveyInfo->language; // Fallback to default language

            // Get token from the URL manually
            $token=Yii::app()->request->getParam('token', null);
            $registrationIdKey="registration_" . $token ;
            $registrationId=$this->get($registrationIdKey, 'Survey', $surveyId);
            if ($comment === 'afterSurveyComplete') {
                if($registrationId == null) {
                    error_log($registrationIdKey . "not found"); 
                }
                $this->customLog("Found " . $registrationIdKey . "set to " . $registrationId);
                $this->customLog("Unset " . $registrationIdKey);
                $this->set($registrationIdKey, null, "Survey", $surveyId);
            } else if($comment === 'afterResponseSave') {
                if($registrationId == null) {
                    error_log($registrationIdKey . "not found"); 
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
                        "homePage" => $this->getGlobalSetting('actorhomepage')
                    )
            );
            $context=array(
                "contextActivities"=> array(
                    "category"=> array(
                        array("id"=>"https://w3id.org/xapi/seriousgame")
                    ),
                ),
                "registration"=>$registrationId,
            );
            $surveyObject=array(
                "id" => $surveyUrl,
                "definition" => array(
                    "type" => "https://w3id.org/xapi/seriousgames/activity-types/serious-game"
                )
            );
            $stringTimestampUTC=gmdate('Y-m-d\TH:i:s\Z', (int)$time_start);
            // Access the API
            $api = $this->pluginManager->getAPI();
            // Include response data only for completion
            if ($comment === 'afterSurveyComplete') {
                $responseId = $event->get('responseId');
                // Fetch response data manually from the survey table
                $response = $api->getResponse($surveyId, $responseId);
                #$timestamp="";
                #if(is_array($response) && isset($response['submitdate'])) {
                #    $timestamp = $response['submitdate'];    
                #}
                $progressedStatement=array(
                    "id"=>$this->uuidv4(),
                    "actor" => $actor,
                    "object" => $surveyObject,
                    "verb" => array("id" => "http://adlnet.gov/expapi/verbs/progressed"),
                    "result" => array(
                        "extensions" => array(
                            "https://w3id.org/xapi/seriousgames/extensions/progress" => 1
                        )
                    ),
                    "context" => $context,
                    "timestamp" => $stringTimestampUTC
                );
                $completedStatement=array(
                    "id"=>$this->uuidv4(),
                    "actor" => $actor,
                    "verb" => array("id" => "http://adlnet.gov/expapi/verbs/completed"),
                    "result" => array("success" => true, "completion" => true),
                    "object" => $surveyObject,
                    "context" => $context,
                    "timestamp" => $stringTimestampUTC
                );
                $statements=array($progressedStatement, $completedStatement);
            } else if($comment === 'afterResponseSave') {
                // Get the responses for the survey with the specified condition
                $responses = $this->getLastResponse($surveyId, $token);
                $lastpage=(int)$responses["lastpage"];
                $timestamp=$responses["datestamp"];
                try {
                    $groups = QuestionGroup::model()->findAllByAttributes([
                        'sid' => $surveyId
                    ]);
                    // Count them
                    $page_count = count($groups);
                    $this->customLog("total_pagecount : $page_count");
                    $total_pagecount=(int)$page_count;
                    // Step 1: Get a session key
                    $this->auth_LRC();
                    // Step 2: Export responses
                    $fullResponse=$this->exportFullResponseLRC($surveyId, $lang, $token);
                    $questions=$this->exportQuestionGroupSurveyLRC($surveyId, $lang, $lastpage);
                    $ResponsesStatement=array();
                    $isMulti=false;
                    $multiTitles=[];
                    foreach($questions as $question) {
                        $response="";
                        if($question["question_theme_name"] === "arrays/array") {
                            $isMulti=true;
                            $multiTitle[$question["id"]]=$question;
                            $this->customLog($question["id"] . " is multi.");
                        } elseif($isMulti) {
                            $title=$question["title"];
                            if(array_key_exists($question["parent_qid"], $multiTitle)) {
                                $tmpTitle=$title;
                                $foundMultiTitle=$multiTitle[$question["parent_qid"]]["title"];
                                $title=$foundMultiTitle . "[" . $tmpTitle . "]";
                                $titleUrl = $foundMultiTitle . "/" . $tmpTitle;
                                $this->customLog($title);
                                if(array_key_exists($title,$fullResponse)) {
                                    $response=$fullResponse[$title];
                                    $this->customLog($response);
                                } else {
                                    $this->customLog($title .  "not found in response!");
                                }
                            } else {
                                $titleUrl=$title;
                                if(array_key_exists($title,$fullResponse)) {
                                    $response=$fullResponse[$title];
                                    $this->customLog($response);
                                } else {
                                    $this->customLog($title .  "not found in response!");
                                }
                            }
                        } else {
                            $title=$question["title"];
                            $titleUrl=$title;
                            if(array_key_exists($title,$fullResponse)) {
                                $response=$fullResponse[$title];
                                $this->customLog($response);
                            } else {
                                $this->customLog($title .  " not found in response!");
                            }
                        }
                        if($response !== "") {
                            $statement = array(
                                "id"=>$this->uuidv4(),
                                "actor" => $actor,
                                "verb" => array("id"=> "https://w3id.org/xapi/adb/verbs/selected"),
                                "object" => array(
                                    "id" => "$surveyUrl/$titleUrl",
                                    "definition" => array(
                                        "name"=> array(
                                            $lang => $title,
                                        ),
                                        "description"=> array(
                                            $lang => $question["question"],
                                        ),
                                        "type" => "http://adlnet.gov/expapi/activities/question"
                                    ),
                                    
                                ),
                                "result" => array(
                                    "response" => "$response"
                                ),
                                "context" => $context,
                                "timestamp" => "$stringTimestampUTC"
                            );
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
                                "extensions" => array(
                                    "https://w3id.org/xapi/seriousgames/extensions/progress" => "$res"
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
                        "extensions" => array(
                            "https://w3id.org/xapi/seriousgames/extensions/progress" => "0"
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
            
            if($bug) {
                $this->customLog(implode(" , ", $headers));
            }
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
