$(function () {
    // Mobile menu toggle with overlay and animation
    const $drawer = $('#mobileMenu');
    const $overlay = $('#mobileOverlay');
    $('#mobileMenuBtn').on('click', function () {
        $overlay.toggleClass('opacity-100 pointer-events-auto');
        $drawer.toggleClass('translate-x-full rtl:-translate-x-full');
        $('body').toggleClass('overflow-hidden menu-open');
    });
    // Close drawer when clicking outside
    $overlay.on('click', function () {
        $overlay.removeClass('opacity-100 pointer-events-auto');
        $drawer.addClass('translate-x-full rtl:-translate-x-full');
        $('body').removeClass('overflow-hidden menu-open');
    });

    // ---------- Subscription Expiration Animation System ----------
    function checkSubscriptionStatus() {
        // بررسی کارت‌های اشتراک و اعمال انیمیشن مناسب
        $('.subscription-card, .card-container').each(function() {
            const $card = $(this);
            const daysRemaining = $card.find('.days-remaining, .expiry-info').text();
            
            if (daysRemaining) {
                // استخراج تعداد روزهای باقی‌مانده
                const daysMatch = daysRemaining.match(/(\d+)\s*روز/);
                if (daysMatch) {
                    const days = parseInt(daysMatch[1]);
                    
                    // حذف کلاس‌های قبلی
                    $card.removeClass('subscription-expiring subscription-critical');
                    
                    // اعمال انیمیشن بر اساس تعداد روزهای باقی‌مانده
                    if (days <= 7) {
                        // کمتر از ۷ روز - انیمیشن بحرانی
                        $card.addClass('subscription-critical');
                        addExpirationWarning($card, 'critical', days);
                    } else if (days <= 30) {
                        // کمتر از ۳۰ روز - انیمیشن هشدار
                        $card.addClass('subscription-expiring');
                        addExpirationWarning($card, 'expiring', days);
                    }
                }
            }
        });
    }
    
    function addExpirationWarning($card, type, days) {
        // حذف هشدارهای قبلی
        $card.find('.expiration-warning').remove();
        
        const warningClass = type === 'critical' ? 'text-red-400' : 'text-orange-400';
        const warningIcon = type === 'critical' ? 'alert-triangle' : 'clock';
        const warningText = type === 'critical' ? 
            `⚠️ اشتراک شما در ${days} روز دیگر منقضی می‌شود!` : 
            `⏰ اشتراک شما در ${days} روز دیگر منقضی می‌شود`;
        
        const $warning = $(`
            <div class="expiration-warning absolute top-2 left-2 z-10 ${warningClass} text-xs font-bold bg-gray-900/80 px-2 py-1 rounded-lg border border-current">
                <i data-feather="${warningIcon}" class="w-3 h-3 inline mr-1"></i>
                ${warningText}
            </div>
        `);
        
        $card.append($warning);
        
        // اعمال انیمیشن به هشدار
        if (type === 'critical') {
            $warning.css('animation', 'critical-warning 0.8s ease-in-out infinite');
        } else {
            $warning.css('animation', 'expiring-pulse 2s ease-in-out infinite');
        }
    }
    
    // اجرای بررسی وضعیت اشتراک‌ها
    checkSubscriptionStatus();
    
    // بررسی مجدد هر ۵ دقیقه
    setInterval(checkSubscriptionStatus, 300000);
    
    // بررسی مجدد هنگام تغییر صفحه
    $(document).on('visibilitychange', function() {
        if (!document.hidden) {
            checkSubscriptionStatus();
        }
    });



    // ---------- Modal open/close with a11y (focus, Escape, aria-hidden) ----------
    var lastModalTrigger = null;

    function openModal($modal) {
        $modal.removeClass('hidden').attr('aria-hidden', 'false');
        lastModalTrigger = document.activeElement && document.activeElement.id ? document.activeElement : null;
        var firstFocus = $modal.find('button.modal-close, input:first, [autofocus]').first()[0];
        if (firstFocus) setTimeout(function () { firstFocus.focus(); }, 50);
    }

    function closeModal($modal) {
        $modal.addClass('hidden').attr('aria-hidden', 'true');
        if (lastModalTrigger && lastModalTrigger.focus) lastModalTrigger.focus();
        lastModalTrigger = null;
    }

    $(document).on('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var $visible = $('.modal:not(.hidden)').last();
        if ($visible.length) { closeModal($visible); e.preventDefault(); }
    });

    $('#openLogin, #mobileLogin').on('click', function () {
        openModal($('#loginModal'));
    });
    $('#openRegister, #mobileRegister').on('click', function () {
        openModal($('#registerModal'));
    });

    $('.modal-close').on('click', function () {
        closeModal($(this).closest('.modal'));
    });

    $(document).on('click', '#loginModal, #registerModal, #resetModal', function (e) {
        if (e.target === this) closeModal($(this));
    });

    $(document).on('click', '.pricing-cta', function () {
        var action = $(this).data('pricing-cta');
        if (action === 'register') openModal($('#registerModal'));
        else if (action === 'login') openModal($('#loginModal'));
    });

    $(document).on('click', '#openReset', function () {
        $('#loginModal').addClass('hidden').attr('aria-hidden', 'true');
        openModal($('#resetModal'));
    });

    const $resetStep1 = $('#resetStep1');
    const $resetStep2 = $('#resetStep2');
    const $resetMsg   = $('#resetMsg');

    function getCsrf() {
        return $('meta[name="csrf-token"]').attr('content') || $('#resetCsrf').val() || '';
    }
    $('#sendResetCode').on('click', function(){
        const email=$('#resetEmail').val().trim();
        if(!email){$resetMsg.text('ایمیل را وارد کنید');return;}
        $resetMsg.text('در حال ارسال...');
        $.post('api/auth.php', {action:'request_reset', email, csrf_token: getCsrf()}, function(res){
           if(res.success){
               $resetMsg.text('کد ارسال شد. ایمیل خود را بررسی کنید');
               $resetStep1.addClass('hidden');
               $resetStep2.removeClass('hidden');
           }else{$resetMsg.text(res.message||'خطا');}
        },'json').fail(function(xhr){ setAuthError(xhr, $resetMsg, 'خطا در اتصال'); });
    });

    $('#confirmReset').on('click', function(){
        const email=$('#resetEmail').val().trim();
        const code=$('#resetCode').val().trim();
        const password=$('#resetNewPass').val();
        if(!code||!password){$resetMsg.text('همه فیلدها الزامیست');return;}
        $resetMsg.text('در حال تایید...');
        $.post('api/auth.php',{action:'confirm_reset',email,code,password,csrf_token: getCsrf()},function(res){
           if(res.success){
               $resetMsg.text('رمز عبور بروزرسانی شد.');
               setTimeout(()=>{
                   $('#resetModal').addClass('hidden');
                   $('#loginModal').removeClass('hidden');
                   // reset form
                   $('#resetStep2').addClass('hidden');
                   $('#resetStep1').removeClass('hidden');
                   $resetMsg.text('');
               },800);
           }
           else{$resetMsg.text(res.message||'خطا');}
        },'json').fail(function(xhr){ setAuthError(xhr, $resetMsg, 'خطا در اتصال'); });
    });

    // Helper: show auth error (including 429 rate limit)
    function setAuthError(xhr, $el, fallback) {
        var msg = fallback || 'خطا در اتصال به سرور';
        try {
            var data = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
            if (xhr.status === 429 && data.message) msg = data.message;
            else if (data.message) msg = data.message;
        } catch (e) {}
        if ($el && $el.length) $el.text(msg);
    }
    // Submit login form
    $('#loginForm').on('submit', function (e) {
        e.preventDefault();
        const data = $(this).serialize() + '&action=login';
        $.post('api/auth.php', data, function (response) {
            if (response.success) {
                location.reload();
            } else {
                $('#loginError').text(response.message || 'خطا در ورود');
            }
        }, 'json').fail(function (xhr) {
            setAuthError(xhr, $('#loginError'), 'خطا در اتصال به سرور');
        });
    });

    // Submit register form
    let regEmail='';
    $('#registerForm').on('submit',function(e){
        e.preventDefault();
        regEmail=$(this).find('input[name="email"]').val().trim();
        const data=$(this).serialize()+'&action=register_request';
        const $btn=$(this).find('button[type="submit"]');
        $btn.prop('disabled',true);
        $.post('api/auth.php',data,function(res){
            if(res.success){
                $('#registerError').text('');
                $('#registerForm').addClass('hidden');
                $('#registerStep2').removeClass('hidden');
            }else{
                $('#registerError').text(res.message||'خطا');
            }
        },'json').always(function(){ $btn.prop('disabled',false); }).fail(function(xhr){ setAuthError(xhr, $('#registerError'), 'خطا در اتصال'); });
    });

    $('#confirmRegister').on('click',function(){
        const code=$('#registerCode').val().trim();
        if(!code){$('#registerMsg').text('کد را وارد کنید');return;}
        const $btn=$(this);
        $btn.prop('disabled',true);
        $.post('api/auth.php',{action:'confirm_register',email:regEmail,code,csrf_token: getCsrf()},function(res){
            if(res.success){location.reload();}
            else{ $('#registerMsg').text(res.message||'خطا'); $btn.prop('disabled',false); }
        },'json').fail(function(xhr){ setAuthError(xhr, $('#registerMsg'), 'خطا در اتصال'); $btn.prop('disabled',false); });
    });
});