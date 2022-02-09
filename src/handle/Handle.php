<?php

namespace easydowork\rotateCaptcha\handle;

use Yii;
use easydowork\rotateCaptcha\CaptchaException;

abstract class Handle
{
    protected $info       = [];
    public $image         = null;
    public $back          = null;
    public $front         = null;
    public $cacheImage    = null;

    /**
     * @param string $image
     * @param string $cacheImage
     * @param array  $config
     * @throws CaptchaException
     */
    public function __construct(string $image, string $cacheImage, array $config = [])
    {
        if (empty($cacheImage)) {
            throw new CaptchaException(Yii::t('captcha', 'The path of the cached image cannot be empty.'));
        }

        if (empty($config['degrees']) || $config['degrees'] < 30) {
            throw new CaptchaException(Yii::t('captcha', 'The degrees of rotation cannot be less than 30.'));
        }

        $this->cacheImage = $cacheImage;

        $this->config = $config;

        $this->image = $image;

        $this->config['outputType'] = $this->getExt();

        $this->config['outputMime'] = $this->getMime();

        $this->getInfo();

        $this->createFront();
    }

    public function calcSize()
    {
        if (!$this->info || is_null($this->front)) {
            return false;
        }

        // Minimum size of original image
        $src_min = min($this->info['width'], $this->info['height']);

        if ($src_min < 160) {
            throw new CaptchaException(Yii::t('captcha', 'The image height and width dimensions must be greater than 160px.'));
        }

        if ($src_min < $this->config['size']) {
            $this->config['size'] = $src_min;
        }

        $src_w = $this->info['width'];
        $src_h = $this->info['height'];

        $dst_w = $dst_h = $this->config['size'];

        $dst_scale = $dst_h / $dst_w; // Target image ratio
        $src_scale = $src_h / $src_w; // Original image aspect ratio

        if ($src_scale >= $dst_scale) { // Too high
            $w = intval($src_w);
            $h = $w;
            $x = 0;
            $y = (int)round(($src_h - $h) / 2);
        } else {
            $h = intval($src_h);
            $w = $h;
            $x = (int)round(($src_w - $w) / 2);
            $y = 0;
        }

        return [$src_w, $src_h, $dst_w, $dst_h, $dst_scale, $src_scale, $w, $h, $x, $y];
    }

    /**
     * getExt
     * @return string
     */
    public function getExt()
    {
        switch ($this->config['outputMime'] ?? null) {
            case 'image/png':
                return 'png';
            case 'image/webp':
                return 'webp';
            case 'image/jpg':
            case 'image/jpeg':
                return 'jpg';
        }
        return 'webp';
    }

    /**
     * getMime
     * @return string
     * @throws CaptchaException
     */
    public function getMime(): string
    {
        $outputType = $this->config['outputType'];

        switch ($outputType) {
            case 'png':
                return 'image/png';
            case 'webp':
                return 'image/webp';
            case 'jpeg':
                return 'image/jpeg';
            default:
                throw new CaptchaException(Yii::t('captcha', 'Unsupported outputType: "{outputType}".', ['outputType' => $outputType]));
        }
    }

    /**
     * Hexadecimal color to RGB
     * @param string $color
     * @param bool   $isReturnString
     * @return string|array
     */
    public function hex2rgb($color, $isReturnString = true)
    {
        $hexColor = str_replace('#', '', $color);
        $lens     = strlen($hexColor);

        if ($lens != 3 && $lens != 6) {
            return false;
        }

        $newColor = '';

        if ($lens == 3) {
            for ($i = 0; $i < $lens; $i++) {
                $newColor .= $hexColor[$i] . $hexColor[$i];
            }
        } else {
            $newColor = $hexColor;
        }

        $rgb = [];
        $hex = str_split($newColor, 2);

        foreach ($hex as $key => $vls) {
            $rgb[] = hexdec($vls);
        }

        if ($isReturnString) {
            return implode(', ', $rgb);
        }

        return $rgb;
    }

    abstract public function createFront(): bool;

    abstract public function getInfo(): array;
}
