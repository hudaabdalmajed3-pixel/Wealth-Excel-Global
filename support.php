<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>مركز الدعم | WEALTHXCEL</title>
  <style>
    :root {
      --bg: #000000;
      --card: rgba(255,255,255,0.06);
      --border: rgba(255,255,255,0.12);
      --text: #f5f5f5;
      --muted: #aab2bf;
      --brand: #d4af37; /* ذهبي */
      --radius: 14px;
      --shadow: 0 10px 24px rgba(0,0,0,0.6);
    }
    body { margin:0; background:var(--bg); color:var(--text); font-family:'Segoe UI',sans-serif; }
    .header { display:flex; justify-content:space-between; align-items:center; padding:20px; }
    .controls { display:flex; gap:10px; padding:0 20px 20px; }
    .controls input, .controls button {
      padding:10px; border-radius:8px; border:1px solid var(--border);
      background:var(--card); color:var(--text); cursor:pointer;
    }
    .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:20px; padding:0 20px 40px; }
    .card { position:relative; background:var(--card); border:1px solid var(--border); border-radius:var(--radius); padding:16px; box-shadow:var(--shadow); }
    .card h3 { margin:0 0 6px; font-size:18px; }
    .card p { margin:4px 0; font-size:14px; color:var(--muted); }
    .card .status { display:inline-block; padding:4px 8px; border-radius:8px; font-size:12px; background:var(--brand); color:#000; margin-top:8px; }
    .card button { margin-top:10px; padding:8px 12px; background:var(--brand); color:#000; border:none; border-radius:8px; cursor:pointer; width:100%; font-weight:bold; }
    .card button:hover, .controls button:hover { background:#b7952b; }
    .favorite { position:absolute; top:12px; left:12px; background:rgba(212,175,55,0.2); border:1px solid var(--border); border-radius:50%; width:28px; height:28px; display:grid; place-items:center; cursor:pointer; }
    .favorite[data-active="true"] { background:var(--brand); color:#000; }
    .knowledge { padding:0 20px 40px; }
    .knowledge h2 { font-size:20px; margin-bottom:10px; }
    .knowledge ul { list-style:none; padding:0; }
    .knowledge li { margin-bottom:8px; font-size:14px; }
    .knowledge button { background:none; border:none; color:var(--brand); cursor:pointer; font-size:14px; text-align:left; }
    .modal { position:fixed; inset:0; background:rgba(0,0,0,0.7); display:none; align-items:center; justify-content:center; padding:20px; }
    .modal[aria-hidden="false"] { display:flex; }
    .modal__card { width:min(520px,96vw); background:var(--card); border:1px solid var(--border); border-radius:var(--radius); box-shadow:var(--shadow); padding:18px; }
    .modal__header { display:flex; justify-content:space-between; align-items:center; }
    .close { background:var(--brand); border:none; color:#000; border-radius:10px; padding:6px 10px; cursor:pointer; font-weight:bold; }
  </style>
</head>
<body>
  <div class="header">
    <h1 id="title">مركز الدعم</h1>
    <div>
      <button id="toggleLang">🌐 عربي/English</button>
      <button id="toggleTheme">الوضع الليلي/النهاري</button>
    </div>
  </div>

  <div class="controls">
    <input type="search" id="search" placeholder="ابحث عن تذكرة..." />
    <button onclick="window.location.href='mailto:support@wealthxcel.com'">📧 اتصل بالدعم</button>
  </div>

  <div class="grid" id="grid"></div>

  <div class="knowledge">
    <h2 id="kbTitle">قاعدة المعرفة</h2>
    <ul id="kbList"></ul>
  </div>

  <div class="modal" id="modal" aria-hidden="true">
    <div class="modal__card">
      <div class="modal__header">
        <h2 id="modalTitle"></h2>
        <button class="close" id="modalClose">✕</button>
      </div>
      <div id="modalBody"></div>
    </div>
  </div>

  <script>
    const tickets = [
      { id:"1236", title_ar:"مشكلة تسجيل الدخول", title_en:"Login Issue", status:"Open", date:"2024-04-08" },
      { id:"1235", title_ar:"تأخير السحب", title_en:"Withdrawal Delay", status:"Closed", date:"2024-04-08" },
      { id:"1234", title_ar:"التحقق من الحساب", title_en:"Account Verification", status:"Closed", date:"2024-04-02" }
    ];

    const knowledgeBase = {
      ar:[
        {q:"كيف أعيد تعيين كلمة المرور؟",a:"من صفحة تسجيل الدخول اختر 'نسيت كلمة المرور' واتبع التعليمات."},
        {q:"كيف أقوم بالإيداع؟",a:"اذهب إلى صفحة المحفظة واختر 'إيداع' ثم اتبع الخطوات."},
        {q:"أين أجد سجل المعاملات؟",a:"يمكنك رؤية جميع المعاملات في قسم 'المحفظة'."},
        {q:"كيف أرقّي الباقة؟",a:"من صفحة الباقة اختر 'ترقية' وحدد الباقة الجديدة."}
      ],
      en:[
        {q:"How do I reset my password?",a:"On the login page choose 'Forgot password' and follow the steps."},
        {q:"How to make a deposit?",a:"Go to the wallet page, select 'Deposit' and follow the instructions."},
        {q:"Where can I view my transaction history?",a:"All transactions are visible in the 'Wallet' section."},
        {q:"How can I upgrade my package?",a:"From the package page choose 'Upgrade' and select the new package."}
      ]
    };

    const state = {
      lang: localStorage.getItem("lang") || "ar",
      theme: localStorage.getItem("theme") || "dark",
      favorites: JSON.parse(localStorage.getItem("favorites") || "{}"),
      search: ""
    };

    const $ = sel => document.querySelector(sel);
    const $$ = sel => document.querySelectorAll(sel);

    function setLanguageTexts() {
      document.documentElement.lang = state.lang;
      document.documentElement.dir = state.lang === "ar" ? "rtl" : "ltr";
      $("#title").textContent = state.lang === "ar" ? "مركز الدعم" : "Support Center";
      $("#search").placeholder = state.lang === "ar" ? "ابحث عن تذكرة..." : "Search tickets...";
      $("#kbTitle").textContent = state.lang === "ar" ? "قاعدة المعرفة" : "Knowledge Base";
    }

    function render() {
      setLanguageTexts();

      const query = state.search.toLowerCase();
      const filtered = tickets.filter(t =>
        t.id.includes(query) ||
        (state.lang==="ar"?t.title_ar:t.title_en).toLowerCase().includes(query) ||
        t.status.toLowerCase().includes(query)
      );

      $("#grid").innerHTML = filtered.map(t => `
        <div class="card" data-id="${t.id}">
          <div class="favorite" data-id="${t.id}" data-active="${!!state.favorites[t.id]}">★</div>
          <h3>${state.lang==="ar"?t.title_ar:t.title_en}</h3>
          <p>#${t.id}</p>
          <p>${t.date}</p>
          <span class="status">${state.lang==="ar"?(t.status==="Open"?"مفتوحة":"مغلقة"):t.status}</span>
          <button>${state.lang==="ar"?"تفاصيل":"Details"}</button>
        </div>
      `).join("");

      // قاعدة المعرفة
            // قاعدة المعرفة
      $("#kbList").innerHTML = knowledgeBase[state.lang]
        .map((item, idx) => `<li><button data-kb="${idx}">${item.q}</button></li>`)
        .join("");

      // تفعيل الضغط على خيارات قاعدة المعرفة
      $$("#kbList button").forEach(btn => {
        btn.onclick = () => {
          const idx = btn.getAttribute("data-kb");
          const item = knowledgeBase[state.lang][idx];
          $("#modalTitle").textContent = item.q;
          $("#modalBody").innerHTML = `<p>${item.a}</p>`;
          $("#modal").setAttribute("aria-hidden","false");
        };
      });
    }

    function closeModal() {
      $("#modal").setAttribute("aria-hidden","true");
    }

    // إغلاق النافذة المنبثقة
    $("#modalClose").onclick = closeModal;
    $("#modal").onclick = e => { if(e.target.id==="modal") closeModal(); };

    // البحث
    $("#search").oninput = e => {
      state.search = e.target.value;
      render();
    };

    // زر التبديل بين العربية والإنجليزية
    $("#toggleLang").onclick = () => {
      state.lang = state.lang === "ar" ? "en" : "ar";
      localStorage.setItem("lang", state.lang);
      render();
    };

    // تبديل المظهر (ليلي/نهاري)
    $("#toggleTheme").onclick = () => {
      state.theme = state.theme==="dark" ? "light" : "dark";
      localStorage.setItem("theme", state.theme);
      document.documentElement.setAttribute("data-theme", state.theme);
    };

    // تحميل الإعدادات عند البداية
    document.documentElement.setAttribute("data-theme", state.theme);
    render();
  </script>
</body>
</html>