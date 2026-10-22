/* Arcaea 用户前端 脚本 */
(function () {
    'use strict';

    /* ---------- 注册页：发送验证码 ---------- */
    var sendBtn = document.getElementById('send-code-btn');
    var codeInput = document.getElementById('code');
    var emailInput = document.getElementById('email');

    function cooldown(seconds) {
        if (!sendBtn) return;
        sendBtn.disabled = true;
        var remain = seconds;
        sendBtn.textContent = remain + 's';
        var timer = setInterval(function () {
            remain -= 1;
            if (remain <= 0) {
                clearInterval(timer);
                sendBtn.disabled = false;
                sendBtn.textContent = '发送验证码';
            } else {
                sendBtn.textContent = remain + 's';
            }
        }, 1000);
    }

    if (sendBtn && emailInput) {
        sendBtn.addEventListener('click', function () {
            var email = emailInput.value.trim();

            if (!email || email.indexOf('@') < 0 || email.indexOf('.') < 0) {
                showAlert('请先输入正确的邮箱地址', 'error');
                return;
            }

            sendBtn.disabled = true;
            sendBtn.textContent = '发送中…';

            fetch('api/send_code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: 'email=' + encodeURIComponent(email)
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && data.ok) {
                        showAlert(data.message || '验证码已发送', 'success');
                        if (codeInput) codeInput.focus();
                        cooldown(60);
                    } else {
                        showAlert((data && data.message) || '发送失败，请重试', 'error');
                        if (sendBtn) {
                            sendBtn.disabled = false;
                            sendBtn.textContent = '发送验证码';
                        }
                    }
                })
                .catch(function () {
                    showAlert('网络错误，请重试', 'error');
                    if (sendBtn) {
                        sendBtn.disabled = false;
                        sendBtn.textContent = '发送验证码';
                    }
                });
        });
    }

    /* ---------- 提示条 ---------- */
    var alertBox = null;

    function showAlert(message, type) {
        if (!alertBox) {
            alertBox = document.createElement('div');
            alertBox.className = 'alert';
            var card = document.querySelector('.auth-card');
            var logo = document.querySelector('.logo');
            if (card && logo) {
                card.insertBefore(alertBox, logo.nextSibling);
            }
        }
        alertBox.className = 'alert alert-' + (type === 'success' ? 'success' : 'error');
        alertBox.textContent = message;
    }

    /* ---------- 主页：复制好友码 ---------- */
    var copyBtns = document.querySelectorAll('.copy-btn');
    copyBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy') || '';
            var done = function () {
                btn.textContent = '已复制 ✓';
                setTimeout(function () { btn.textContent = '复制'; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function () { fallbackCopy(text, btn, done); });
            } else {
                fallbackCopy(text, btn, done);
            }
        });
    });

    function fallbackCopy(text, btn, done) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            done();
        } catch (err) {
            btn.textContent = '复制失败';
        }
        document.body.removeChild(ta);
    }
})();