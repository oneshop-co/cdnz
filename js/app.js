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



    // Open modal buttons
    $('#openLogin, #mobileLogin').on('click', function () {
        $('#loginModal').removeClass('hidden');
    });
    $('#openRegister, #mobileRegister').on('click', function () {
        $('#registerModal').removeClass('hidden');
    });

    // Close modal buttons
    $('.modal-close').on('click', function () {
        $(this).closest('.modal').addClass('hidden');
    });

    // Close login/register modal when clicking outside content
    $(document).on('click', '#loginModal, #registerModal, #resetModal', function (e) {
        if (e.target === this) {
            $(this).addClass('hidden');
        }
    });

    // Forgot password link
    $(document).on('click','#openReset',function(){
        $('#loginModal').addClass('hidden');
        $('#resetModal').removeClass('hidden');
    });

    const $resetStep1 = $('#resetStep1');
    const $resetStep2 = $('#resetStep2');
    const $resetMsg   = $('#resetMsg');

    $('#sendResetCode').on('click', function(){
        const email=$('#resetEmail').val().trim();
        if(!email){$resetMsg.text('ایمیل را وارد کنید');return;}
        $resetMsg.text('در حال ارسال...');
        $.post('api/auth.php', {action:'request_reset', email}, function(res){
           if(res.success){
               $resetMsg.text('کد ارسال شد. ایمیل خود را بررسی کنید');
               $resetStep1.addClass('hidden');
               $resetStep2.removeClass('hidden');
           }else{$resetMsg.text(res.message||'خطا');}
        },'json').fail(()=>{$resetMsg.text('خطا در اتصال');});
    });

    $('#confirmReset').on('click', function(){
        const email=$('#resetEmail').val().trim();
        const code=$('#resetCode').val().trim();
        const password=$('#resetNewPass').val();
        if(!code||!password){$resetMsg.text('همه فیلدها الزامیست');return;}
        $resetMsg.text('در حال تایید...');
        $.post('api/auth.php',{action:'confirm_reset',email,code,password},function(res){
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
        },'json').fail(()=>{$resetMsg.text('خطا در اتصال');});
    });

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
        }, 'json').fail(function () {
            $('#loginError').text('خطا در اتصال به سرور');
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
        },'json').always(function(){ $btn.prop('disabled',false); }).fail(function(){ $('#registerError').text('خطا در اتصال'); });
    });

    $('#confirmRegister').on('click',function(){
        const code=$('#registerCode').val().trim();
        if(!code){$('#registerMsg').text('کد را وارد کنید');return;}
        const $btn=$(this);
        $btn.prop('disabled',true);
        $.post('api/auth.php',{action:'confirm_register',email:regEmail,code},function(res){
            if(res.success){location.reload();}
            else{ $('#registerMsg').text(res.message||'خطا'); $btn.prop('disabled',false); }
        },'json').fail(function(){ $('#registerMsg').text('خطا در اتصال'); $btn.prop('disabled',false); });
    });
});