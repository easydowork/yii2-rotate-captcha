<?php

namespace easydowork\rotateCaptcha;

use Yii;
use yii\base\Action;
use yii\helpers\Url;
use yii\web\Response;

class CaptchaAction extends Action
{

    /**
     * @var CaptchaImage
     */
    protected $captchaImage;

    /**
     * @var array
     */
    public $captchaOption = [];

    /**
     * init
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {

        if (!isset(Yii::$app->i18n->translations['captcha'])) {
            Yii::$app->i18n->translations['captcha'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'basePath' => __DIR__.DIRECTORY_SEPARATOR.'messages',
            ];
        }

        $config = $this->captchaOption;

        $config['class'] = CaptchaImage::class;

        $this->captchaImage = Yii::createObject($config);
    }

    /**
     * run
     * @return array|Response
     */
    public function run()
    {

        try {
            $type = Yii::$app->request->getQueryParam('type');

            if($type == 'img'){
                $id = Yii::$app->request->get('id');
                $image = $this->captchaImage->output($id);
                return Yii::$app->response->sendFile($image);
            }else{
                Yii::$app->response->format = Response::FORMAT_JSON;
                if($type == 'create'){
                    $data = $this->captchaImage->create();
                    $data['image'] = Url::to([$this->id,'type'=>'img','id'=>$data['id']]);
                    unset($data['id']);
                }elseif($type == 'check'){
                    if($this->captchaImage->check(Yii::$app->request->headers->get('X-CaptchaToken'),Yii::$app->request->get('angle'))){
                        $data['value'] = $this->captchaImage->generateValue();
                    }else{
                        throw new CaptchaException(Yii::t('captcha', 'verification error.'));
                    }
                }

                return [
                    'code' => 0,
                    'msg'  => 'success',
                    'data' => $data,
                ];
            }
        } catch (\Exception $exception) {
            Yii::error($exception, __METHOD__);
            return [
                'code' => 1,
                'msg'  => YII_DEBUG?$exception->getMessage():'verification error.',
                'data' => YII_DEBUG?[
                    'code'        => $exception->getCode(),
                    'file'        => $exception->getFile(),
                    'line'        => $exception->getLine(),
                    'message'     => $exception->getMessage(),
                    'traceString' => $exception->getTraceAsString(),
                ]:[],
            ];
        }
    }

    /**
     * validateValue
     * @param $value
     * @return bool
     */
    public function validateValue($value)
    {
        return $this->captchaImage->validateValue($value);
    }

}
