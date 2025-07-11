<?php
/**
 * الصفحة الرئيسية لنظام إدارة الأمناء الشرعيين
 * Main Page for Notary Management System
 */

// تعريف ثابت للوصول للنظام
define('SYSTEM_ACCESS', true);

// تضمين ملفات الإعداد
require_once 'config/config.php';
require_once 'config/database.php';

// التحقق من وجود ملف الإعداد
if (!file_exists('config/config.php') || !defined('DB_NAME')) {
    header('Location: setup.php');
    exit;
}

// التحقق من حالة الصيانة
if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE) {
    include 'templates/maintenance.php';
    exit;
}

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $database->sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (!empty($username) && !empty($password)) {
        // البحث عن المستخدم
        $user = $database->selectOne(
            "SELECT u.*, r.role_name, l.status as license_status 
             FROM users u 
             JOIN roles r ON u.role_id = r.role_id 
             LEFT JOIN licenses l ON u.license_id = l.license_id 
             WHERE u.username = ? AND u.is_active = 1",
            [$username]
        );
        
        if ($user && $database->verifyPassword($password, $user['password_hash'])) {
            // التحقق من حالة الترخيص للأمناء
            if ($user['role_name'] === 'Notary' && $user['license_status'] !== 'active') {
                $error_message = "ترخيصك غير نشط. يرجى التواصل مع الإدارة.";
            } else {
                // تسجيل دخول ناجح
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['last_activity'] = time();
                
                // تحديث آخر دخول
                $database->update(
                    "UPDATE users SET last_login = NOW() WHERE user_id = ?",
                    [$user['user_id']]
                );
                
                // تسجيل عملية الدخول
                $database->insert(
                    "INSERT INTO log_access (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)",
                    [$user['user_id'], 'login', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]
                );
                
                // توجيه المستخدم حسب دوره
                switch ($user['role_name']) {
                    case 'Administrator':
                        header('Location: admin/dashboard.php');
                        break;
                    case 'Head of Notary Office':
                        header('Location: supervisor/dashboard.php');
                        break;
                    case 'Notary':
                        header('Location: notary/dashboard.php');
                        break;
                    default:
                        header('Location: index.php');
                }
                exit;
            }
        } else {
            $error_message = "اسم المستخدم أو كلمة المرور غير صحيحة.";
        }
    } else {
        $error_message = "يرجى إدخال اسم المستخدم وكلمة المرور.";
    }
}

// التحقق من تسجيل الدخول المسبق
if (isset($_SESSION['user_id'])) {
    // التحقق من انتهاء الجلسة
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        header('Location: index.php?timeout=1');
        exit;
    }
    
    $_SESSION['last_activity'] = time();
    
    // توجيه المستخدم حسب دوره
    switch ($_SESSION['role_name']) {
        case 'Administrator':
            header('Location: admin/dashboard.php');
            break;
        case 'Head of Notary Office':
            header('Location: supervisor/dashboard.php');
            break;
        case 'Notary':
            header('Location: notary/dashboard.php');
            break;
    }
    exit;
}

// رسالة انتهاء الجلسة
if (isset($_GET['timeout'])) {
    $info_message = "انتهت جلستك. يرجى تسجيل الدخول مرة أخرى.";
}

// رسالة تسجيل الخروج
if (isset($_GET['logout'])) {
    $info_message = "تم تسجيل خروجك بنجاح.";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SYSTEM_NAME; ?> - تسجيل الدخول</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <img src="assets/images/logo.png" alt="شعار النظام" onerror="this.style.display='none'">
                </div>
                <h1><?php echo SYSTEM_NAME; ?></h1>
                <p>وزارة العدل - الجمهورية اليمنية</p>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="icon-error"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($info_message)): ?>
                <div class="alert alert-info">
                    <i class="icon-info"></i>
                    <?php echo $info_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="username">اسم المستخدم</label>
                    <div class="input-group">
                        <i class="icon-user"></i>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               placeholder="أدخل اسم المستخدم">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <div class="input-group">
                        <i class="icon-lock"></i>
                        <input type="password" id="password" name="password" required 
                               placeholder="أدخل كلمة المرور">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="icon-eye" id="eye-icon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-container">
                        <input type="checkbox" name="remember_me">
                        <span class="checkmark"></span>
                        تذكرني
                    </label>
                    <a href="#" class="forgot-password">نسيت كلمة المرور؟</a>
                </div>
                
                <button type="submit" name="login" class="login-btn">
                    <i class="icon-login"></i>
                    تسجيل الدخول
                </button>
            </form>
            
            <div class="login-footer">
                <p>النسخة <?php echo SYSTEM_VERSION; ?></p>
                <div class="support-links">
                    <a href="#" onclick="showHelp()">المساعدة</a>
                    <span>|</span>
                    <a href="#" onclick="showContact()">التواصل</a>
                </div>
            </div>
        </div>
        
        <div class="background-pattern"></div>
    </div>
    
    <!-- نافذة المساعدة -->
    <div id="helpModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>المساعدة</h3>
                <span class="close" onclick="closeModal('helpModal')">&times;</span>
            </div>
            <div class="modal-body">
                <h4>كيفية تسجيل الدخول:</h4>
                <ol>
                    <li>أدخل اسم المستخدم الخاص بك</li>
                    <li>أدخل كلمة المرور</li>
                    <li>اضغط على زر "تسجيل الدخول"</li>
                </ol>
                
                <h4>في حالة نسيان كلمة المرور:</h4>
                <p>يرجى التواصل مع مدير النظام أو رئيس قلم التوثيق لإعادة تعيين كلمة المرور.</p>
                
                <h4>متطلبات النظام:</h4>
                <ul>
                    <li>متصفح حديث يدعم JavaScript</li>
                    <li>اتصال بالإنترنت</li>
                    <li>دقة شاشة لا تقل عن 1024x768</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- نافذة التواصل -->
    <div id="contactModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>معلومات التواصل</h3>
                <span class="close" onclick="closeModal('contactModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="icon-phone"></i>
                        <div>
                            <strong>الهاتف:</strong>
                            <p>+967-1-123456</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <i class="icon-email"></i>
                        <div>
                            <strong>البريد الإلكتروني:</strong>
                            <p>support@notary.gov.ye</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <i class="icon-location"></i>
                        <div>
                            <strong>العنوان:</strong>
                            <p>وزارة العدل - صنعاء - الجمهورية اليمنية</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <i class="icon-clock"></i>
                        <div>
                            <strong>ساعات العمل:</strong>
                            <p>الأحد - الخميس: 8:00 ص - 3:00 م</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/login.js"></script>
</body>
</html>

