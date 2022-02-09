<?php
declare (strict_types = 1);

namespace easydowork\rotateCaptcha\handle;

use Yii;
use \Imagick;
use \ImagickDraw;
use \ImagickPixel;
use easydowork\rotateCaptcha\CaptchaException;

class ImagickHandle extends Handle
{
	/**
	 * Get image info
	 * @return array
	 */
	public function getInfo(): array
	{
        $image = new Imagick($this->cacheImage);
        $info = $image->getImageGeometry();
        $info['mime'] = $image->getimagemimetype();
        $info['type'] = $this->getFileExt();
        $image->clear();
        $image->destroy();
		if (!in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/x-webp'])) {
			throw new CaptchaException(Yii::t('captcha', 'Please use jpeg and png or webp images.'));
		}
		return $this->info = $info;
	}

    /**
     * Get file output extension
     * @param bool   $isIgnoreAfter
     * @return string
     */
    public function getFileExt($isIgnoreAfter = true)
    {
        $ext = pathinfo(($this->cacheImage), PATHINFO_EXTENSION);

        if ($isIgnoreAfter) {
            return '.' . $ext;
        }
        // Output extension
        $ext = $this->getExt();

        if ($ext == 'webp') {
            return '.' . $ext;
        }

        if (!empty($this->config['bgcolor']) && ($ext != 'jpg' || $ext != 'jepg')) {
            $ext = 'jpg';
        }

        return '.' . $ext;
    }

	/**
	 * Save image
	 * @return bool
	 */
	public function save(): bool
	{
		if (!$this->build() || !$this->back) {
			return false;
		}

		$this->back->writeImage($this->cacheImage);

		$this->back->clear();
		$this->back->destroy();
		
		$this->front->clear();
		$this->front->destroy();

		return true;
	}

	/**
	 * Build rotate image
	 * @return bool
	 */
	public function build(): bool
	{
		if(($sizes = $this->calcSize()) && $sizes === false) {
			return false;
		}

		list($src_w, $src_h, $dst_w, $dst_h, $dst_scale, $src_scale, $w, $h, $x, $y) = $sizes;

		// Cut image
		$this->front->thumbnailImage($src_w, $src_h, true);
		$this->front->cropImage($w, $h, $x, $y);

		// Set mask
		$mask = new Imagick();
		$mask->newImage($w, $h, new ImagickPixel('transparent'), 'png');
		// Create the rounded rectangle
		$shape = new ImagickDraw();
		$shape->setFillColor(new ImagickPixel('white'));
		$shape->roundRectangle(0, 0, $w, $h, $w, $w);
		// Draw the rectangle
		$mask->drawImage($shape);

		// Apply mask
		$this->front->setImageMatte(true);
		$this->front->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);

		// Rotate image
		$this->front->rotateImage(new ImagickPixel('none'), $this->config['degrees']);

		// Cut image
		$info = $this->front->getImageGeometry();

		$x = intval(($info['width'] - $w) / 2);
		$y = intval(($info['height'] - $h) / 2);

		$this->front->thumbnailImage($info['width'], $info['height'], true);
		$this->front->cropImage($w, $h, $x, $y);

		// Zoom
		$scale = $dst_w / $w;

		$final_w = intval($w * $scale);
		$final_h = intval($h * $scale);

		$this->front->thumbnailImage($final_w, $final_h, true);
		$this->front->cropimage($w, $h, 0, 0);

		// Delete picture information
		$this->front->stripImage();

		// Jpg default white background
		if($this->config['outputMime'] == 'image/jpeg') {
			$this->config['bgcolor'] = $this->config['bgcolor'] ?: 'white';
		}

		if(empty($this->config['bgcolor'])) {
			$this->back = $this->front;
		} else {
			// Have a background
			$this->back = new Imagick();
			$this->back->newImage($final_w, $final_h, new ImagickPixel($this->config['bgcolor']));
			$this->back->compositeImage($this->front, Imagick::COMPOSITE_OVER, 0, 0);
		}
		
		// Conversion format
		$this->back->setImageFormat($this->config['outputType']);

		if($this->config['outputMime'] == 'image/webp' || $this->config['outputMime'] == 'image/jpeg') {
			$this->back->setImageCompression(Imagick::COMPRESSION_JPEG);
			$this->back->setImageCompressionQuality($this->config['quality'] ?: 80);
		} else {
			// PNG can be compressed by more than 2 times, with an average of about 90kb, the disadvantage is that it loses too many pixels
			// $this->back->setImageType(Imagick::IMGTYPE_PALETTEMATTE);

			// Losslessly compress png, only a few K- -...
			// $this->back->setImageFormat('png');
			$this->back->setImageAlphaChannel(Imagick::COLOR_BLACK);
			$this->back->setImageCompression(Imagick::COMPRESSION_ZIP);
			// $this->back->setImageCompressionQuality(9);
			$this->back->setOption('png:compression-level', 9);
		}

		return true;
	}

	/**
	 * Create image
	 * @return bool
	 */
	public function createFront(): bool
	{
		$this->front = new Imagick($this->image);

		return true;
	}
}
