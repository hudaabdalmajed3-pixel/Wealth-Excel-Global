<?php
// my_team.php - صفحة الفريق (باركود + نسخ الرابط + بيانات الإحالات المفعلة)
ob_start();
session_start();

require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$lang = $_SESSION['lang'] ?? 'en';

// رابط الإحالة
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$script_path = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
$script_path = rtrim($script_path, '/');
$myRefLink = "{$protocol}://{$domain}{$script_path}/Register.php?ref={$user_id}";

// جلب بيانات الإحالات من جدول referrals
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id, u.username, u.email, u.created_at,
            r.is_active, r.total_deposit
        FROM referrals r
        JOIN users u ON r.referred_id = u.id
        WHERE r.referrer_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $team = $stmt->fetchAll();

    $total_referred = count($team);
    $active_count = 0;
    $total_deposits_sum = 0;
    foreach ($team as $member) {
        if ($member['is_active']) {
            $active_count++;
            $total_deposits_sum += floatval($member['total_deposit']);
        }
    }
} catch (PDOException $e) {
    $dbError = "خطأ في قاعدة البيانات: " . $e->getMessage();
    $team = [];
    $total_referred = 0;
    $active_count = 0;
    $total_deposits_sum = 0;
}

$strings = [
    'en' => [
        'page_title' => 'My Family',
        'back' => 'Dashboard',
        'total_members' => 'Total Family',
        'active_members' => 'Active Family', 
        'total_deposits' => 'Total Interactive Activity',
        'share_title' => 'Invite to Join My Family',
        'share_desc' => 'Scan the code to join or copy the link below.',
        'copy' => 'Copy Link',
        'list_title' => 'Family Members List',
        'th_user' => 'User',
        'th_date' => 'Join Date',
        'th_deposit' => 'Total Activity',
        'th_status' => 'Status',
        'st_active' => 'Active',
        'st_inactive' => 'Inactive',
        'no_data' => 'No family members yet. Share your link!',
        'copied_msg' => 'Link Copied!',
    ],
    'ar' => [
        'page_title' => 'عائلتي',
        'back' => 'الرئيسية',
        'total_members' => 'إجمالي عائلتي',
        'active_members' => 'العائلة النشطة', 
        'total_deposits' => 'إجمالي النشاط التفاعلي',
        'share_title' => 'دعوة للانضمام لعائلتي',
        'share_desc' => 'امسح الرمز للانضمام أو انسخ الرابط أدناه.',
        'copy' => 'نسخ الرابط',
        'list_title' => 'قائمة أفراد العائلة',
        'th_user' => 'المستخدم',
        'th_date' => 'تاريخ الانضمام',
        'th_deposit' => 'إجمالي النشاط',
        'th_status' => 'الحالة',
        'st_active' => 'مفعل',
        'st_inactive' => 'غير مفعل',
        'no_data' => 'لا يوجد أفراد في عائلتك بعد. شارك رابطك الآن!',
        'copied_msg' => 'تم نسخ الرابط!',
    ]
];
$t = $strings[$lang];
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo ($lang=='ar')?'rtl':'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['page_title']; ?> | Wealth Excel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Noto+Sans+Arabic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --gold: #d4af37; --bg: #020617; --card: #1e293b; --text: #f8fafc; }
        body { background: radial-gradient(circle at top, #111827 0, #020617 55%); color: var(--text); font-family: 'Inter', sans-serif; margin: 0; padding: 20px; }
        [dir="rtl"] { font-family: 'Noto Sans Arabic', sans-serif; }
        header { display: flex; justify-content: space-between; align-items: center; background: rgba(15, 23, 42, 0.9); padding: 15px 20px; border-radius: 12px; border: 1px solid #334155; margin-bottom: 25px; }
        .logo { color: var(--gold); font-weight: bold; text-decoration: none; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .btn-lang { background: transparent; border: 1px solid #555; color: #ccc; padding: 5px 12px; cursor: pointer; border-radius: 6px; font-size: 12px; }
        .btn-back { background: var(--gold); color: black; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 13px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 12px; border: 1px solid rgba(212, 175, 55, 0.2); text-align: center; }
        .stat-val { font-size: 24px; font-weight: bold; color: var(--gold); margin-top: 5px; display: block; }
        .stat-label { font-size: 13px; color: #94a3b8; }
        .referral-card { 
            background: linear-gradient(135deg, rgba(30, 41, 59, 1) 0%, rgba(15, 23, 42, 1) 100%);
            border: 1px solid rgba(212, 175, 55, 0.4); border-radius: 12px; padding: 25px; margin-bottom: 25px;
            text-align: center; display: flex; flex-direction: column; align-items: center;
        }
        .ref-title { color: var(--gold); font-weight: bold; font-size: 16px; margin-bottom: 10px; display: block; }
        .qr-box { background: #fff; padding: 10px; border-radius: 10px; margin: 15px 0; box-shadow: 0 0 15px rgba(212, 175, 55, 0.3); width: 160px; height: 160px; display: flex; align-items: center; justify-content: center; }
        .qr-box img { width: 100%; height: 100%; object-fit: contain; }
        .ref-input-group { margin-bottom: 15px; width: 100%; display: flex; justify-content: center; }
        .ref-input { width: 100%; max-width: 400px; box-sizing: border-box; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: #10b981; padding: 12px; border-radius: 6px; font-size: 13px; outline: none; font-family: monospace; text-align: center; }
        .btn-copy-wide { width: 100%; max-width: 400px; padding: 12px; border-radius: 6px; border: none; font-weight: bold; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; background: var(--gold); color: #000; }
        .btn-copy-wide:hover { background: #f0c440; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(212, 175, 55, 0.2); }
        .table-container { background: var(--card); border-radius: 12px; border: 1px solid #334155; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #334155; }
        [dir="rtl"] th, [dir="rtl"] td { text-align: right; }
        th { color: var(--gold); font-size: 12px; text-transform: uppercase; background: rgba(0,0,0,0.2); }
        td { font-size: 14px; color: #e2e8f0; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .st-active { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .st-inactive { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        #toast { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 2px; padding: 16px; position: fixed; z-index: 1000; left: 50%; bottom: 30px; transform: translateX(-50%); font-size: 14px; border: 1px solid var(--gold); }
        #toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }
        .error-banner { background: #ef4444; color: white; padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px; }
    </style>
</head>
<body>

<div id="toast"></div>

<header>
    <a href="dashboard.php" class="logo"><i class="fa-solid fa-users"></i> <?php echo $t['page_title']; ?></a>
    <div style="display: flex; gap: 10px; align-items: center;">
        <button class="btn-lang" onclick="toggleLang()"><?php echo ($lang=='ar')?'English':'العربية'; ?></button>
        <a href="dashboard.php" class="btn-back"><?php echo $t['back']; ?></a>
    </div>
</header>

<?php if(isset($dbError)): ?>
    <div class="error-banner">⚠️ <?php echo $dbError; ?></div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card"><span class="stat-label"><?php echo $t['total_members']; ?></span><span class="stat-val"><?php echo $total_referred; ?></span></div>
    <div class="stat-card"><span class="stat-label"><?php echo $t['active_members']; ?></span><span class="stat-val" style="color: #10b981;"><?php echo $active_count; ?></span></div>
    <div class="stat-card"><span class="stat-label"><?php echo $t['total_deposits']; ?></span><span class="stat-val"><?php echo number_format($total_deposits_sum, 2); ?></span></div>
</div>

<div class="referral-card">
    <span class="ref-title"><?php echo $t['share_title']; ?></span>
    <p style="color: #94a3b8; font-size: 13px; margin: 0;"><?php echo $t['share_desc']; ?></p>
    <div class="qr-box">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($myRefLink); ?>" alt="Invite QR">
    </div>
    <div class="ref-input-group">
        <input type="text" value="<?php echo $myRefLink; ?>" id="refInput" readonly class="ref-input">
    </div>
    <button class="btn-copy-wide" onclick="copyLink()"><i class="fa-regular fa-copy"></i> <?php echo $t['copy']; ?></button>
</div>

<h3 style="color: white; margin-bottom: 15px;"><?php echo $t['list_title']; ?></h3>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th><?php echo $t['th_user']; ?></th>
                <th><?php echo $t['th_date']; ?></th>
                <th><?php echo $t['th_deposit']; ?></th>
                <th><?php echo $t['th_status']; ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if($total_referred > 0): ?>
                <?php foreach($team as $m): ?>
                <tr>
                    <td><div style="display:flex; align-items:center; gap:8px;"><i class="fa-solid fa-circle-user" style="color: #334155; font-size: 20px;"></i><div><div style="font-weight:bold;"><?php echo htmlspecialchars($m['username']); ?></div><div style="font-size:11px; color:#64748b;"><?php echo htmlspecialchars($m['email']); ?></div></div></div></td>
                    <td><?php echo date('Y-m-d', strtotime($m['created_at'])); ?></td>
                    <td><?php echo number_format(floatval($m['total_deposit']), 2); ?></td>
                    <td><?php if($m['is_active']): ?><span class="badge st-active"><?php echo $t['st_active']; ?></span><?php else: ?><span class="badge st-inactive"><?php echo $t['st_inactive']; ?></span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center; padding: 30px; color: #94a3b8;"><?php echo $t['no_data']; ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    function toggleLang() {
        let current = "<?php echo $lang; ?>";
        let newLang = (current === 'en') ? 'ar' : 'en';
        window.location.href = '?lang=' + newLang;
    }
    function copyLink() {
        const input = document.getElementById('refInput');
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value);
        showToast("<?php echo $t['copied_msg']; ?>");
    }
    function showToast(msg) {
        var x = document.getElementById("toast");
        x.innerHTML = msg;
        x.className = "show";
        setTimeout(function(){ x.className = x.className.replace("show", ""); }, 3000);
    }
</script>
</body>
</html>