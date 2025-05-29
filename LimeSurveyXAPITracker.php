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
            $this->subscribe('beforeControllerAction');
            $this->subscribe('beforeSurveySettings');
            $this->subscribe('newSurveySettings');
            $this->subscribe('afterSurveyComplete');
            $this->subscribe('beforeSurveyPage');
            $this->subscribe('afterResponseSave');
        }

        public function beforeControllerAction()
        {
            $route = Yii::app()->getRequest()->getPathInfo();
            $pluginName=self::$name;
            if ($route === "api/plugin/$pluginName/settings/update") {
                $this->runUpdate();
                Yii::app()->end();
            }
        }

        protected function runUpdate()
        {
            $req    = Yii::app()->getRequest();
            $token  = $req->getPost('api_token');
            $settings = $req->getPost('settings');      // array of key=>value
            $surveyId = (int)$req->getPost('survey_id'); // ← optional

            if ($token !== $this->getGlobalSetting('sAuthToken')) {
                header('HTTP/1.1 401 Unauthorized');
                echo json_encode(['error'=>'invalid token']);
                return;
            }
            if (!is_array($settings)) {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(['error'=>'missing settings']);
                return;
            }

            /** @var \LimeSurvey\PluginManager $pm */
            $pm = Yii::app()->getPluginManager();

            if ($surveyId > 0) {
                // This is the magic call:
                //   3rd parameter = survey ID tells LS you want survey-scope settings
                $pm->updatePluginSettings($this->name, $settings, $surveyId);
            } else {
                // fallback to global settings
                $pm->updatePluginSettings($this->name, $settings);
            }

            echo json_encode(['result'=>'ok']);
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
            if((boolean)$this->getGlobalSetting('studylrsendpointws', false)) {
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
                    'default' => '000000',
                    'label' => 'The ID of the surveys:',
                    'default' => $this->getGlobalSetting('sId', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('sId', $fixedPluginSettings)
                    ],
                    'help' => 'The unique number of the surveys. You can set multiple surveys with an "," as separator. Example: 123456, 234567, 345678. Let empty to treat all'
                ),
                'studylrsendpoint' => array(
                    'type' => 'checkbox',
                    'default' => $this->getGlobalSetting('studylrsendpoint', false),
                    'htmlOptions' => [
                        'readonly' => in_array('studylrsendpoint', $fixedPluginSettings)
                    ],
                    'label' => 'Enable Study LRS Endpoint Mode',
                    'help' => 'Enable Study LRS Endpoint Mode.'
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
                    'type' => 'checkbox',
                    'label' => 'oAuth Type',
                    'default' => $this->getGlobalSetting('oAuthType', ''),
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
                'sToken' => array(
                    'type' => 'string',
                    'label' => 'API Token',
                    'default' => $this->getGlobalSetting('sToken', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('sToken', $fixedPluginSettings)
                    ],
                    'help' => 'Maybe you need a token to verify updated settings?'
                ),
                'sHeaderSignatureName' => array(
                    'type' => 'string',
                    'label' => 'Header Signature Name',
                    'default' => $this->getGlobalSetting('sHeaderSignatureName', 'X-Signature-SHA256'),
                    'htmlOptions' => [
                        'readonly' => in_array('sHeaderSignatureName', $fixedPluginSettings)
                    ],
                    'help' => 'Header Signature Name. Default to X-Signature-SHA256.'
                ),
                'sHeaderSignaturePrefix' => array(
                    'type' => 'string',
                    'label' => 'Header Signature Prefix',
                    'default' => $this->getGlobalSetting('sHeaderSignaturePrefix', ''),
                    'htmlOptions' => [
                        'readonly' => in_array('sHeaderSignaturePrefix', $fixedPluginSettings)
                    ],
                    'help' => 'Header Signature Prefix'
                ),
                'sBug' => array(
                    'type' => 'checkbox',
                    'default' => $this->getGlobalSetting('sBug', false),
                    'htmlOptions' => [
                        'readonly' => in_array('sBug', $fixedPluginSettings)
                    ],
                    'label' => 'Enable Debug Mode',
                    'help' => 'Enable debugmode to see what data is transmitted. Respondents will see this as well so you should turn this off for live surveys'
                ),
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
            $i=1;
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
                $i++;
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
            
            // Try to fetch the current from the URL manually or default language
            $surveyInfo = Survey::model()->findByPk($surveyId);
            $languageRequest=Yii::app()->request->getParam('lang', null);
            $lang = $languageRequest !== null ? $languageRequest : $surveyInfo->language; // Fallback to default language

            // Get token from the URL manually
            $token=Yii::app()->request->getParam('token', null);
            $registrationId="";
            $actor=array(
                "account" => 
                    array(
                        "name" => $token,
                        "homepage" => $this->getGlobalSetting('actorhomepage')
                    )
            );
            $context=array(
                "contextActivities"=> array(
                    "category"=> array(
                        array("id"=>"https://w3id.org/xapi/seriousgame")
                    ),
                "registration"=>$registrationId
                ),
            );
            $surveyObject=array(
                "id" => $surveyId,
                "definition" => array(
                    "type" => "https://w3id.org/xapi/seriousgames/activity-types/serious-game"
                )
            );
            $stringTimestampUTC=gmdate('Y-m-d\TH:i:s\Z', $time_start);
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
                    "actor" => $actor,
                    "verb" => array("id" => "http://adlnet.gov/expapi/verbs/completed"),
                    "result" => array("" => "1", "completion" => "1"),
                    "object" => $surveyObject,
                    "context" => $context,
                    "timestamp" => $stringTimestampUTC
                );
                
                $details=array($progressedStatement, $completedStatement);
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
                                $title=$multiTitle[$question["parent_qid"]]["title"] . "[" . $title . "]";
                                $this->customLog($title);
                                if(array_key_exists($title,$fullResponse)) {
                                    $response=$fullResponse[$title];
                                    $this->customLog($response);
                                } else {
                                    $this->customLog($title .  "not found in response!");
                                }
                            } else {
                                if(array_key_exists($title,$fullResponse)) {
                                    $response=$fullResponse[$title];
                                    $this->customLog($response);
                                } else {
                                    $this->customLog($title .  "not found in response!");
                                }
                            }
                        } else {
                            $title=$question["title"];
                            if(array_key_exists($title,$fullResponse)) {
                                $response=$fullResponse[$title];
                                $this->customLog($response);
                            } else {
                                $this->customLog($title .  " not found in response!");
                            }
                        }
                        if($response !== "") {
                            $statement = array(
                                "actor" => $actor,
                                "verb" => array("id"=> "https://w3id.org/xapi/adb/verbs/selected"),
                                "object" => array(
                                    "id" => "$surveyId/$title",
                                    "definition" => array(
                                        "type" => "http://adlnet.gov/expapi/activities/question"
                                    ),
                                    "display"=> array(
                                        $lang => $question["question"],
                                    )
                                ),
                                "result" => array(
                                    "response" => $response
                                ),
                                "context" => $context,
                                "timestamp" => $stringTimestampUTC
                            );
                            array_push($ResponsesStatement,$statement);
                        }
                    }
                    if($lastpage === $total_pagecount) {
                        $details=$ResponsesStatement;
                    } else {
                        $res=$lastpage/$total_pagecount;
                        $progressedStatement = array(
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
                        $details=$ResponsesStatement;
                    }
                    
                    // Step 3: Release the session key
                    $this->release_LRC();
                } catch(e) {
                    $this->customLog(e);
                }
            } else {
                $sartedStatement = array(
                    "actor" => $actor,
                    "verb" => array("id"=> "http://adlnet.gov/expapi/verbs/initialized"),
                    "object" => array(
                        "id" => $surveyId,
                        "definition" => array(
                            "type" => "https://w3id.org/xapi/seriousgames/activity-types/serious-game"
                        )
                    ),
                    "context" => $context,
                    "timestamp" => "$stringTimestampUTC"
                );
                $progressedStatement=array(
                    "actor" => $actor,
                    "object" => array(
                        "id" => $surveyId,
                        "definition" => array(
                            "type" => "https://w3id.org/xapi/seriousgames/activity-types/serious-game"
                        )
                    ),
                    "verb" => array("id" => "http://adlnet.gov/expapi/verbs/progressed"),
                    "result" => array(
                        "extensions" => array(
                            "https://w3id.org/xapi/seriousgames/extensions/progress" => 0
                        )
                    ),
                    "context" => $context,
                    "timestamp" => "$stringTimestampUTC"
                );
                $details=array($sartedStatement, $progressedStatement);
            }
            $parameters=array("statements" => $details);
            $postData=json_encode($parameters);
            $url = $this->getGlobalSetting('sUrl');
            $resultSent = $this->httpPost($url, $postData);
            $this->debug($url, $parameters, $resultSent, $time_start, $comment);
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

        /***** ***** ***** ***** *****
        * httpPost function http://hayageek.com/php-curl-post-get/
        * creates and executes a POST request
        * returns the output
        ***** ***** ***** ***** *****/
        private function httpPost($url, $postData)
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
            $signingSecret = $this->getGlobalSetting('sAuthToken', '');
            $authToken=$signingSecret;
            $headers=[
                "Content-Type: application/json",
                "Content-Length: " . strlen($postData), // Helps some servers parse JSON correctly
                "Authorization: Bearer " . $authToken
            ];
            
            if($bug) {
                $this->customLog(implode(" , ", $headers));
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $output = curl_exec($ch);
            
            // Handle errors (optional)
            if (curl_errno($ch)) {
                $this->customLog('HTTP call failed: ' . curl_error($ch));
            }+
            curl_close($ch);
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
