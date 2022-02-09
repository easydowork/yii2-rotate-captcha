<?php
namespace easydowork\rotateCaptcha;

use yii\base\Exception;

class CaptchaException extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'CaptchaException';
    }
}
