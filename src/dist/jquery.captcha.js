(function () {
    'use strict';

    const libName = 'captcha';
    const isTouch = 'ontouchstart' in window;

    let index = 0;
    let instances = [];
    let ulit = {};

    let defaults = {
        theme: '#07f',
        title: '安全验证',
        desc: '拖动滑块，使图片角度为正',
        width: 305, // 验证界面的宽度
        successClose: 1500, // 验证成功后页面关闭时间
        timerProgressBar: !1, // 验证成功后关闭时是否显示进度条
        timerProgressBarColor: 'rgba(0, 0, 0, 0.2)',
        path: '',
        url: {
            create: '/captcha/create', // 创建验证码信息
            check: '/captcha/check' // 验证
        },
        init: function (captcha) {}, // 初始化
        success: function (res) {}, // 验证成功
        fail: function (res) {}, // 验证失败
        complete: function (state) {}, // 触发验证, 不管成功与否
        close: function (state) {}, // 关闭验证码窗口
    };

    class Captcha {
        constructor(element, options) {
            const _this = this;
            _this.runtime = {
                deg: 0,
                left: 0,
                state: !1,
                loaded: !1,
            };

            _this.index = index++ || 0;
            _this.options = ulit.extend({}, defaults, options);

            _this.options.id = 'J_rotate_captcha_' + (_this.options.id || _this.index);

            _this.options.size = {img: 152, control: 275};
            _this.options.size.img = parseInt(_this.options.width / 2);
            _this.options.size.control = parseInt(_this.options.width - 30);
            _this.options.size.imgMargin = parseInt(_this.options.width / 10);
            _this.element = element;
            _this.token = '';

            // load css
            _this.insertCss();
            // render template
            _this.render();
        }

        render() {
            const _this = this;

            _this.element.innerHTML = _this.captchaHTML(_this.options);

            _this.options.init(_this);

            _this.$elem = _this.element.querySelectorAll('.captcha-root')[0];
            _this.$main = _this.$elem.querySelectorAll('.captcha-main')[0];

            _this.$captchaImgWrap = _this.$elem.querySelectorAll('.captcha-img')[0];
            _this.$captchaImg = _this.$elem.querySelectorAll('.captcha-img img')[0];
            _this.$coordinate = _this.$elem.querySelectorAll('.captcha-coordinate')[0];

            _this.$control = _this.$elem.querySelectorAll('.captcha-control')[0];
            _this.$controlWrap = _this.$elem.querySelectorAll('.captcha-control-wrap')[0];
            _this.$controlButton = _this.$elem.querySelectorAll('.captcha-control-button')[0];

            _this.loadImg(function() {
                _this.events();
            });
        }

        loadImg(callback) {
            const _this = this;

            callback = callback || function() {};

            _this.runtime.loaded = !1;
            _this.$captchaImgWrap.classList.add('captcha-loading');

            _this.getJSON(_this.options.url.create, null, function(res, xhr) {
                if(res.code === 0) {
                    let token = xhr.getResponseHeader('X-CaptchaToken');
                    _this.token = token || res.data.token || '';
                    _this.$captchaImg = _this.$captchaImgWrap.querySelectorAll('img')[0];
                    _this.$captchaImg.setAttribute('src', res.data.image);
                    _this.$captchaImg.style.cssText = 'transform: rotate(0deg);';

                    _this.$captchaImg.onload = function () {
                        _this.runtime.loaded = !0;
                        _this.$captchaImgWrap.classList.remove('captcha-loading');
                    };

                    if(typeof callback == 'function') {
                        callback();
                    }
                }
            });
        }

        events(elem) {
            const _this = this;
            if(isTouch) {
                _this.initTouch();
            } else {
                _this.initMouse();
            }
        }

        spinImg() {
            const _this = this;

            if(this.runtime.deg) {
                _this.$coordinate.style.display = 'block';
            } else {
                _this.$coordinate.style.display = 'none';
            }

            _this.$captchaImg.style.cssText = 'transform: rotate('+ this.runtime.deg +'deg)';
        }

        initMouse() {
            const _this = this;
            let ifThisMousedown = !1;
            _this.$controlButton.on('mousedown', function (e) {
                if (!_this.runtime.loaded || _this.runtime.state || _this.dragTimerState || _this.$controlButton.hasAttribute('animated')) {
                    return !1;
                }

                ifThisMousedown = !0;
                let disPageX = e.pageX;
                _this.$controlButton.classList.add('captcha-button-active');

                $(document).on('mousemove', function (e) {

                    if (!ifThisMousedown) {
                        return !1;
                    }

                    let x = e.pageX - disPageX;


                    _this.move(x);
                    e.preventDefault();
                });
            });

            $(document).on('mouseup', function () {
                if (!ifThisMousedown) {
                    return !1;
                }

                ifThisMousedown = !1;

                if (_this.runtime.state) {
                    return !1;
                }

                $(document).off('mousemove');
                _this.$controlButton.classList.remove('captcha-button-active');

                if(!_this.runtime.deg || _this.runtime.left < 5) {
                    _this.$coordinate.style.display = 'none';
                    _this.$captchaImg.style.cssText = 'transform: rotate(0deg)';
                    _this.$controlButton.style.cssText = 'transform: translateX';
                    return !1;
                }

                // 验证
                _this.check();
            });
        }

        initTouch() {
            const _this = this;

            let ifThisTouchStart = !1;

            let disPageX = 0;

            _this.$controlButton.on('touchstart', function (e) {
                if (!_this.runtime.loaded || _this.runtime.state || _this.dragTimerState || _this.$controlButton.hasAttribute('animated')) {
                    return !1;
                }

                ifThisTouchStart = !0;
                disPageX = e.targetTouches[0].pageX;

                _this.$controlButton.classList.add('captcha-button-active');
            });

            _this.$controlButton.on('touchmove', function (e) {
                e.preventDefault();
                if (!ifThisTouchStart || _this.dragTimerState || _this.$controlButton.hasAttribute('animated')) {
                    return !1;
                }

                let x = e.targetTouches[0].pageX - disPageX;
                _this.move(x);
            });

            _this.$controlButton.on('touchend', function (e) {
                if (!ifThisTouchStart) {
                    return !1;
                }

                ifThisTouchStart = !1;

                if (_this.runtime.state) {
                    return !1;
                }

                if (_this.$controlButton.hasAttribute('animated')) {
                    return !1;
                }

                _this.$controlButton.classList.remove('captcha-button-active');

                if(!_this.runtime.deg || _this.runtime.left < 5) {
                    _this.$coordinate.style.display = 'none';
                    _this.$captchaImg.style.cssText = 'transform: rotate(0deg)';
                    _this.$controlButton.style.cssText = 'transform: translateX(0px)';
                    return !1;
                }

                // 验证
                _this.check();
            });
        }

        // 验证
        check() {
            const _this = this;

            _this.getJSON(_this.options.url.check, {angle: _this.runtime.deg}, function(res) {
                if(res.code === 0) {
                    _this.runtime.state = !0;
                    _this.$coordinate.style.display = 'none';
                    _this.$main.classList.add('captcha-success');
                    _this.options.success(res);
                    _this.options.complete(!0);
                    _this.$controlButton.off('touchmove');

                    if(_this.options.successClose) {
                        _this.timerProgressBar(parseInt(_this.options.successClose) || 1500);
                    }

                    return !0;
                }

                _this.options.fail(res);
                _this.options.complete(!1);

                _this.dragTimerState = !0;
                _this.$main.classList.add('captcha-fail');
                _this.$control.classList.add('captcha-control-horizontal');

                _this.$control.once('webkitAnimationEnd', function() { // animationend
                    _this.$control.classList.remove('captcha-control-horizontal');
                });

                _this.$controlButton.setAttribute('animated', 1);

                setTimeout(function() {
                    _this.dragTimerState = !1;
                    _this.$main.classList.remove('captcha-fail');
                    _this.$controlButton.style.cssText = 'transform: translateX(0px)';
                    _this.$controlButton.removeAttribute('animated');
                    _this.refresh();
                }, 1000);
            });
        }
        // ajax请求
        getJSON(url, data, callback) {
            const _this = this;
            let params = '';
            if(data && typeof data == 'object') {
                params = Object.keys(data).map(function(key) {
                    return encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
                }).join('&');

                url = url + ((url.indexOf('?') == -1 ? '?' : '&') + params);
            }

            let xhr, formData = null;
            xhr = new XMLHttpRequest();
            xhr.withCredentials = false;

            xhr.open('GET', url);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            if(_this.token) {
                xhr.setRequestHeader('X-CaptchaToken', _this.token);
            }
            xhr.onload = function() {
                if (xhr.status != 200) {
                    return;
                }

                try {
                    let res = JSON.parse(xhr.responseText) || null;
                    if (!res) {
                        return;
                    }
                    callback(res, xhr);
                } catch(e) {
                    return;
                }
            };

            xhr.send(formData);
        }

        move(x) {
            const _this = this;

            if (x < 0) {
                x = 0;
            } else if (x >= (_this.$control.clientWidth - _this.$controlButton.offsetWidth)) {
                x = _this.$control.clientWidth - _this.$controlButton.offsetWidth;
            }

            let width = _this.options.size.control - _this.$controlButton.offsetWidth;

            _this.runtime.deg = (360 / width) * x;

            let isFail = _this.$main.classList.contains('captcha-fail');

            if (x > (width + 1)) {
                _this.$controlButton.style.cssText = 'transform: translateX(0px)';
            }

            if(!isFail) {
                if (x < (width + 1) && x > -1) {
                    if (x == 0) {
                        _this.$controlButton.style.cssText = 'transform: translateX(0px)';
                    } else {
                        _this.$controlButton.style.cssText = 'transform: translateX('+ x +'px)';
                    }

                } else {
                    _this.$main.classList.add('captcha-fail');
                    _this.$controlButton.style.cssText = 'translateX('+ width +'px)';
                    _this.$controlButton.classList.remove('captcha-button-active');
                }
            }

            _this.runtime.left = x;
            _this.spinImg();
        }

        timerProgressBar (timer) {
            const _this = this;

            if(!timer) {
                return !1;
            }

            if(!_this.options.timerProgressBar) {
                setTimeout(function() {
                    _this.options.close(_this.runtime.state);
                }, timer);
                return !1;
            }

            setTimeout(function() {
                _this.options.close(_this.runtime.state);
            }, timer + 10);

            let timerProgressBar = _this.$elem.querySelectorAll('.captcha-timer-progress-bar')[0] || null;

            if(!timerProgressBar) {
                return !1;
            }

            timerProgressBar.style.display = 'flex';

            setTimeout(() => {
                timerProgressBar.style.transition = `width ${timer / 1000}s linear`;
                timerProgressBar.style.width = '0%';
            }, 10);
        }

        state() {
            return this.runtime.state || !1;
        }

        refresh() {
            this.runtime = {
                deg: 0,
                left: 0,
                state: !1,
                loaded: !1,
            };
            this.$coordinate.style.display = 'none';
            // this.$coordinate.hide();
            this.loadImg(this.$elem);
        }

        destroy() {
            this.options.close(this.runtime.state);
            this.runtime = {
                deg: 0,
                left: 0,
                state: !1,
                loaded: !1,
            };
        }

        close() {
            this.destroy();
        }

        insertCss() {

            let style = document.getElementById('J_captcha_css');

            if (!style) {
                // Load css for link
                if(this.options.path) {
                    let link = document.createElement('link');
                    link.setAttribute('id', 'J_captcha_css');
                    link.type = 'text/css';
                    link.rel = 'stylesheet';
                    link.href = this.options.path + '/style.css';
                    document.getElementsByTagName('head').item(0).appendChild(link);
                    return !1;
                }
            }
        }

        captchaHTML(options) {
            return `<div id="${options.id}" class="captcha-root" style="--theme: ${options.theme};--progress-bar-color: ${options.timerProgressBarColor};--size-width: 305px;--size-img: 152px;--size-img-margin: 28px;--size-control: 275px;">
                <div class="captcha-wrap">
                    <div class="captcha" style="--size-width: ${options.width}px">
                        <div class="captcha-title">
                            <h2>${options.title}</h2>
                            <p>${options.desc}</p>
                        </div>
                        <div class="captcha-main">
                            <div class="captcha-wrap">
                                <div class="captcha-image" style="--size-img-margin: ${options.size.imgMargin}px">
                                    <div class="captcha-img captcha-loading" style="--size-img: ${options.size.img}px">
                                        <img style="transform: rotate(0deg);">
                                        <div class="captcha-loader">
                                            <svg xmlns="https://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 38 38">
                                                <defs>
                                                    <linearGradient x1="8.042%" y1="0%" x2="65.682%" y2="23.865%" id="a">
                                                        <stop stop-color="${options.theme}" stop-opacity="0" offset="0%"/>
                                                        <stop stop-color="${options.theme}" stop-opacity=".631" offset="63.146%"/>
                                                        <stop stop-color="${options.theme}" offset="100%"/>
                                                    </linearGradient>
                                                </defs>
                                                <g fill="none" fill-rule="evenodd">
                                                    <g transform="translate(1 1)">
                                                        <path d="M36 18c0-9.94-8.06-18-18-18" id="Oval-2" stroke="url(#a)" stroke-width="2">
                                                            <animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="0.9s" repeatCount="indefinite"/>
                                                        </path>
                                                        <circle fill="${options.theme}" cx="36" cy="18" r="1">
                                                            <animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="0.9s" repeatCount="indefinite"/>
                                                        </circle>
                                                    </g>
                                                </g>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="captcha-coordinate"></div>
                                    <div class="captcha-state">
                                        <svg class="captcha-state-icon-success" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 86.6986 86.6986">
                                            <foreignObject><style xmlns="http://www.w3.org/1999/xhtml">@-webkit-keyframes drawLine{100%{-webkit-stroke-dashoffset: 0;stroke-dashoffset: 0;}}@keyframes drawLine{100%{-webkit-stroke-dashoffset: 0;stroke-dashoffset: 0;}}svg.captcha-state-icon-success path{fill:none;stroke:#fff;stroke-width:3.7253;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:49 51;stroke-dashoffset:50;animation:drawLine 400ms ease-out 90ms forwards;}</style></foreignObject>
                                            <path class="path-success" d="M26.316,42.859L37.9984,54.5414L60.3826,32.1572"></path>
                                        </svg>
                                        <svg class="captcha-state-icon-fail" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 86.6986 86.6986">
                                            <foreignObject><style xmlns="http://www.w3.org/1999/xhtml">@-webkit-keyframes drawLine{100%{-webkit-stroke-dashoffset:0;stroke-dashoffset:0;}}@keyframes drawLine{100%{-webkit-stroke-dashoffset: 0;stroke-dashoffset: 0;}}svg.captcha-state-icon-fail path{fill:none;stroke:#fff;stroke-dasharray:42 44;stroke-dashoffset:-43;stroke-width:3.7253;stroke-linecap:round;stroke-linejoin:round;}.path-1{animation:drawLine 400ms ease-out 80ms forwards;}.path-2{animation:drawLine 400ms ease-out 280ms forwards;}</style></foreignObject>
                                            <path class="path-1" d="M28.774,57.9246L57.9247,28.7739"></path>
                                            <path class="path-2" d="M57.9246,57.9246L28.7739,28.7739"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="captcha-control" style="--size-control: ${options.size.control}px">
                                <div class="captcha-control-wrap"></div>
                                <div class="captcha-control-button"><i></i></div>
                            </div>
                        </div>
                        <div class="captcha-timer-progress-bar-wrap">
                            <div class="captcha-timer-progress-bar" style="display: none;"></div>
                        </div>
                    </div>
                </div>
            </div>`;
        }
    };

    Element.prototype.on = function(event, callback) {
        this.addEventListener(event, callback, false);
        return this;
    }

    Element.prototype.off = function(event, callback) {
        this.removeEventListener(event, callback, false);
        return this;
    }

    Element.prototype.once = function(type, callback) {
        var handle = function() {
            callback = callback.call(this)
            this.removeEventListener(type, handle)
        }
        this.addEventListener(type, handle)
    }

    Element.prototype.captcha = function(options) {
        if(!this.hasAttribute('data-' + libName)) {
            let instance = new Captcha(this, options);
            instances.push(instance);
            this.setAttribute('data-' + libName, true);
            return instance;
        }
    }

    !(function (global) {
        let extend,
            _extend,
            _isObject;
        _isObject = function (o) {
            return Object.prototype.toString.call(o) === '[object Object]';
        };
        _extend = function self(destination, source) {
            let property;
            for (property in destination) {
                if (destination.hasOwnProperty(property)) {
                    if (_isObject(destination[property]) && _isObject(source[property])) {
                        self(destination[property], source[property]);
                    }
                    if (source.hasOwnProperty(property)) {
                        continue;
                    } else {
                        source[property] = destination[property];
                    }
                }
            }
        };
        extend = function () {
            let arr = arguments,
                result = {},
                i;
            if (!arr.length)
                return {};
            for (i = arr.length - 1; i >= 0; i--) {
                if (_isObject(arr[i])) {
                    _extend(arr[i], result);
                }
            }
            arr[0] = result;
            return result;
        };
        global.extend = extend;
    })(ulit);

    window.Captcha = Captcha;
})();

if (typeof(module) !== 'undefined')
{
    module.exports = window.Captcha;
} else if (typeof define === 'function' && define.amd) {
    define([], function () {
        'use strict';
        return window.Captcha;
    });
}