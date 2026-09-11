<?php

/**
 * This file is part of reloadAnyResponse plugin
 * @version 5.11.0
 */

namespace reloadAnyResponse\models;

use Yii;
use CActiveRecord;
use Response;
use Survey;
use reloadAnyResponse\Utilities;

class surveySession extends CActiveRecord
{
    /**
     * Class reloadAnyResponse\models\surveySession
     *
     * @property integer $sid survey
     * @property integer $srid : response id
     * @property string $token : optionnal token
     * @property string $session : the curent session id
     * @property datetime $lastaction : the last action done
     * @property string $pageid : the curent page id
     */

    /* @const integer The max time for session for a specific survey, if it's not set in session or by server (0 in session_time_limit) */
    const maxSessionTime = 30;

    /* @const integer The max time for session for ALL surveys, in hour, can be replaced by App->setConfig('surveySessionMaxGlobalTime'); */
    const MAXGLOBALTIME = 24;

    /** @inheritdoc */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * init to set default
     *
     */
    public function init()
    {
        $this->sid = 0;
        $this->srid = 0;
        $this->token = "";
        $this->session = "";
        $this->lastaction = null;
        $this->pageid = null;
    }

    /** @inheritdoc */
    public function tableName()
    {
        return '{{reloadanyresponse_surveySession}}';
    }

    /** @inheritdoc */
    public function primaryKey()
    {
        return array('sid', 'srid');
    }

    /**
     * Return (or create) self
     * @param integer $sid survey
     * @param null|integer $srid response id, if not set : find current session one, return if not
     * @param null|string $token : optionnal token, if set : always reset
     * @param null|string $pageid : optionnal page identifier
     * @return self|null
     */
    public static function saveSessionTime($sid, $srid = null, $token = null, $pageId = null)
    {
        if (!$srid) {
            $srid = Utilities::getCurrentSrid($sid);
        }
        if (!$srid && !$token) {
            return;
        }
        if (self::surveyHasTokenTable($sid)) {
            if (!$token) {
                $token = Utilities::getCurrentToken($sid);
            }
            if (!$token && Survey::model()->findByPk($sid)->anonymized != "N") {
                $oResponse = Response::model($sid)->findByPk($srid);
                if ($oResponse && !empty($oResponse->token)) {
                    $token = $oResponse->token;
                }
            }
        }
        $oSessionSurvey = self::model()->find("sid = :sid and srid = :srid", array(':sid' => $sid, ':srid' => $srid));
        if (!$oSessionSurvey) {
            $oSessionSurvey = new self();
            $oSessionSurvey->sid = $sid;
            $oSessionSurvey->srid = $srid;
            $oSessionSurvey->pageid = $pageId;
        }
        if ($token) {
            $oSessionSurvey->token = $token;
        }
        $oSessionSurvey->lastaction = date('Y-m-d H:i:s');
        $oSessionSurvey->session = self::getSessionId();
        if (!$oSessionSurvey->save()) {
            Yii::log("Error in saveSessionTime for $srid on $sid ($token) by " . self::getUserAgent() . " with " . self::getSessionId(), \CLogger::LEVEL_INFO, 'reloadAnyResponse.models.surveySession.saveSessionTime');
            return;
        }
        Yii::log("saveSessionTime for $srid on $sid ($token) by " . self::getUserAgent() . " with " . self::getSessionId(), \CLogger::LEVEL_INFO, 'reloadAnyResponse.models.surveySession.saveSessionTime');
        return $oSessionSurvey;
    }

