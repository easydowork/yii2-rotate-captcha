<?php
namespace easydowork\rotateCaptcha;

use easydowork\rotateCaptcha\handle\GDHandle;
use easydowork\rotateCaptcha\handle\ImagickHandle;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\caching\Cache;
use yii\helpers\FileHelper;

class CaptchaImage extends Component
{

    const CACHE_KEY = 'rotate_captcha_';

    /**
     * @var string
     */
    public $imagePath = __DIR__.DIRECTORY_SEPARATOR.'images';

    /**
     * @var string
     */
    public $cacheImagePath;

    /**
     * @var Cache
     */
    public $cacheHandel;

    /**
     * @var int
     */
    public $cacheExpire = 60;

    /**
     * @var string
     */
    public $salt = '';

    /**
     * @var int
     */
    public $size = 350;

    /**
     * @var int
     */
    public $earea = 10;

    /**
     * @var string
     */
    public $imageHandle = 'gd';

    /**
     * @var string
     */
    public $imageHandleConfig = [
        'quality' => 80,
        'bgcolor' => '#fff', // 底色
    ];

    /**
     * @var array
     */
    public $callbackFunc = [];

    /**
     * @var int
     */
    protected $degrees;

    /**
     * init
     * @throws InvalidConfigException
     * @throws \yii\base\Exception
     */
    public function init()
    {
        if(!extension_loaded('gd') && !extension_loaded('imagick')) {
            throw new InvalidConfigException(Yii::t('captcha', 'Need to support GD or Imagick extension.'));
        }

        if(!in_array($this->imageHandle,['gd','imagick'])){
            throw new InvalidConfigException(Yii::t('captcha', 'Captcha imageHandle must be gd or imagick.'));
        }

        if(!is_dir($this->imagePath)){
            throw new InvalidConfigException(Yii::t('captcha', 'Captcha imagePath must be dir.'));
        }

        if(!is_readable($this->imagePath)){
            throw new InvalidConfigException(Yii::t('captcha', 'Captcha imagePath is not readable.'));
        }

        if(empty($this->cacheImagePath)){
            $this->cacheImagePath = Yii::getAlias('@webroot/upload/captcha');
        }

        FileHelper::createDirectory($this->cacheImagePath);

        if(empty($this->cacheHandel)){
            $this->cacheHandel = Yii::$app->cache;
        }

        if (!$this->cacheHandel instanceof Cache) {
            throw new InvalidConfigException(Yii::t('captcha', 'Captcha cacheHandel must be set.'));
        }

        if(!is_string($this->salt) || empty($this->salt)){
            $this->salt = md5(__DIR__);
        }

        if($this->size < 150){
            $this->size  = 150;
        }

        $this->degrees = rand(30, 270);

    }

    /**
     * output
     * @param string $str
     * @return string
     */
    public function output(string $str)
    {
        return Yii::$app->security->decryptByKey(base64_decode($str),$this->salt);
    }

    /**
     * create
     * @return array
     * @throws CaptchaException
     * @throws InvalidConfigException|\yii\base\Exception
     */
    public function create()
    {
        $imageList = FileHelper::findFiles($this->imagePath);

        if(empty($imageList)){
            throw new InvalidConfigException(Yii::t('captcha','Captcha imagePath:"{imagePath}" no image file.',['imagePath'=>$this->imagePath]));
        }

        $rand = mt_rand(0, count($imageList) - 1);

        $image = $imageList[$rand];

        $cachePath = $this->cacheImagePath.DIRECTORY_SEPARATOR.$this->degrees;

        FileHelper::createDirectory($cachePath);

        $imageCache = $cachePath.DIRECTORY_SEPARATOR.pathinfo($image)['basename'];

        if(!is_file($imageCache)){
            copy($image,$imageCache);
        }

        $imageHandleConfig = $this->imageHandleConfig;

        $imageHandleConfig['degrees'] = $this->degrees;
        $imageHandleConfig['size'] = $this->size;

        if($this->imageHandle == 'gd'){
            $imageHandle = new GDHandle($image,$imageCache,$imageHandleConfig);
        }else{
            $imageHandle = new ImagickHandle($image,$imageCache,$imageHandleConfig);
        }

        if(!$imageHandle->save()){
            throw new InvalidConfigException(Yii::t('captcha', 'Captcha image save fail.'));
        }

        $cacheKey = $this->getCacheKey();

        $this->cacheHandel->set($cacheKey,$this->getCacheValue(),$this->cacheExpire);

        return [
            'token' => $cacheKey,
            'id' => base64_encode(Yii::$app->security->encryptByKey($imageCache,$this->salt)),
        ];
    }

