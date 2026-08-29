<?php
// footer.php - يتم تضمينه في نهاية كل صفحة لظهور التذييل أسفل المحتوى
$lang = $_SESSION['lang'] ?? 'en'; // التأكد من جلب اللغة الحالية
?>
</main> <!-- نهاية محتوى الصفحة -->

<footer class="footer">
    <div class="container" style="text-align: center; padding: 20px; border-top: 1px solid var(--border); margin-top: 40px;">
        <div class="footer-links" style="display: flex; justify-content: center; gap: 25px; flex-wrap: wrap; margin-bottom: 10px;">
            <a href="privacy.php" style="color: var(--text-muted); text-decoration: none;"><?php echo ($lang == 'ar') ? 'سياسة الخصوصية' : 'Privacy Policy'; ?></a>
            <a href="terms.php" style="color: var(--text-muted); text-decoration: none;"><?php echo ($lang == 'ar') ? 'شروط الخدمة' : 'Terms of Service'; ?></a>
            <a href="risk.php" style="color: var(--text-muted); text-decoration: none;"><?php echo ($lang == 'ar') ? 'تحذير المخاطر' : 'Risk Warning'; ?></a>
            <a href="about.php" style="color: var(--text-muted); text-decoration: none;"><?php echo ($lang == 'ar') ? 'من نحن' : 'About Us'; ?></a>
            <a href="faq.php" style="color: var(--text-muted); text-decoration: none;"><?php echo ($lang == 'ar') ? 'الأسئلة الشائعة' : 'FAQ'; ?></a>
        </div>
        <div class="copyright" style="color: var(--text-muted); font-size: 12px;">
            © <?php echo date('Y'); ?> <?php echo ($lang == 'ar') ? 'ويلث إكسل جلوبال - جميع الحقوق محفوظة' : 'Weath EXcel Global - All rights reserved'; ?>
        </div>
    </div>
</footer>

<style>
    .footer .container {
        max-width: 1300px;
        margin: 0 auto;
        padding: 0 20px;
    }
    .footer-links a:hover {
        color: var(--gold-main) !important;
    }
</style>

<script>
    document.querySelectorAll('#nav-links a').forEach(link => {
        link.addEventListener('click', () => {
            const navLinks = document.getElementById('nav-links');
            if (navLinks) {
                navLinks.classList.remove('active');
            }
        });
    });
</script>
</body>
</html>