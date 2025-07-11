<?php
/**
 * ملف معالجة تسجيل الدخول والخروج
 * Login/Logout Handler File
 * 
 * يحتوي على معالجات تسجيل الدخول والخروج
 * Contains login and logout handlers
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

// تضمين الملفات المطلوبة
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

class LoginHandler {
    
    private $auth;
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 دقيقة
    
    public function __construct($auth) {
        $this->auth = $auth;
    }
    
    /**
     * معالجة طلب تسجيل الدخول
     * Handle login request
     */
    public function handleLogin() {
        try {
            // التحقق من طريقة الطلب
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return $this->jsonResponse(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
            }
            
            // التحقق من رمز CSRF
            if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                return $this->jsonResponse(['success' => false, 'message' => 'رمز الأمان غير صحيح']);
            }
            
            // الحصول على البيانات
            $username = sanitizeInput($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $rememberMe = isset($_POST['remember_me']);
            
            // التحقق من البيانات المطلوبة
            if (empty($username) || empty($password)) {
                return $this->jsonResponse(['success' => false, 'message' => 'اسم المستخدم وكلمة المرور مطلوبان']);
            }
            
            // التحقق من محاولات الدخول
            if ($this->isAccountLocked($username)) {
                $remainingTime = $this->getRemainingLockoutTime($username);
                return $this->jsonResponse([
                    'success' => false, 
                    'message' => "الحساب مقفل مؤقتاً. المحاولة مرة أخرى خلال $remainingTime دقيقة"
                ]);
            }
            
            // محاولة تسجيل الدخول
            $result = $this->auth->login($username, $password, $rememberMe);
            
            if ($result['success']) {
                // مسح محاولات الدخول الفاشلة
                $this->clearFailedAttempts($username);
                
                // تسجيل نجاح الدخول
                Logger::logLogin($username, true);
                
                return $this->jsonResponse($result);
            } else {
                // تسجيل محاولة فاشلة
                $this->recordFailedAttempt($username);
                Logger::logLogin($username, false, $result['message']);
                
                return $this->jsonResponse($result);
            }
            
        } catch (Exception $e) {
            error_log("Login handler error: " . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'message' => 'حدث خطأ أثناء تسجيل الدخول']);
        }
    }
    
    /**
     * معالجة طلب تسجيل الخروج
     * Handle logout request
     */
    public function handleLogout() {
        try {
            $result = $this->auth->logout();
            
            if ($result['success']) {
                // إعادة توجيه للصفحة الرئيسية
                if (isAjaxRequest()) {
                    return $this->jsonResponse(['success' => true, 'redirect' => 'index.php']);
                } else {
                    redirect('index.php');
                }
            } else {
                return $this->jsonResponse($result);
            }
            
        } catch (Exception $e) {
            error_log("Logout handler error: " . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'message' => 'حدث خطأ أثناء تسجيل الخروج']);
        }
    }
    
    /**
     * معالجة طلب إعادة تعيين كلمة المرور
     * Handle password reset request
     */
    public function handlePasswordReset() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return $this->jsonResponse(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
            }
            
            // التحقق من رمز CSRF
            if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                return $this->jsonResponse(['success' => false, 'message' => 'رمز الأمان غير صحيح']);
            }
            
            $email = sanitizeInput($_POST['email'] ?? '');
            
            if (empty($email)) {
                return $this->jsonResponse(['success' => false, 'message' => 'البريد الإلكتروني مطلوب']);
            }
            
            if (!validateEmail($email)) {
                return $this->jsonResponse(['success' => false, 'message' => 'البريد الإلكتروني غير صحيح']);
            }
            
            $result = $this->auth->resetPassword($email);
            
            // تسجيل العملية
            if ($result['success']) {
                logSecurity('password_reset_requested', 'medium', "Password reset requested for email: $email");
            }
            
            return $this->jsonResponse($result);
            
        } catch (Exception $e) {
            error_log("Password reset handler error: " . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'message' => 'حدث خطأ أثناء إعادة تعيين كلمة المرور']);
        }
    }
    
    /**
     * معالجة طلب تغيير كلمة المرور
     * Handle password change request
     */
    public function handlePasswordChange() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return $this->jsonResponse(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
            }
            
            // التحقق من تسجيل الدخول
            if (!isLoggedIn()) {
                return $this->jsonResponse(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
            }
            
            // التحقق من رمز CSRF
            if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                return $this->jsonResponse(['success' => false, 'message' => 'رمز الأمان غير صحيح']);
            }
            
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // التحقق من البيانات
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                return $this->jsonResponse(['success' => false, 'message' => 'جميع الحقول مطلوبة']);
            }
            
            if ($newPassword !== $confirmPassword) {
                return $this->jsonResponse(['success' => false, 'message' => 'كلمة المرور الجديدة غير متطابقة']);
            }
            
            $userId = $_SESSION['user_id'];
            $result = $this->auth->changePassword($userId, $currentPassword, $newPassword);
            
            return $this->jsonResponse($result);
            
        } catch (Exception $e) {
            error_log("Password change handler error: " . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'message' => 'حدث خطأ أثناء تغيير كلمة المرور']);
        }
    }
    
    /**
     * التحقق من قفل الحساب
     * Check if account is locked
     */
    private function isAccountLocked($username) {
        $attempts = $this->getFailedAttempts($username);
        
        if (count($attempts) >= $this->maxLoginAttempts) {
            $lastAttempt = end($attempts);
            $timeDiff = time() - strtotime($lastAttempt['timestamp']);
            
            return $timeDiff < $this->lockoutDuration;
        }
        
        return false;
    }
    
    /**
     * الحصول على الوقت المتبقي للقفل
     * Get remaining lockout time
     */
    private function getRemainingLockoutTime($username) {
        $attempts = $this->getFailedAttempts($username);
        
        if (!empty($attempts)) {
            $lastAttempt = end($attempts);
            $timeDiff = time() - strtotime($lastAttempt['timestamp']);
            $remaining = $this->lockoutDuration - $timeDiff;
            
            return max(0, ceil($remaining / 60)); // بالدقائق
        }
        
        return 0;
    }
    
    /**
     * تسجيل محاولة دخول فاشلة
     * Record failed login attempt
     */
    private function recordFailedAttempt($username) {
        global $database;
        
        try {
            $database->insert(
                "INSERT INTO failed_login_attempts (username, ip_address, user_agent, timestamp) VALUES (?, ?, ?, NOW())",
                [
                    $username,
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
            
            // تنظيف المحاولات القديمة
            $this->cleanOldAttempts();
            
        } catch (Exception $e) {
            error_log("Record failed attempt error: " . $e->getMessage());
        }
    }
    
    /**
     * الحصول على محاولات الدخول الفاشلة
     * Get failed login attempts
     */
    private function getFailedAttempts($username) {
        global $database;
        
        try {
            return $database->select(
                "SELECT * FROM failed_login_attempts 
                 WHERE username = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? SECOND) 
                 ORDER BY timestamp DESC",
                [$username, $this->lockoutDuration]
            );
        } catch (Exception $e) {
            error_log("Get failed attempts error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * مسح محاولات الدخول الفاشلة
     * Clear failed login attempts
     */
    private function clearFailedAttempts($username) {
        global $database;
        
        try {
            $database->delete(
                "DELETE FROM failed_login_attempts WHERE username = ?",
                [$username]
            );
        } catch (Exception $e) {
            error_log("Clear failed attempts error: " . $e->getMessage());
        }
    }
    
    /**
     * تنظيف المحاولات القديمة
     * Clean old attempts
     */
    private function cleanOldAttempts() {
        global $database;
        
        try {
            $database->delete(
                "DELETE FROM failed_login_attempts WHERE timestamp < DATE_SUB(NOW(), INTERVAL 1 DAY)"
            );
        } catch (Exception $e) {
            error_log("Clean old attempts error: " . $e->getMessage());
        }
    }
    
    /**
     * إرجاع استجابة JSON
     * Return JSON response
     */
    private function jsonResponse($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * التحقق من كوكيز "تذكرني"
     * Check remember me cookie
     */
    public function checkRememberMe() {
        if (!isLoggedIn()) {
            return $this->auth->checkRememberMe();
        }
        return true;
    }
    
    /**
     * معالجة تمديد الجلسة
     * Handle session extension
     */
    public function handleSessionExtension() {
        try {
            if (!isLoggedIn()) {
                return $this->jsonResponse(['success' => false, 'message' => 'الجلسة منتهية']);
            }
            
            SessionManager::extend();
            
            return $this->jsonResponse([
                'success' => true,
                'message' => 'تم تمديد الجلسة',
                'remaining_time' => SessionManager::getRemainingTime()
            ]);
            
        } catch (Exception $e) {
            error_log("Session extension error: " . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'message' => 'حدث خطأ أثناء تمديد الجلسة']);
        }
    }
    
    /**
     * الحصول على حالة الجلسة
     * Get session status
     */
    public function getSessionStatus() {
        try {
            if (!isLoggedIn()) {
                return $this->jsonResponse([
                    'logged_in' => false,
                    'remaining_time' => 0
                ]);
            }
            
            $stats = SessionManager::getStats();
            
            return $this->jsonResponse([
                'logged_in' => true,
                'remaining_time' => $stats['remaining_time'],
                'about_to_expire' => SessionManager::isAboutToExpire(),
                'user' => getCurrentUser()
            ]);
            
        } catch (Exception $e) {
            error_log("Get session status error: " . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'message' => 'حدث خطأ']);
        }
    }
}

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    $loginHandler = new LoginHandler($auth);
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'login':
            $loginHandler->handleLogin();
            break;
            
        case 'logout':
            $loginHandler->handleLogout();
            break;
            
        case 'reset_password':
            $loginHandler->handlePasswordReset();
            break;
            
        case 'change_password':
            $loginHandler->handlePasswordChange();
            break;
            
        case 'extend_session':
            $loginHandler->handleSessionExtension();
            break;
            
        case 'session_status':
            $loginHandler->getSessionStatus();
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'عملية غير صحيحة']);
    }
}

// التحقق من كوكيز "تذكرني" عند تحميل الصفحة
if (!isLoggedIn()) {
    $loginHandler = new LoginHandler($auth);
    $loginHandler->checkRememberMe();
}
?>

