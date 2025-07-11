<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generateCsrfToken(); ?>">
    <title><?php echo $pageTitle ?? 'نظام إدارة الأمناء الشرعيين'; ?></title>
    
    <!-- الأيقونة المفضلة -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <!-- ملفات CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- Font Awesome للأيقونات -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- ملفات CSS إضافية -->
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link href="<?php echo $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <style>
        /* تخصيصات إضافية للصفحة */
        <?php if (isset($pageCSS)): ?>
            <?php echo $pageCSS; ?>
        <?php endif; ?>
    </style>
</head>
<body class="<?php echo $bodyClass ?? ''; ?>">
    
    <!-- شريط التنقل العلوي -->
    <?php if (!isset($hideNavbar) || !$hideNavbar): ?>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <!-- شعار النظام -->
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <img src="assets/images/logo.png" alt="شعار النظام" height="40" class="me-2">
                <span>نظام إدارة الأمناء الشرعيين</span>
            </a>
            
            <!-- أزرار التنقل للشاشات الصغيرة -->
            <div class="d-flex align-items-center">
                <!-- زر الشريط الجانبي -->
                <?php if (isLoggedIn()): ?>
                <button class="btn btn-outline-primary me-2 d-lg-none" type="button" data-toggle="sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <?php endif; ?>
                
                <!-- قائمة المستخدم -->
                <?php if (isLoggedIn()): ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                        <img src="<?php echo getCurrentUser()['profile_image_url'] ?? 'assets/images/default-avatar.png'; ?>" 
                             alt="الصورة الشخصية" class="rounded-circle me-2" width="32" height="32">
                        <span class="d-none d-md-inline"><?php echo getCurrentUser()['full_name']; ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">مرحباً، <?php echo getCurrentUser()['first_name']; ?></h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>الملف الشخصي</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>الإعدادات</a></li>
                        <li><a class="dropdown-item" href="notifications.php">
                            <i class="fas fa-bell me-2"></i>الإشعارات
                            <span class="badge bg-danger notification-badge ms-2" style="display: none;">0</span>
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="NotarySystem.logout()">
                            <i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج
                        </a></li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- الشريط الجانبي -->
    <?php if (isLoggedIn() && (!isset($hideSidebar) || !$hideSidebar)): ?>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-sticky">
            <!-- زر الإغلاق للشاشات الصغيرة -->
            <div class="d-flex justify-content-between align-items-center p-3 d-lg-none">
                <h5 class="mb-0">القائمة</h5>
                <button type="button" class="btn-close" data-close="sidebar"></button>
            </div>
            
            <!-- قائمة التنقل -->
            <ul class="nav flex-column">
                <!-- لوحة التحكم -->
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        لوحة التحكم
                    </a>
                </li>
                
                <?php if (hasRole('Administrator')): ?>
                <!-- إدارة النظام -->
                <li class="nav-item">
                    <h6 class="nav-header mt-3 mb-2 text-muted">إدارة النظام</h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('admin/users.php') ? 'active' : ''; ?>" href="admin/users.php">
                        <i class="fas fa-users me-2"></i>
                        إدارة المستخدمين
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('admin/licenses.php') ? 'active' : ''; ?>" href="admin/licenses.php">
                        <i class="fas fa-certificate me-2"></i>
                        إدارة التراخيص
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('admin/districts.php') ? 'active' : ''; ?>" href="admin/districts.php">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        إدارة المديريات
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('admin/reports.php') ? 'active' : ''; ?>" href="admin/reports.php">
                        <i class="fas fa-chart-bar me-2"></i>
                        التقارير والإحصائيات
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('admin/settings.php') ? 'active' : ''; ?>" href="admin/settings.php">
                        <i class="fas fa-cogs me-2"></i>
                        إعدادات النظام
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasRole('Supervisor')): ?>
                <!-- إدارة الإشراف -->
                <li class="nav-item">
                    <h6 class="nav-header mt-3 mb-2 text-muted">إدارة الإشراف</h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('supervisor/notaries.php') ? 'active' : ''; ?>" href="supervisor/notaries.php">
                        <i class="fas fa-user-tie me-2"></i>
                        الأمناء الشرعيين
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('supervisor/contracts.php') ? 'active' : ''; ?>" href="supervisor/contracts.php">
                        <i class="fas fa-file-contract me-2"></i>
                        مراجعة العقود
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('supervisor/approvals.php') ? 'active' : ''; ?>" href="supervisor/approvals.php">
                        <i class="fas fa-check-circle me-2"></i>
                        الموافقات المعلقة
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('supervisor/reports.php') ? 'active' : ''; ?>" href="supervisor/reports.php">
                        <i class="fas fa-file-alt me-2"></i>
                        تقارير المديرية
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasRole('Notary')): ?>
                <!-- أعمال الأمين الشرعي -->
                <li class="nav-item">
                    <h6 class="nav-header mt-3 mb-2 text-muted">أعمال التوثيق</h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('notary/contracts.php') ? 'active' : ''; ?>" href="notary/contracts.php">
                        <i class="fas fa-file-signature me-2"></i>
                        عقودي
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('notary/new-contract.php') ? 'active' : ''; ?>" href="notary/new-contract.php">
                        <i class="fas fa-plus-circle me-2"></i>
                        عقد جديد
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('notary/clients.php') ? 'active' : ''; ?>" href="notary/clients.php">
                        <i class="fas fa-address-book me-2"></i>
                        العملاء
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('notary/documents.php') ? 'active' : ''; ?>" href="notary/documents.php">
                        <i class="fas fa-folder-open me-2"></i>
                        المستندات
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- القسم العام -->
                <li class="nav-item">
                    <h6 class="nav-header mt-3 mb-2 text-muted">عام</h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('profile.php') ? 'active' : ''; ?>" href="profile.php">
                        <i class="fas fa-user me-2"></i>
                        الملف الشخصي
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('notifications.php') ? 'active' : ''; ?>" href="notifications.php">
                        <i class="fas fa-bell me-2"></i>
                        الإشعارات
                        <span class="badge bg-danger notification-badge ms-2" style="display: none;">0</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isCurrentPage('help.php') ? 'active' : ''; ?>" href="help.php">
                        <i class="fas fa-question-circle me-2"></i>
                        المساعدة
                    </a>
                </li>
            </ul>
            
            <!-- معلومات المستخدم -->
            <div class="mt-auto p-3 border-top">
                <div class="d-flex align-items-center">
                    <img src="<?php echo getCurrentUser()['profile_image_url'] ?? 'assets/images/default-avatar.png'; ?>" 
                         alt="الصورة الشخصية" class="rounded-circle me-2" width="40" height="40">
                    <div class="flex-grow-1">
                        <div class="fw-medium"><?php echo getCurrentUser()['first_name']; ?></div>
                        <small class="text-muted"><?php echo getCurrentUser()['role_name']; ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- المحتوى الرئيسي -->
    <main class="main-content <?php echo isLoggedIn() ? 'sidebar-open' : ''; ?>">
        <!-- شريط العنوان -->
        <?php if (isset($pageTitle) && isLoggedIn()): ?>
        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title mb-1"><?php echo $pageTitle; ?></h1>
                    <?php if (isset($pageDescription)): ?>
                    <p class="page-description text-muted mb-0"><?php echo $pageDescription; ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($pageActions)): ?>
                <div class="page-actions">
                    <?php echo $pageActions; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- مسار التنقل -->
            <?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
            <nav aria-label="breadcrumb" class="mt-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></li>
                    <?php foreach ($breadcrumbs as $breadcrumb): ?>
                        <?php if (isset($breadcrumb['url'])): ?>
                            <li class="breadcrumb-item"><a href="<?php echo $breadcrumb['url']; ?>"><?php echo $breadcrumb['title']; ?></a></li>
                        <?php else: ?>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo $breadcrumb['title']; ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- محتوى الصفحة -->
        <div class="page-content">
            <?php
            // عرض الرسائل
            if (isset($_SESSION['flash_messages'])) {
                foreach ($_SESSION['flash_messages'] as $message) {
                    echo '<div class="alert alert-' . $message['type'] . ' alert-dismissible fade show" role="alert">';
                    echo $message['text'];
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    echo '</div>';
                }
                unset($_SESSION['flash_messages']);
            }
            ?>
            
            <!-- محتوى الصفحة الفعلي يتم تضمينه هنا -->

