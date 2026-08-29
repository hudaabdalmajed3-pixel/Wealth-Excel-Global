<?php
// academy.php - النسخة المصلحة (إعادة الأسئلة + إصلاح التحميل)
require 'header.php'; 

// --- تم تعديل النصوص هنا بناءً على طلبك ---
$page_txt = [
    'en' => [
        // تم تغيير اسم المنصة هنا إلى Wealth Excel
        'title' => 'Wealth Excel Academy',
        // تم تحديث الوصف ليتوافق مع النسخة العربية الجديدة
        'sub' => 'Learn smarter with your smart assistant. Ask, explore, and get clear explanations of concepts you need, step by step, anytime.',
        'chat_header' => 'AI Tutor Assistant',
        'input_placeholder' => 'Ask your question about this lesson...',
        'free_limit' => 'Free limit reached. <a href="register.php">Register</a> to continue.',
        'welcome_msg' => 'Welcome to <b>%s</b>. I have read the lesson material, how can I help you understand it?',
        'download_btn' => 'Download Lesson (PDF)',
        'conn_err' => 'Connection Error'
    ],
    'ar' => [
        'title' => 'أكاديمية ويلث إكسل',
        // تم تحديث الوصف العربي بناءً على طلبك
        'sub' => 'تعلّم بطريقة أذكى مع مساعدك الذكي، اسأل واستكشف واحصل على شرح واضح للمفاهيم والمعلومات التي تحتاجها، خطوة بخطوة وفي أي وقت.',
        'chat_header' => 'المساعد الذكي',
        'input_placeholder' => 'اسأل سؤالاً حول هذا الدرس...',
        'free_limit' => 'انتهت الأسئلة المجانية. <a href="register.php">سجل الآن</a> للمتابعة.',
        'welcome_msg' => 'مرحباً بك في قسم <b>%s</b>. لقد قرأت محتوى الدرس، كيف يمكنني مساعدتك في فهمه؟',
        'download_btn' => 'تحميل ملف الدرس (PDF)',
        'conn_err' => 'خطأ في الاتصال'
    ]
];
$t = $page_txt[$lang];
$is_logged_in = isset($_SESSION['user_id']) ? 'true' : 'false';
?>

