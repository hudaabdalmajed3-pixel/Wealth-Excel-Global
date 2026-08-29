(function() {
    // 1. تحديد نوع الجهاز
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    
    // 2. التحقق من وضع التشغيل (Standalone Mode)
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || 
                         window.navigator.standalone === true;

    // 3. تخزين حدث مطالبة التثبيت للأندرويد
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        // إذا ظهرت شاشة التثبيت، قم بتفعيل زر التثبيت للأندرويد
        const installBtn = document.getElementById('android-install-btn');
        if (installBtn) {
            installBtn.style.display = 'block';
            installBtn.addEventListener('click', () => {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    }
                    deferredPrompt = null;
                });
            });
        }
    });

    // 4. البوابة المنطقية (The Gate Logic)
    document.addEventListener("DOMContentLoaded", function() {
        // إذا كان الجهاز هاتفاً ذكياً ولم يكن في وضع التطبيق المستقل (Browser Mode)
        if (isMobile && !isStandalone) {
            // قم بإخفاء محتوى الصفحة الأصلية لمنع الوصول إليه
            document.body.innerHTML = ''; 
            document.body.style.overflow = 'hidden';
            document.body.style.backgroundColor = '#020617'; // لون خلفية المنصة
            document.body.style.margin = '0';
            
            // حقن شاشة التثبيت (Installation Gate)
            injectInstallGate(isIOS);
        }
        
        // إذا كان الجهاز هاتفاً وفي وضع التطبيق المستقل، يتم السماح بالدخول طبيعياً
        // إذا كان الجهاز كمبيوتر، يتم السماح بالدخول طبيعياً
    });

    // 5. دالة بناء واجهة شاشة التثبيت الإجبارية
    function injectInstallGate(isIOS) {
        const gateContainer = document.createElement('div');
        gateContainer.style.cssText = `
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: radial-gradient(circle at top, #111827 0, #020617 55%);
            color: #fff; z-index: 999999; display: flex; flex-direction: column;
            align-items: center; justify-content: center; font-family: sans-serif;
            text-align: center; padding: 20px; box-sizing: border-box;
        `;

        const logoHtml = `
            <i class="fa-solid fa-wallet fa-4x" style="color:#d4af37; margin-bottom:20px;"></i>
            <h1 style="color:#d4af37; margin:0 0 10px 0;">Install Wealth Excel</h1>
            <p style="color:#ccc; font-size:16px; margin-bottom:40px; max-width:80%;">
                To continue, please install Wealth Excel on your device for a secure and optimized experience.
            </p>
        `;

        let instructionsHtml = '';

        if (isIOS) {
            // شاشة تعليمات الآيفون
            instructionsHtml = `
                <div style="background: rgba(15,23,42,0.9); border: 1px solid #d4af37; border-radius: 12px; padding: 20px; width: 100%; max-width: 350px; text-align: left;">
                    <h3 style="color:#fff; margin-top:0; text-align:center;">iOS Installation Guide</h3>
                    <div style="margin-bottom:15px; display:flex; align-items:center;">
                        <span style="background:#d4af37; color:#000; font-weight:bold; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:10px;">1</span>
                        <span>Tap the <strong>Share</strong> button <i class="fa-solid fa-arrow-up-from-bracket"></i> below.</span>
                    </div>
                    <div style="margin-bottom:15px; display:flex; align-items:center;">
                        <span style="background:#d4af37; color:#000; font-weight:bold; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:10px;">2</span>
                        <span>Scroll down and select <strong>Add to Home Screen</strong> <i class="fa-regular fa-square-plus"></i>.</span>
                    </div>
                    <div style="display:flex; align-items:center;">
                        <span style="background:#d4af37; color:#000; font-weight:bold; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-right:10px;">3</span>
                        <span>Tap <strong>Add</strong> in the top right corner.</span>
                    </div>
                </div>
                <p style="color:#10b981; margin-top:30px; font-weight:bold; font-size:14px;">
                    Open Wealth Excel from the new icon to continue.
                </p>
            `;
        } else {
            // شاشة الأندرويد
            instructionsHtml = `
                <button id="android-install-btn" style="
                    display:none;
                    background: linear-gradient(135deg, #d4af37, #b8860b);
                    color: #000; border: none; padding: 15px 30px; border-radius: 8px;
                    font-size: 18px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 15px rgba(212,175,55,0.4);
                ">Install Platform</button>
                <p style="color:#10b981; margin-top:20px; font-weight:bold; font-size:14px;">
                    After installation, open the app from your home screen.
                </p>
            `;
        }

        gateContainer.innerHTML = logoHtml + instructionsHtml;
        
        const faLink = document.createElement('link');
        faLink.rel = 'stylesheet';
        faLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        document.head.appendChild(faLink);

        document.documentElement.appendChild(gateContainer);
    }
})();