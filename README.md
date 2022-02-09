## yii2图片旋转验证码
- 简介  
基于https://gitee.com/isszz/rotate-captcha 修改的图片旋转验证码.
- 流程说明
  1. 表单提交时js添加`beforeSubmit`事件,判断是有验证码校验值
  2. 弹窗旋转图片验证码,进行检验,若不成功重新旋转图片.
  3. 校验成功后台返回value,赋值给表单的captcha隐藏输入框,js自动提交表单.
- 使用说明  
```php
//与yii\captcha\Captcha类似,配置不同
//例:SiteController中behaviors与actions方法配置
public function behaviors()
{
    return [
        'access' => [
            'class' => AccessControl::className(),
            'rules' => [
                [
                    'actions' => ['captcha'],//允许访问
                    'allow' => true,
                ],
            ],
        ],
        
    ];
}
public function actions()
{
    return [
        'captcha' => [
            'class' => 'easydowork\rotateCaptcha\CaptchaAction',
            'captchaOption' => [
                'imagePath' => '',//验证码图片库目录
                'cacheImagePath' => '',//旋转后验证码缓存目录
                'cacheHandel' => '',//缓存类继承yii\caching\Cache
                'cacheExpire' => 60,//验证码token过期时间
                'salt' => '',//加密字符串参数 默认为当前文件地址
                'size' => 350,//生成图片大小 px
                'earea' => 10,//验证图片时允许旋转角度的误差值
                'imageHandle'=>'gd',//推荐使用gd库,个人测试imagick时在windows和linux上生成的图片不一致,有能力的欢迎pr.
                'imageHandleConfig' => [ //生成验证码的参数
                    'quality' => 80,//图片质量
                    'bgcolor' => '#fff', // 底色
                ],
                'callbackFunc' => [ //一些回调函数 参数可看代码 非必填
                    'cacheKey' => function(){},//token缓存key
                    'cacheValue' => function(){},//token缓存value
                    'check' => function(){},//验证token
                    'generateValue' => function(){},//图片验证码通过后返回的value
                    'validateValue' => function(){},//校验value
                ],
            ]
        ],
    ];
}
//例:LoginForm中rules方法配置
public function rules()
{
    return [
        ['captcha', 'easydowork\rotateCaptcha\CaptchaValidator','captchaAction'=>'site/captcha','message'=>'验证码不正确！'],
    ];
}
//view 视图中使用
<?= $form->field($model,'captcha')->label(false)->widget(\easydowork\rotateCaptcha\CaptchaWidget::class,[
    'id'=>'captcha',
    'name'=>'captcha',
    'captchaOptions' => [
        'theme'                 => '#07f', // 验证码主色调
        'title'                 => '安全验证',
        'desc'                  => '拖动滑块，使图片角度为正',
        'width'                 => 305, // 验证界面的宽度
        'successClose'          => 1500, // 验证成功后页面关闭时间
        'timerProgressBar'      => true, // 验证成功后关闭时是否显示进度条
        'timerProgressBarColor' => '#07f', // 进度条颜色
        'url' => [
            //type参数辨别请求类型
            'create' => Url::to(['captcha', 'type' => 'create']),// 获取验证码信息
            'check'  => Url::to(['captcha', 'type' => 'check']),// 验证
        ],
    ],
]);?>
```
 