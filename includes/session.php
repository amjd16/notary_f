<?php
/**
 * ملف إدارة الجلسات
 * Session Management File
 * 
 * يحتوي على وظائف إدارة جلسات المستخدمين
 * Contains user session management functions
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

class SessionManager {
    
    /**
     * بدء الجلسة
     * Start session
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            // إعدادات الجلسة الآمنة
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
            
            session_start();
            
            // تجديد معرف الجلسة دورياً لمنع session hijacking
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - $_SESSION['created'] > 1800) { // 30 دقيقة
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    /**
     * تسجيل دخول المستخدم
     * Login user
     */
    public static function login($user) {
        self::start();
        
        // تجديد معرف الجلسة لمنع session fixation
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['district_id'] = $user['district_id'];
        $_SESSION['village_id'] = $user['village_id'];
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        
        // إنشاء رمز CSRF
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        return true;
    }
    
    /**
     * تسجيل خروج المستخدم
     * Logout user
     */
    public static function logout() {
        self::start();
        
        // تسجيل عملية الخروج
        if (isset($_SESSION['user_id'])) {
            logActivity($_SESSION['user_id'], 'logout');
        }
        
        // مسح جميع متغيرات الجلسة
        $_SESSION = array();
        
        // حذف كوكيز الجلسة
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // تدمير الجلسة
        session_destroy();
        
        return true;
    }
    
    /**
     * التحقق من تسجيل الدخول
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        self::start();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        // التحقق من انتهاء الجلسة
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            self::logout();
            return false;
        }
        
        // التحقق من تغيير IP (اختياري)
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            // يمكن تفعيل هذا للحماية الإضافية
            // self::logout();
            // return false;
        }
        
        // تحديث وقت آخر نشاط
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * التحقق من الصلاحيات
     * Check user permissions
     */
    public static function hasRole($requiredRole) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        $userRole = $_SESSION['role_name'];
        
        // مدير النظام له صلاحيات كاملة
        if ($userRole === 'Administrator') {
            return true;
        }
        
        // التحقق من الدور المطلوب
        if (is_array($requiredRole)) {
            return in_array($userRole, $requiredRole);
        }
        
        return $userRole === $requiredRole;
    }
    
    /**
     * الحصول على معلومات المستخدم الحالي
     * Get current user info
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role_name' => $_SESSION['role_name'],
            'role_id' => $_SESSION['role_id'],
            'full_name' => $_SESSION['full_name'],
            'district_id' => $_SESSION['district_id'] ?? null,
            'village_id' => $_SESSION['village_id'] ?? null,
            'login_time' => $_SESSION['login_time'],
            'last_activity' => $_SESSION['last_activity']
        ];
    }
    
    /**
     * تعيين رسالة فلاش
     * Set flash message
     */
    public static function setFlash($type, $message) {
        self::start();
        $_SESSION['flash'][$type] = $message;
    }
    
    /**
     * الحصول على رسالة فلاش
     * Get flash message
     */
    public static function getFlash($type) {
        self::start();
        
        if (isset($_SESSION['flash'][$type])) {
            $message = $_SESSION['flash'][$type];
            unset($_SESSION['flash'][$type]);
            return $message;
        }
        
        return null;
    }
    
    /**
     * التحقق من وجود رسالة فلاش
     * Check if flash message exists
     */
    public static function hasFlash($type) {
        self::start();
        return isset($_SESSION['flash'][$type]);
    }
    
    /**
     * مسح جميع رسائل الفلاش
     * Clear all flash messages
     */
    public static function clearFlash() {
        self::start();
        unset($_SESSION['flash']);
    }
    
    /**
     * تعيين قيمة في الجلسة
     * Set session value
     */
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    /**
     * الحصول على قيمة من الجلسة
     * Get session value
     */
    public static function get($key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * حذف قيمة من الجلسة
     * Remove session value
     */
    public static function remove($key) {
        self::start();
        unset($_SESSION[$key]);
    }
    
    /**
     * التحقق من وجود قيمة في الجلسة
     * Check if session value exists
     */
    public static function has($key) {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    /**
     * الحصول على معرف الجلسة
     * Get session ID
     */
    public static function getId() {
        self::start();
        return session_id();
    }
    
    /**
     * تجديد معرف الجلسة
     * Regenerate session ID
     */
    public static function regenerateId($deleteOld = true) {
        self::start();
        return session_regenerate_id($deleteOld);
    }
    
    /**
     * الحصول على وقت انتهاء الجلسة
     * Get session expiry time
     */
    public static function getExpiryTime() {
        if (!self::isLoggedIn()) {
            return 0;
        }
        
        return $_SESSION['last_activity'] + SESSION_TIMEOUT;
    }
    
    /**
     * الحصول على الوقت المتبقي للجلسة
     * Get remaining session time
     */
    public static function getRemainingTime() {
        if (!self::isLoggedIn()) {
            return 0;
        }
        
        $remaining = self::getExpiryTime() - time();
        return max(0, $remaining);
    }
    
    /**
     * التحقق من اقتراب انتهاء الجلسة
     * Check if session is about to expire
     */
    public static function isAboutToExpire($warningTime = 300) { // 5 دقائق
        return self::getRemainingTime() <= $warningTime;
    }
    
    /**
     * تمديد الجلسة
     * Extend session
     */
    public static function extend() {
        if (self::isLoggedIn()) {
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    
    /**
     * الحصول على إحصائيات الجلسة
     * Get session statistics
     */
    public static function getStats() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        $loginTime = $_SESSION['login_time'];
        $lastActivity = $_SESSION['last_activity'];
        $currentTime = time();
        
        return [
            'login_time' => $loginTime,
            'last_activity' => $lastActivity,
            'session_duration' => $currentTime - $loginTime,
            'idle_time' => $currentTime - $lastActivity,
            'remaining_time' => self::getRemainingTime(),
            'expires_at' => self::getExpiryTime()
        ];
    }
}

// دوال مساعدة سريعة
function isLoggedIn() {
    return SessionManager::isLoggedIn();
}

function hasRole($role) {
    return SessionManager::hasRole($role);
}

function getCurrentUser() {
    return SessionManager::getCurrentUser();
}

function setFlash($type, $message) {
    SessionManager::setFlash($type, $message);
}

function getFlash($type) {
    return SessionManager::getFlash($type);
}

function hasFlash($type) {
    return SessionManager::hasFlash($type);
}
?>

