<?php
// security.php
session_start(); // استئناف الجلسة

// دالة للتحقق من الصلاحيات
function check_login($required_role = null) {
    // 1. هل المستخدم مسجل دخول أصلاً؟
    if (!isset($_SESSION['user_id'])) {
        // غير مسجل -> توجيه لصفحة الدخول
        header("Location: login.html");
        exit();
    }

    // 2. إذا كانت الصفحة تتطلب رتبة معينة (مثلاً أدمن فقط)
    if ($required_role !== null && $_SESSION['role'] !== $required_role) {
        // مسجل دخول لكن ليس أدمن -> توجيه للصفحة الرئيسية أو خطأ
        echo "غير مصرح لك بدخول هذه الصفحة";
        exit();
    }
}
?>