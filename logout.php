<?php
session_start();
session_unset();     // تفريغ المتغيرات
session_destroy();   // تدمير الجلسة
header("Location: login.html"); // العودة لصفحة الدخول
exit();
?>