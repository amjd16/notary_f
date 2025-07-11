<?php
/**
 * ملف حماية الصفحات
 * Page Guard File
 * 
 * يحتوي على وظائف حماية الصفحات والتحقق من الصلاحيات
 * Contains page protection and permission checking functions
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

class PageGuard {
    
    private $roleManager;
    private $currentPage;
    private $requiredRole;
    private $requiredPermission;
    
    public function __construct($roleManager) {
        $this->roleManager = $roleManager;
        $this->currentPage = $this->getCurrentPage();
    }
    
    /**
     * حماية الصفحة بدور معين
     * Protect page with specific role
     */
    public function requireRole($role) {
        $this->requiredRole = $role;
        return $this->checkAccess();
    }
    
    /**
     * حماية الصفحة بصلاحية معينة
     * Protect page with specific permission
     */
    public function requirePermission($permission) {
        $this->requiredPermission = $permission;
        return $this->checkAccess();
    }
    
    /**
     * حماية الصفحة بتسجيل الدخول فقط
     * Protect page with login only
     */
    public function requireLogin() {
        return $this->checkLogin();
    }
    
    /**
     * التحقق من الوصول
     * Check access
     */
    private function checkAccess() {
        // التحقق من تسجيل الدخول أولاً
        if (!$this->checkLogin()) {
            return false;
        }
        
        $user = getCurrentUser();
        
        // التحقق من الدور المطلوب
        if ($this->requiredRole && !hasRole($this->requiredRole)) {
            $this->denyAccess('ليس لديك الصلاحية للوصول لهذه الصفحة');
            return false;
        }
        
        // التحقق من الصلاحية المطلوبة
        if ($this->requiredPermission && !$this->roleManager->hasPermission($user['user_id'], $this->requiredPermission)) {
            $this->denyAccess('ليس لديك الصلاحية لتنفيذ هذه العملية');
            return false;
        }
        
        // تسجيل الوصول
        $this->logAccess($user);
        
        return true;
    }
    
    /**
     * التحقق من تسجيل الدخول
     * Check login
     */
    private function checkLogin() {
        if (!isLoggedIn()) {
            $this->redirectToLogin();
            return false;
        }
        
        return true;
    }
    
    /**
     * إعادة التوجيه لصفحة تسجيل الدخول
     * Redirect to login page
     */
    private function redirectToLogin() {
        // حفظ الصفحة المطلوبة للعودة إليها بعد تسجيل الدخول
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        if (isAjaxRequest()) {
            jsonResponse([
                'success' => false,
                'message' => 'انتهت صلاحية الجلسة',
                'redirect' => 'index.php'
            ]);
        } else {
            redirect('index.php');
        }
    }
    
    /**
     * رفض الوصول
     * Deny access
     */
    private function denyAccess($message = 'ليس لديك الصلاحية للوصول لهذه الصفحة') {
        // تسجيل محاولة الوصول غير المصرح بها
        $this->logUnauthorizedAccess();
        
        if (isAjaxRequest()) {
            jsonResponse([
                'success' => false,
                'message' => $message,
                'error_code' => 'ACCESS_DENIED'
            ]);
        } else {
            // عرض صفحة خطأ 403
            http_response_code(403);
            include 'templates/error_403.php';
            exit;
        }
    }
    
    /**
     * تسجيل الوصول
     * Log access
     */
    private function logAccess($user) {
        logActivity(
            $user['user_id'],
            'page_access',
            "Accessed page: {$this->currentPage}",
            'page',
            null
        );
    }
    
    /**
     * تسجيل محاولة الوصول غير المصرح بها
     * Log unauthorized access attempt
     */
    private function logUnauthorizedAccess() {
        $user = getCurrentUser();
        $userId = $user ? $user['user_id'] : null;
        $username = $user ? $user['username'] : 'guest';
        
        logSecurity(
            'unauthorized_access_attempt',
            'medium',
            "User $username attempted to access {$this->currentPage} without permission"
        );
        
        if ($userId) {
            logActivity(
                $userId,
                'unauthorized_access',
                "Attempted to access: {$this->currentPage}",
                'page',
                null
            );
        }
    }
    
    /**
     * الحصول على الصفحة الحالية
     * Get current page
     */
    private function getCurrentPage() {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        
        // إزالة المعاملات من الرابط
        $page = parse_url($requestUri, PHP_URL_PATH);
        
        return basename($page);
    }
    
    /**
     * التحقق من الوصول للمورد
     * Check resource access
     */
    public function checkResourceAccess($resourceType, $resourceId) {
        if (!isLoggedIn()) {
            return false;
        }
        
        $user = getCurrentUser();
        
        // مدير النظام يمكنه الوصول لكل شيء
        if ($user['role_name'] === 'Administrator') {
            return true;
        }
        
        return $this->roleManager->canAccessResource($user['user_id'], $resourceType, $resourceId);
    }
    
    /**
     * التحقق من الوصول للعقد
     * Check contract access
     */
    public function checkContractAccess($contractId) {
        return $this->checkResourceAccess('contract', $contractId);
    }
    
    /**
     * التحقق من الوصول للمستخدم
     * Check user access
     */
    public function checkUserAccess($userId) {
        return $this->checkResourceAccess('user', $userId);
    }
    
    /**
     * التحقق من الوصول للمديرية
     * Check district access
     */
    public function checkDistrictAccess($districtId) {
        return $this->checkResourceAccess('district', $districtId);
    }
    
    /**
     * حماية API
     * Protect API
     */
    public function protectAPI($requiredPermission = null) {
        // التحقق من تسجيل الدخول
        if (!isLoggedIn()) {
            jsonResponse([
                'success' => false,
                'message' => 'يجب تسجيل الدخول للوصول لهذه الخدمة',
                'error_code' => 'AUTHENTICATION_REQUIRED'
            ], 401);
        }
        
        // التحقق من الصلاحية إذا كانت مطلوبة
        if ($requiredPermission) {
            $user = getCurrentUser();
            if (!$this->roleManager->hasPermission($user['user_id'], $requiredPermission)) {
                jsonResponse([
                    'success' => false,
                    'message' => 'ليس لديك الصلاحية لاستخدام هذه الخدمة',
                    'error_code' => 'PERMISSION_DENIED'
                ], 403);
            }
        }
        
        return true;
    }
    
    /**
     * التحقق من رمز CSRF
     * Check CSRF token
     */
    public function checkCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            
            if (!verifyCsrfToken($token)) {
                if (isAjaxRequest()) {
                    jsonResponse([
                        'success' => false,
                        'message' => 'رمز الأمان غير صحيح',
                        'error_code' => 'CSRF_TOKEN_INVALID'
                    ], 403);
                } else {
                    $this->denyAccess('رمز الأمان غير صحيح');
                }
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * التحقق من معدل الطلبات
     * Check rate limiting
     */
    public function checkRateLimit($maxRequests = 100, $timeWindow = 3600) {
        $user = getCurrentUser();
        $identifier = $user ? $user['user_id'] : $_SERVER['REMOTE_ADDR'];
        
        $cacheKey = "rate_limit_" . md5($identifier);
        
        // استخدام ملف مؤقت للتخزين المؤقت (يمكن استبداله بـ Redis أو Memcached)
        $cacheFile = sys_get_temp_dir() . '/' . $cacheKey;
        
        $requests = [];
        if (file_exists($cacheFile)) {
            $requests = json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        
        // تنظيف الطلبات القديمة
        $currentTime = time();
        $requests = array_filter($requests, function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        });
        
        // التحقق من الحد الأقصى
        if (count($requests) >= $maxRequests) {
            if (isAjaxRequest()) {
                jsonResponse([
                    'success' => false,
                    'message' => 'تم تجاوز الحد الأقصى للطلبات. يرجى المحاولة لاحقاً',
                    'error_code' => 'RATE_LIMIT_EXCEEDED'
                ], 429);
            } else {
                $this->denyAccess('تم تجاوز الحد الأقصى للطلبات');
            }
            return false;
        }
        
        // إضافة الطلب الحالي
        $requests[] = $currentTime;
        file_put_contents($cacheFile, json_encode($requests));
        
        return true;
    }
    
    /**
     * حماية شاملة للصفحة
     * Comprehensive page protection
     */
    public function protect($options = []) {
        // الخيارات الافتراضية
        $defaults = [
            'require_login' => true,
            'required_role' => null,
            'required_permission' => null,
            'check_csrf' => true,
            'rate_limit' => false,
            'max_requests' => 100,
            'time_window' => 3600
        ];
        
        $options = array_merge($defaults, $options);
        
        // التحقق من تسجيل الدخول
        if ($options['require_login'] && !$this->checkLogin()) {
            return false;
        }
        
        // التحقق من الدور
        if ($options['required_role'] && !hasRole($options['required_role'])) {
            $this->denyAccess();
            return false;
        }
        
        // التحقق من الصلاحية
        if ($options['required_permission']) {
            $user = getCurrentUser();
            if (!$this->roleManager->hasPermission($user['user_id'], $options['required_permission'])) {
                $this->denyAccess();
                return false;
            }
        }
        
        // التحقق من رمز CSRF
        if ($options['check_csrf'] && !$this->checkCSRF()) {
            return false;
        }
        
        // التحقق من معدل الطلبات
        if ($options['rate_limit'] && !$this->checkRateLimit($options['max_requests'], $options['time_window'])) {
            return false;
        }
        
        // تسجيل الوصول الناجح
        if ($options['require_login']) {
            $this->logAccess(getCurrentUser());
        }
        
        return true;
    }
    
    /**
     * إنشاء middleware للحماية
     * Create protection middleware
     */
    public static function middleware($options = []) {
        global $roleManager;
        
        $guard = new self($roleManager);
        return $guard->protect($options);
    }
}

// دوال مساعدة سريعة
function requireLogin() {
    global $roleManager;
    $guard = new PageGuard($roleManager);
    return $guard->requireLogin();
}

function requireRole($role) {
    global $roleManager;
    $guard = new PageGuard($roleManager);
    return $guard->requireRole($role);
}

function requirePermission($permission) {
    global $roleManager;
    $guard = new PageGuard($roleManager);
    return $guard->requirePermission($permission);
}

function protectAPI($requiredPermission = null) {
    global $roleManager;
    $guard = new PageGuard($roleManager);
    return $guard->protectAPI($requiredPermission);
}

function checkResourceAccess($resourceType, $resourceId) {
    global $roleManager;
    $guard = new PageGuard($roleManager);
    return $guard->checkResourceAccess($resourceType, $resourceId);
}

function protectPage($options = []) {
    return PageGuard::middleware($options);
}
?>