    /**
     * getCacheKey
     * @param int $length
     * @return string
     */
    protected function getCacheKey($length=32)
    {
        if(!empty($this->callbackFunc['cacheKey'])){
            return call_user_func_array($this->callbackFunc['cacheKey'],[$this,$length]);
        }
        $string = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return self::CACHE_KEY.substr(str_shuffle(str_repeat($string, 5)), 0, $length);
    }

    /**
     * getCacheValue
     * @return array
     */
    protected function getCacheValue()
    {
        if(!empty($this->callbackFunc['cacheValue'])){
            return call_user_func_array($this->callbackFunc['cacheValue'],[$this]);
        }
        return [
            'ds' => $this->degrees,
            'ip' => Yii::$app->request->getUserIP(),
            'ua' => crc32(Yii::$app->request->getUserAgent()),
            'ttl' => time() + $this->cacheExpire,
        ];
    }

    /**
     * check
     * @param      $token
     * @param null $angle
     * @return bool
     * @throws CaptchaException
     */
    public function check($token,$angle=null)
    {
        if(empty($token) || empty($angle)) {
            return false;
        }

        if(!empty($this->callbackFunc['check'])){
            return call_user_func_array($this->callbackFunc['check'],[$this,$token,$angle]);
        }

        $payload = $this->cacheHandel->get($token);

        if(empty($payload)) {
            return false;
        }

        if(!isset($payload['ttl']) || time() > $payload['ttl']) {
            throw new CaptchaException(Yii::t('captcha', 'Verification timed out.'));
        }

        if(!isset($payload['ip']) || Yii::$app->request->getUserIP() !== $payload['ip']) {
            throw new CaptchaException(Yii::t('captcha', 'IP Invalid verification.'));
        }

        $ua = Yii::$app->request->getUserAgent();
        if(!isset($payload['ua']) || crc32($ua) !== $payload['ua']) {
            throw new CaptchaException(Yii::t('captcha', 'UA Invalid verification.'));
        }

        if(!isset($payload['ds'])) {
            throw new CaptchaException(Yii::t('captcha', 'DS Invalid verification.'));
        }

        $angle = (float) $angle;

        $payload['ds'] = (int) $payload['ds'];

        if($angle == $payload['ds']) {
            return true;
        }

        if($angle > $payload['ds'] && $angle - $payload['ds'] < $this->earea) {
            return true;
        }

        if($angle < $payload['ds'] && $payload['ds'] - $angle < $this->earea) {
            $this->cacheHandel->delete($token);
            return true;
        }

        return false;
    }

    /**
     * generateValue
     * @return string
     */
    public function generateValue()
    {
        if(!empty($this->callbackFunc['generateValue'])){
            return call_user_func_array($this->callbackFunc['generateValue'],[$this]);
        }

        return base64_encode(Yii::$app->security->encryptByKey(serialize([
            'ip' => Yii::$app->request->getUserIP(),
            'ua' => crc32(Yii::$app->request->getUserAgent()),
            'ttl' => time() + $this->cacheExpire,
        ]),$this->salt));
    }

    /**
     * validateValue
     * @return bool
     */
    public function validateValue($value)
    {
        if(!empty($this->callbackFunc['validateValue'])){
            return call_user_func_array($this->callbackFunc['validateValue'],[$this,$value]);
        }

        $data = unserialize(Yii::$app->security->decryptByKey(base64_decode($value),$this->salt));

        if(empty($data['ip']) || $data['ip'] != Yii::$app->request->getUserIP()){
            return false;
        }

        if(empty($data['ua']) || $data['ua'] != crc32(Yii::$app->request->getUserAgent())){
            return false;
        }

        if(empty($data['ttl']) || time() > $data['ttl']) {
            return false;
        }

        return true;
    }

}
