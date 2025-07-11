        </div>
    </main>
    
    <!-- التذييل -->
    <?php if (!isset($hideFooter) || !$hideFooter): ?>
    <footer class="footer mt-auto py-4 bg-light border-top">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-muted">
                        &copy; <?php echo date('Y'); ?> نظام إدارة الأمناء الشرعيين - وزارة العدل اليمنية
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="d-flex justify-content-md-end align-items-center">
                        <span class="text-muted me-3">الإصدار 1.0.0</span>
                        <div class="btn-group btn-group-sm">
                            <a href="help.php" class="btn btn-outline-secondary">
                                <i class="fas fa-question-circle me-1"></i>المساعدة
                            </a>
                            <a href="contact.php" class="btn btn-outline-secondary">
                                <i class="fas fa-envelope me-1"></i>اتصل بنا
                            </a>
                            <a href="privacy.php" class="btn btn-outline-secondary">
                                <i class="fas fa-shield-alt me-1"></i>الخصوصية
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- معلومات إضافية للمطورين في بيئة التطوير -->
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert alert-info">
                        <strong>معلومات التطوير:</strong>
                        <ul class="mb-0 mt-2">
                            <li>وقت التحميل: <?php echo number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2); ?> مللي ثانية</li>
                            <li>استخدام الذاكرة: <?php echo formatBytes(memory_get_peak_usage(true)); ?></li>
                            <li>عدد الاستعلامات: <?php echo $database->getQueryCount() ?? 0; ?></li>
                            <li>إصدار PHP: <?php echo PHP_VERSION; ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </footer>
    <?php endif; ?>
    
    <!-- حاوية الإشعارات -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
    
    <!-- ملفات JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <!-- ملفات JavaScript إضافية -->
    <?php if (isset($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- JavaScript مخصص للصفحة -->
    <?php if (isset($pageJS)): ?>
    <script>
        <?php echo $pageJS; ?>
    </script>
    <?php endif; ?>
    
    <!-- تهيئة النظام -->
    <script>
        // تمرير البيانات من PHP إلى JavaScript
        window.systemData = {
            isLoggedIn: <?php echo isLoggedIn() ? 'true' : 'false'; ?>,
            currentUser: <?php echo isLoggedIn() ? json_encode(getCurrentUser(), JSON_UNESCAPED_UNICODE) : 'null'; ?>,
            csrfToken: '<?php echo generateCsrfToken(); ?>',
            baseUrl: '<?php echo BASE_URL; ?>',
            currentPage: '<?php echo basename($_SERVER['PHP_SELF']); ?>',
            userRole: '<?php echo isLoggedIn() ? getCurrentUser()['role_name'] : ''; ?>',
            notifications: <?php echo isLoggedIn() ? json_encode(getUnreadNotifications(), JSON_UNESCAPED_UNICODE) : '[]'; ?>
        };
        
        // تحديث حالة النظام
        if (window.systemData.isLoggedIn) {
            NotarySystem.state.isLoggedIn = true;
            NotarySystem.state.currentUser = window.systemData.currentUser;
            NotarySystem.state.notifications = window.systemData.notifications;
        }
        
        // تحديث شارة الإشعارات
        document.addEventListener('DOMContentLoaded', function() {
            NotarySystem.updateNotificationBadge();
        });
    </script>
    
    <!-- Google Analytics (اختياري) -->
    <?php if (defined('GOOGLE_ANALYTICS_ID') && GOOGLE_ANALYTICS_ID): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo GOOGLE_ANALYTICS_ID; ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?php echo GOOGLE_ANALYTICS_ID; ?>');
    </script>
    <?php endif; ?>
    
</body>
</html>

<?php
// تنظيف المتغيرات المؤقتة
unset($pageTitle, $pageDescription, $pageActions, $breadcrumbs, $additionalCSS, $additionalJS, $pageCSS, $pageJS, $bodyClass, $hideNavbar, $hideSidebar, $hideFooter);
?>

