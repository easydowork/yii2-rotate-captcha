<?php
declare (strict_types = 1);

namespace easydowork\rotateCaptcha\handle;

use Yii;
use easydowork\rotateCaptcha\CaptchaException;

class GDHandle extends Handle
{

    /**
     * Get image info
     * @return array
     * @throws CaptchaException
     */
	public function getInfo(): array
	{
        $info = getimagesize($this->cacheImage);
		return $this->info = $this->formatImageInfo($info);
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

		$mime = $this->info['mime'];

		if($this->config['outputMime'] != $mime) {
			$mime = $this->config['outputMime'];
		}

		switch($mime) {
			case 'image/jpg':
			case 'image/jpeg':
				imagejpeg($this->back, $this->cacheImage, $this->config['quality'] ?: 80);
				break;
			case 'image/webp':
				imagepalettetotruecolor($this->back);
				imagewebp($this->back, $this->cacheImage, $this->config['quality'] ?: 80);
				break;
			case 'image/png':
				imagepng($this->back, $this->cacheImage);
				break;
			default:
				return false;
		}

		imagedestroy($this->back);

		return true;
	}

	/**
	 * Build rotate image
	 * @return bool
	 */
	public function build(): bool
	{

		if(empty($sizes = $this->calcSize())) {
			return false;
		}

		list($src_w, $src_h, $dst_w, $dst_h, $dst_scale, $src_scale, $w, $h, $x, $y) = $sizes;

		$cropped = imagecreatetruecolor($w, $h);

		$bg = imagecolorallocatealpha($cropped, 255, 255, 255, 127);
		imagefill($cropped, 0, 0, $bg);
		// Keep transparent
		imagesavealpha($cropped, true);
		// imagealphablending($cropped, false);

		// Cut image
		imagecopy($cropped, $this->front, 0, 0, $x, $y, $src_w, $src_h);

		$r = $w / 2;
		$w = imagesx($cropped);
		$h = imagesy($cropped);

		$img = imagecreatetruecolor($w, $h);

		$transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
		imagefill($img, 0, 0, $transparent);
		// Keep transparent
		imagesavealpha($img, true);
		// imagealphablending($img, false);

		for ($x = 0; $x < $w; $x++) {
			for ($y = 0; $y < $h; $y++) {
				$rgbColor = @imagecolorat($cropped, $x, $y);
				if (((($x - $r) * ($x - $r) + ($y - $r) * ($y - $r)) < ($r * $r))) {
					imagesetpixel($img, $x, $y, $rgbColor);
				}
			}
		}

		// Rotate the image
		$circled = imagerotate($img, $this->config['degrees'], imagecolorallocatealpha($img, 255, 255, 255, 127));

		$w1 = imagesx($circled);
		$h1 = imagesy($circled);

		$x = intval(($w1 - $w) / 2);
		$y = intval(($h1 - $h) / 2);

		imagecopy($img, $circled, 0, 0, $x, $y, $w1, $h1);

		// Zoom
		$scale = $dst_w / $w;
		$target = imagecreatetruecolor($dst_w, $dst_h);

		if(!empty($this->config['bgcolor'])) {
			if($this->config['bgcolor'] == '#fff' || $this->config['bgcolor'] == 'white') {
				$this->config['bgcolor'] = '#ffffff';
			}
			// Set background color
			$_color = $this->hex2rgb($this->config['bgcolor'], false);
			if(!$_color || !is_array($_color)) {
				$_color = [255, 255, 255];
			}
			$bgColor = imagecolorallocate($target, ...$_color);
			imagefill($target, 0, 0, $bgColor);
		} else {
			$bgColor = imagecolorallocatealpha($target, 255, 255, 255, 127);
			imagefill($target, 0, 0, $bgColor);
			// Keep transparent
			imagesavealpha($target, true);
			// imagealphablending($target, false);
		}

		$final_w = intval($w * $scale);
		$final_h = intval($h * $scale);
		imagecopyresampled($target, $img, 0, 0, 0, 0, $final_w, $final_h, $w, $h);

		// Destroy image
		imagedestroy($img);
		imagedestroy($cropped);
		imagedestroy($circled);
		imagedestroy($this->front);

		$this->back = $target;

		return true;
	}

	/**
	 * Create image
	 *
	 * @return bool
	 */
	public function createFront(): bool
	{
		switch ($this->info['mime']??'image/png') {
			case 'image/jpeg':
				$this->front = imagecreatefromjpeg($this->image);
				break;
			case 'image/png':
				$this->front = imagecreatefrompng($this->image);
				break;
			case 'image/webp':
				$this->front = imagecreatefromwebp($this->image);
				break;
			default:
				return false;
		}

		return true;
	}

	/**
	 * Format the obtained image information
	 * 
	 * @param array $info
	 * @return array
	 */
	private function formatImageInfo(array $info = [])
	{
		if (!in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'])) {
			throw new CaptchaException(Yii::t('captcha', 'Please use jpeg and png or webp images.'));
		}

		return [
			'width'  => $info[0],
			'height' => $info[1],
			'mime'   => $info['mime'],
			'type'   => image_type_to_extension($info[2], false),
		];
	}
}
