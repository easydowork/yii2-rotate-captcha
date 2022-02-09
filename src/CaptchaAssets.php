<?php
namespace easydowork\rotateCaptcha;

use yii\validators\ValidationAsset;

class CaptchaAssets extends \yii\web\AssetBundle
{

    public $depends = [
        ValidationAsset::class,
    ];

    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';
//        $this->css[] = YII_DEBUG?'media.css':'media.min.css';
//        $this->js[] = YII_DEBUG?'media.js':'media.min.js';
        $this->css[] = 'captcha.css';
        $this->js[] = 'jquery.captcha.js';
        parent::init();
    }

}