    /**
     * Find if access is OK, delete old access for this srid
     * @param integer $sid survey
     * @param integer $srid response id, if not set : find current session one, return if not
     * @param boolean $save save current if not exist
     * @param string $pageId pseudo unique id from current page
     * @return float|null : time (in minutes) since last action is done
     */
    public static function getIsUsed($sid, $srid = null, $save = true, $pageId = null)
    {
        if (!$srid) {
            $srid = Utilities::getCurrentSrid($sid);
        }
        if (!$srid) {
            return;
        }
        $delay = self::getSessionTimeLimit();
        if ($delay === "0") {
            return null;
        }
        $surveySessionMaxGlobalTime = self::MAXGLOBALTIME;
        if (intval(App()->getConfig('surveySessionMaxGlobalTime')) > 0) {
            $surveySessionMaxGlobalTime = intval(App()->getConfig('surveySessionMaxGlobalTime'));
        }
        $maxGlobalDateTime = date('Y-m-d H:i:s', strtotime("{$delay} hours ago"));
        self::model()->deleteAll(
            "lastaction < :lastaction",
            array(":lastaction" => $maxGlobalDateTime)
        );
        $deleteDelay = $delay + 1;
        $maxDateTime = date('Y-m-d H:i:s', strtotime("{$deleteDelay} minutes ago"));
        self::model()->deleteAll(
            "sid = :sid and lastaction < :lastaction",
            array(":sid" => $sid, ":lastaction" => $maxDateTime)
        );
        $oSessionSurvey = self::model()->findByPk(array('sid' => $sid, 'srid' => $srid));
        /* No current session save it and can quit */
        if (!$oSessionSurvey) {
            if ($save) {
                $token = Yii::app()->getRequest()->getParam('token');
                self::saveSessionTime($sid, $srid, $token, $pageId);
            }
            return null;
        }
        /* Same session : save time and quit */
        if ($oSessionSurvey->session == self::getSessionId()) {
            $oSessionSurvey->lastaction = date('Y-m-d H:i:s');
            $oSessionSurvey->pageid = $pageId;
            $oSessionSurvey->save();
            return null;
        }
        /* In previous sessions : save time and quit */
        $previousSessionId = (array) Yii::app()->session['previousSessionId'];
        if (in_array($oSessionSurvey->session, $previousSessionId)) {
            $oSessionSurvey->session = self::getSessionId();
            $oSessionSurvey->lastaction = date('Y-m-d H:i:s');
            $oSessionSurvey->pageid = $pageId;
            $oSessionSurvey->save();
            return null;
        }
        $token = Yii::app()->getRequest()->getParam('token');
        Yii::log("Session is used for $srid on $sid ($token) by " . self::getUserAgent() . " with " . self::getSessionId(), \CLogger::LEVEL_INFO, 'reloadAnyResponse.models.surveySession.saveSessionTime');
        $lastaction = strtotime($oSessionSurvey->lastaction);
        $now = strtotime("now");
        $sinceTime = abs($lastaction - $now) / 60;
        return $sinceTime;
    }

    /**
     * Get session time limit
     */
    private static function getSessionTimeLimit()
    {
        $sessionTimeLimit = intval(App()->getConfig('surveysessiontime_limit', self::maxSessionTime));
        return $sessionTimeLimit;
    }

    /**
     * try to keep previous session in session …
     * @deprecated
     * @param string $sessionId, ifb not set : current one
     * @return void
     */
    public static function addCurrrentSessionInPrevious($sessionId = null)
    {
        // Deprecated function
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $aRules = array(
            array('sid', 'required'),
            array('srid', 'required'),
            array('sid,srid', 'numerical', 'integerOnly' => true),
            array(
                'srid',
                'unique',
                'criteria' => array(
                    'condition' => 'sid=:sid',
                    'params' => array(':sid' => $this->sid)
                ),
                'message' => sprintf("Srid : '%s' already set for '%s'.", $this->srid, $this->sid),
            ),
        );
        return $aRules;
    }

    /**
     * Generate an random unique id for the session
     * @return string
     */
    public static function getSessionId()
    {
        if (empty(Yii::app()->session['reloadAnyResponseSessionId'])) {
            Yii::app()->session['reloadAnyResponseSessionId'] = uniqid(Yii::app()->getSecurityManager()->generateRandomString('42'), true);
        }
        return Yii::app()->session['reloadAnyResponseSessionId'];
    }

    /**
     * Delete all current survey
     * @return void
     */
    public static function deleteAllBySessionId()
    {
        if (empty(self::getSessionId())) {
            return;
        }
        self::model()->deleteAll("session = :session", array(':session' => self::getSessionId()));
    }

    /**
     * Replace getHasTokensTable for 2.X compat
     * @param $surveyId
     * @return boolean
     */
    private static function surveyHasTokenTable($surveyId)
    {
        if (intval(App()->getConfig('versionnumber')) >= 3) {
            return Survey::model()->findByPk($surveyId)->getHasTokensTable();
        }
        Yii::import('application.helpers.common_helper', true);
        return tableExists("{{tokens_" . $surveyId . "}}");
    }

    /**
     * Get the current user agent
     * Used for login
     * @return string
     */
    private static function getUserAgent()
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return "no HTTP_USER_AGENT";
        }
        return $_SERVER['HTTP_USER_AGENT'];
    }
}