<style>
    .curriculum-list { list-style: none; padding: 0; margin-top: 30px; }
    .curriculum-item { background: var(--bg-card); border: 1px solid var(--border); padding: 20px; margin-bottom: 12px; border-radius: 12px; cursor: pointer; display: flex; align-items: center; transition: 0.3s; }
    .curriculum-item:hover { border-color: var(--gold); transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.4); }
    .module-num { font-size: 22px; color: var(--gold); font-weight: 800; margin-inline-end: 20px; padding-inline-end: 20px; border-inline-end: 1px solid #333; min-width: 50px; }

    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(10px); }
    .modal-content { display: flex; flex-direction: column; width: 900px; max-width: 95%; height: 85vh; background: #0f172a; border: 1px solid var(--gold); border-radius: 16px; overflow: hidden; position: relative; }
    
    .close-btn { position: absolute; top: 15px; <?php echo ($lang=='ar')?'left:15px':'right:15px'; ?>; background: #ef4444; color: white; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; z-index: 100; font-weight: bold; }

    .chat-section { flex: 1; display: flex; flex-direction: column; height: 100%; }
    .chat-top-bar { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(0,0,0,0.3); text-align: center; }
    .chat-title { color: var(--gold); font-weight: bold; margin-bottom: 12px; font-size: 18px; display: block; }
    
    .download-link { 
        display: inline-flex; align-items: center; gap: 8px; background: rgba(212, 175, 55, 0.1); border: 1px solid var(--gold); color: var(--gold); padding: 8px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.3s;
    }
    .download-link:hover { background: var(--gold); color: #000; }

    .chat-messages { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; background: #020617; }
    .msg { padding: 12px 16px; border-radius: 12px; max-width: 80%; font-size: 14px; line-height: 1.6; }
    .ai-msg { background: #1e293b; border: 1px solid #334155; align-self: flex-start; color: #f1f5f9; }
    .user-msg { background: var(--gold); color: #000; align-self: flex-end; font-weight: 600; }
    .sys-msg { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ffadad; align-self: center; text-align: center; width: 90%; }

    .suggestions { padding: 10px 20px; display: flex; gap: 10px; overflow-x: auto; background: #0f172a; white-space: nowrap; border-top: 1px solid rgba(255,255,255,0.05); }
    .sugg-btn { background: #1e293b; border: 1px solid #334155; color: #cbd5e1; padding: 7px 15px; border-radius: 20px; font-size: 12px; cursor: pointer; transition: 0.2s; flex-shrink: 0; }
    .sugg-btn:hover { border-color: var(--gold); color: var(--gold); }

    .chat-input-area { padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; gap: 12px; background: #0f172a; }
    .chat-input { flex: 1; padding: 12px 20px; border-radius: 30px; border: 1px solid #334155; background: #020617; color: white; outline: none; font-size: 14px; }
    .send-btn { background: var(--gold); border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; color: black; display: flex; align-items: center; justify-content: center; font-size: 18px; }

    @media (max-width: 768px) {
        .modal-content { width: 100%; height: 100vh; border-radius: 0; max-width: 100%; }
        .msg { max-width: 90%; }
    }
</style>

<div class="container">
    <h1 style="text-align:center; color:var(--gold); margin-top:30px;"><?php echo $t['title']; ?></h1>
    <p style="text-align:center; color:var(--text-muted); margin-bottom:30px;"><?php echo $t['sub']; ?></p>
    <ul class="curriculum-list" id="module-list"></ul>
</div>

<div id="modal" class="modal-overlay">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal()">✕</button>
        <div class="chat-section">
            <div class="chat-top-bar">
                <span class="chat-title" id="chat-title">Module Title</span>
                <a id="download-btn" href="#" download class="download-link">
                    <i class="fa-solid fa-file-pdf"></i> <?php echo $t['download_btn']; ?>
                </a>
            </div>
            <div class="chat-messages" id="chat-box"></div>
            <div class="suggestions" id="suggestions-box"></div>
            <div class="chat-input-area">
                <input type="text" id="user-input" class="chat-input" placeholder="<?php echo $t['input_placeholder']; ?>" onkeypress="if(event.key==='Enter') sendMessage()">
                <button class="send-btn" onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
</div>

<script>
    const IS_LOGGED_IN = <?php echo $is_logged_in; ?>;
    const currentLang = '<?php echo $lang; ?>';
    let interactionCount = 0;
    const MAX_FREE = 3;

    const modules = [
        {id: 1, en: "Global Investing", ar: "الاستثمار العالمي"},
        {id: 2, en: "Capital Management", ar: "إدارة رأس المال"},
        {id: 3, en: "Risk Management", ar: "إدارة المخاطر"},
        {id: 4, en: "Financial Psychology", ar: "السيكولوجية المالية"},
        {id: 5, en: "AI for Business", ar: "الذكاء الاصطناعي للأعمال"},
        {id: 6, en: "Global Macroeconomics", ar: "الاقتصاد الكلي العالمي"},
        {id: 7, en: "International Trade", ar: "التجارة الدولية"},
        {id: 8, en: "Digital Marketing", ar: "التسويق الرقمي"},
        {id: 9, en: "Entrepreneurship", ar: "ريادة الأعمال"},
        {id: 10, en: "Web3 & Digital Assets", ar: "Web3 والأصول الرقمية"}
    ];

    const suggestedQuestions = {
        1: { ar: ["ما الفرق بين الأسهم والسندات؟", "كيف أحدد مستوى المخاطرة؟", "ما هو التذبذب؟", "كيف أبني محفظة متنوعة؟", "قاعدة إعادة التوازن؟", "الاستثمار vs المضاربة"], en: ["Stocks vs Bonds?", "Define my risk profile", "What is Volatility?", "Build diversified portfolio", "Rebalancing Rules?", "Investing vs Speculation"] },
        2: { ar: ["كيف أصنع ميزانية؟", "قاعدة 50/30/20", "حجم صندوق الطوارئ", "تأثير الفائدة المركبة", "متى أسحب الأرباح؟", "الفرق بين الادخار والاستثمار"], en: ["How to budget?", "50/30/20 Rule", "Emergency Fund Size", "Compound Interest", "When to take profits?", "Saving vs Investing"] },
        3: { ar: ["حساب حجم الصفقة", "أين أضع وقف الخسارة؟", "مخاطر الرافعة المالية", "التحوط (Hedging)", "نسبة المخاطرة للعائد", "التنويع كأداة حماية"], en: ["Calculate Position Size", "Where to put Stop Loss?", "Leverage Risks", "What is Hedging?", "Risk/Reward Ratio", "Diversification"] },
        4: { ar: ["التغلب على الخوف (FOMO)", "السيطرة على الطمع", "الالتزام بالخطة", "تحليل نفسية السوق", "الانحياز التأكيدي", "كيف أفكر كمحترف؟"], en: ["Overcoming FOMO", "Controlling Greed", "Sticking to Plan", "Market Psychology", "Confirmation Bias", "Think like a Pro"] },
        5: { ar: ["أدوات AI للعمل", "كيف أكتب Prompt؟", "أتمتة المهام اليومية", "تحليل البيانات بالذكاء الاصطناعي", "Chatbots لخدمة العملاء", "مستقبل العمل مع AI"], en: ["Best AI Tools", "Writing Prompts", "Task Automation", "AI Data Analysis", "Chatbots for Support", "Future of Work"] },
        6: { ar: ["تأثير الفائدة على السوق", "التضخم والبطالة", "ما هو الركود؟", "قراءة التقويم الاقتصادي", "السياسة النقدية", "الناتج المحلي الإجمالي"], en: ["Interest Rates Impact", "Inflation & Unemployment", "What is Recession?", "Economic Calendar", "Monetary Policy", "What is GDP?"] },
        7: { ar: ["خطوات الاستيراد", "حساب التكلفة النهائية", "الشحن الجوي vs البحري", "الدفع الآمن للموردين", "التعامل مع الجمارك", "المصطلحات التجارية"], en: ["Importing Steps", "Landed Cost Calc", "Air vs Sea Freight", "Secure Payments", "Customs clearance", "Incoterms explained"] },
        8: { ar: ["تحديد الجمهور المستهدف", "SEO vs SEM", "قمع المبيعات (Funnel)", "التسويق بالمحتوى", "إعلانات التواصل الاجتماعي", "تحليل معدل التحويل"], en: ["Target Audience", "SEO vs SEM", "Sales Funnel", "Content Marketing", "Social Ads", "Conversion Rate"] },
        9: { ar: ["التحقق من الفكرة", "نموذج العمل التجاري", "بناء MVP", "جذب المستثمرين", "إدارة التدفق المالي", "استراتيجيات النمو"], en: ["Idea Validation", "Business Model", "Building MVP", "Attracting Investors", "Cashflow Mgmt", "Growth Strategies"] },
        10: { ar: ["ما هو البلوكشين؟", "العملات vs التوكن", "حماية المحفظة", "العقود الذكية", "التمويل اللامركزي (DeFi)", "مستقبل الـ NFTs"], en: ["What is Blockchain?", "Coins vs Tokens", "Wallet Security", "Smart Contracts", "DeFi explained", "Future of NFTs"] }
    };

    function n(num) {
        if (currentLang !== 'ar') return num;
        return num.toString().replace(/[0-9]/g, w => ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'][w]);
    }

    function renderModules() {
        const list = document.getElementById('module-list');
        modules.forEach(m => {
            const title = (currentLang === 'ar') ? m.ar : m.en;
            list.innerHTML += `
                <li class="curriculum-item" onclick="openModule(${m.id}, '${title}')">
                    <div class="module-num">${n(m.id < 10 ? '0'+m.id : m.id)}</div>
                    <div style="font-weight:bold; font-size:16px; color:#fff;">${title}</div>
                </li>`;
        });
    }

    function openModule(id, title) {
    document.getElementById('modal').style.display = 'flex';
    document.getElementById('chat-title').innerText = title;
    
    // نحدد المسار الأساسي (تأكد من كتابة اسم المجلدات بدقة كما هي في السيرفر)
    const pdfUrl = `uploads/academy/${id}.pdf`;
    
    const dlBtn = document.getElementById('download-btn');
    dlBtn.href = pdfUrl;
    
    // إضافة سمة التحميل وتحديد اسم الملف عند النزول
    dlBtn.setAttribute('download', `${title}.pdf`); 
    
    // اختبار بسيط: إذا ضغط المستخدم وحدث خطأ، يمكننا تنبيهه
    dlBtn.onclick = function(e) {
        console.log("Attempting to download:", pdfUrl);
    };

    document.getElementById('chat-box').innerHTML = '';
    const welcome = '<?php echo $t["welcome_msg"]; ?>'.replace('%s', title);
    addMessage(welcome, 'ai');
    renderSuggestions(id);
}
    function closeModal() { document.getElementById('modal').style.display = 'none'; }

    function addMessage(text, sender) {
        const box = document.getElementById('chat-box');
        const div = document.createElement('div');
        div.className = `msg ${sender}-msg`;
        if(sender==='sys') div.className = `msg sys-msg`;
        div.innerHTML = text;
        box.appendChild(div);
        box.scrollTop = box.scrollHeight;
    }

    function renderSuggestions(id) {
        const box = document.getElementById('suggestions-box');
        box.innerHTML = '';
        const qs = suggestedQuestions[id] ? suggestedQuestions[id][currentLang] : [];
        qs.forEach(q => {
            const btn = document.createElement('button');
            btn.className = 'sugg-btn'; btn.innerText = q;
            btn.onclick = () => sendMessage(q);
            box.appendChild(btn);
        });
    }

    async function sendMessage(text = null) {
        const input = document.getElementById('user-input');
        const msg = text || input.value.trim();
        if(!msg) return;
        if (!IS_LOGGED_IN && interactionCount >= MAX_FREE) {
            addMessage('<?php echo $t["free_limit"]; ?>', 'sys');
            return;
        }
        input.value = '';
        addMessage(msg, 'user');
        const loadId = 'load-'+Date.now();
        addMessage(`<span id="${loadId}"><i class="fa-solid fa-spinner fa-spin"></i></span>`, 'ai');
        try {
            const response = await fetch('ai_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: msg, module: document.getElementById('chat-title').innerText, lang: currentLang })
            });
            const data = await response.json();
            document.getElementById(loadId).parentElement.remove();
            addMessage(data.reply, data.limit_reached ? 'sys' : 'ai');
            if(!IS_LOGGED_IN) interactionCount++;
        } catch (e) {
            document.getElementById(loadId)?.parentElement.remove();
            addMessage('<?php echo $t["conn_err"]; ?>', 'sys');
        }
    }

    document.addEventListener("DOMContentLoaded", renderModules);
</script>
</body>
</html>