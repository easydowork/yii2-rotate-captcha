<?php
use yii\helpers\Html;

/** @var $options array */
/** @var $name string */
/** @var $value string */

?>
<div class="captcha-shade hide"></div>
<?=Html::hiddenInput($name,$value,$options);?>
<div class="captcha-box"></div>