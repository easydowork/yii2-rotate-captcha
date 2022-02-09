<?php
namespace easydowork\rotateCaptcha;

use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\JsExpression;
use yii\widgets\InputWidget;

class CaptchaWidget extends InputWidget
{

    /**
     * @var array
     */
    public $captchaOptions = [];

    /**
     * @var array
     */
    public $callBack = [];

    /**
     * init
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

    }

    /**
     * run
     * @return string
     */
    public function run()
    {
        CaptchaAssets::register($this->getView());

        $value = $this->hasModel() ? $this->model->{$this->attribute} : $this->value;
        $name = $this->hasModel() ? Html::getInputName($this->model,$this->name) : $this->name;

        $this->renderJs();

        return $this->render('captcha',[
            'name' => $name,
            'value' => $value,
            'options' => $this->options,
        ]);

    }

    /**
     * renderJs
     */
    public function renderJs()
    {
        $defaultCaptchaOptions = [
            'theme'                 => '#07f', // 验证码主色调
            'title'                 => '安全验证',
            'desc'                  => '拖动滑块，使图片角度为正',
            'width'                 => 305, // 验证界面的宽度
            'successClose'          => 1500, // 验证成功后页面关闭时间
            'timerProgressBar'      => true, // 验证成功后关闭时是否显示进度条
            'timerProgressBarColor' => '#07f', // 进度条颜色
            'url' => [
                'create' => '',// 获取验证码信息
                'check'  => '',// 验证
            ],
            'success' => new JsExpression("function(res){captchaInputEle.val(res.data.value);fromEle.submit();}"),
        ];

        $options = Json::encode(array_merge($defaultCaptchaOptions,$this->captchaOptions));

        $js = <<< JS
var captchaInputEle = $('#{$this->options['id']}');
var fromEle = captchaInputEle.closest('form');
fromEle.on('beforeSubmit',function (){
    if(captchaInputEle.val().length === 0){
        new Captcha(document.querySelectorAll('.captcha-box').item(0),$options);
        $('.captcha-shade').removeClass('hide');
        $('.captcha-box').css('z-index',99);
        return false;
    }else{
        return true;
    }
});
JS;
        $this->view->registerJs($js,$this->view::POS_READY);
    }

}