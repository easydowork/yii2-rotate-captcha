<?php

namespace easydowork\rotateCaptcha;

use Yii;
use yii\base\InvalidConfigException;
use yii\validators\Validator;

class CaptchaValidator extends Validator
{
    /**
     * @var bool whether to skip this validator if the input is empty.
     */
    public $skipOnEmpty = false;

    /**
     * @var string the route of the controller action that validator captcha value.
     */
    public $captchaAction = 'site/captcha';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yii::t('yii', 'The verification code is incorrect.');
        }
    }

    /**
     * validateValue
     * @param mixed $value
     * @return array|null
     * @throws InvalidConfigException
     */
    protected function validateValue($value)
    {
        $captcha = $this->createCaptchaAction();
        $valid = !is_array($value) && $captcha->validateValue($value);
        return $valid ? null : [$this->message, []];
    }

    /**
     * createCaptchaAction
     * @return CaptchaAction
     * @throws InvalidConfigException
     */
    public function createCaptchaAction()
    {
        $ca = Yii::$app->createController($this->captchaAction);
        if ($ca !== false) {
            /* @var $controller \yii\base\Controller */
            list($controller, $actionID) = $ca;
            $action = $controller->createAction($actionID);
            if ($action !== null) {
                return $action;
            }
        }
        throw new InvalidConfigException('Invalid CAPTCHA action ID: ' . $this->captchaAction);
    }

}
