<?php
/**
 * ملف التحقق من الصلاحيات والمصادقة
 * Authentication and Authorization File
 * 
 * يحتوي على وظائف التحقق من الهوية والصلاحيات
 * Contains authentication and authorization functions
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

class Auth {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    /**
     * تسجيل دخول المستخدم
     * Authenticate user login
     */
    public function login($username, $password, $rememberMe = false) {
        try {
            // البحث عن المستخدم
            $user = $this->database->selectOne(
                "SELECT u.*, r.role_name, l.status as license_status, l.expiry_date as license_expiry
                 FROM users u 
                 JOIN roles r ON u.role_id = r.role_id 
                 LEFT JOIN licenses l ON u.license_id = l.license_id 
                 WHERE u.username = ? AND u.is_active = 1",
                [$username]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'اسم المستخدم غير موجود أو غير نشط'];
            }
            
            // التحقق من كلمة المرور
            if (!password_verify($password, $user['password_hash'])) {
                // تسجيل محاولة دخول فاشلة
                $this->logFailedLogin($username);
                return ['success' => false, 'message' => 'كلمة المرور غير صحيحة'];
            }
            
            // التحقق من حالة الترخيص للأمناء
            if ($user['role_name'] === 'Notary') {
                if ($user['license_status'] !== 'active') {
                    return ['success' => false, 'message' => 'ترخيصك غير نشط. يرجى التواصل مع الإدارة'];
                }
                
                // التحقق من انتهاء الترخيص
                if ($user['license_expiry'] && strtotime($user['license_expiry']) < time()) {
                    return ['success' => false, 'message' => 'انتهت صلاحية ترخيصك. يرجى التواصل مع الإدارة'];
                }
            }
            
            // تسجيل دخول ناجح
            SessionManager::login($user);
            
            // تحديث آخر دخول
            $this->database->update(
                "UPDATE users SET last_login = NOW() WHERE user_id = ?",
                [$user['user_id']]
            );
            
            // تسجيل عملية الدخول
            $this->logSuccessfulLogin($user['user_id']);
            
            // معالجة "تذكرني"
            if ($rememberMe) {
                $this->setRememberMeCookie($user['user_id']);
            }
            
            return [
                'success' => true, 
                'message' => 'تم تسجيل الدخول بنجاح',
                'user' => $user,
                'redirect' => $this->getRedirectUrl($user['role_name'])
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تسجيل الدخول'];
        }
    }
    
    /**
     * تسجيل خروج المستخدم
     * Logout user
     */
    public function logout() {
        // حذف كوكيز "تذكرني"
        $this->clearRememberMeCookie();
        
        // تسجيل خروج الجلسة
        SessionManager::logout();
        
        return ['success' => true, 'message' => 'تم تسجيل الخروج بنجاح'];
    }
    
    /**
     * التحقق من كوكيز "تذكرني"
     * Check remember me cookie
     */
    public function checkRememberMe() {
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }
        
        $token = $_COOKIE['remember_token'];
        
        // البحث عن الرمز في قاعدة البيانات
        $user = $this->database->selectOne(
            "SELECT u.*, r.role_name FROM users u 
             JOIN roles r ON u.role_id = r.role_id 
             WHERE u.remember_token = ? AND u.is_active = 1",
            [$token]
        );
        
        if ($user) {
            // تسجيل دخول تلقائي
            SessionManager::login($user);
            
            // تجديد الرمز
            $this->setRememberMeCookie($user['user_id']);
            
            return true;
        }
        
        // حذف الكوكيز إذا كان الرمز غير صالح
        $this->clearRememberMeCookie();
        return false;
    }
    
    /**
     * تعيين كوكيز "تذكرني"
     * Set remember me cookie
     */
    private function setRememberMeCookie($userId) {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + (30 * 24 * 60 * 60); // 30 يوم
        
        // حفظ الرمز في قاعدة البيانات
        $this->database->update(
            "UPDATE users SET remember_token = ? WHERE user_id = ?",
            [$token, $userId]
        );
        
        // تعيين الكوكيز
        setcookie('remember_token', $token, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
    }
    
    /**
     * حذف كوكيز "تذكرني"
     * Clear remember me cookie
     */
    private function clearRememberMeCookie() {
        if (isset($_COOKIE['remember_token'])) {
            // حذف الرمز من قاعدة البيانات
            $this->database->update(
                "UPDATE users SET remember_token = NULL WHERE remember_token = ?",
                [$_COOKIE['remember_token']]
            );
            
            // حذف الكوكيز
            setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        }
    }
    
    /**
     * تسجيل محاولة دخول فاشلة
     * Log failed login attempt
     */
    private function logFailedLogin($username) {
        $this->database->insert(
            "INSERT INTO log_access (user_id, action, ip_address, user_agent, details) VALUES (NULL, ?, ?, ?, ?)",
            [
                'failed_login',
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                "Failed login attempt for username: $username"
            ]
        );
    }
    
    /**
     * تسجيل دخول ناجح
     * Log successful login
     */
    private function logSuccessfulLogin($userId) {
        $this->database->insert(
            "INSERT INTO log_access (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)",
            [
                $userId,
                'login',
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]
        );
    }
    
    /**
     * الحصول على رابط إعادة التوجيه حسب الدور
     * Get redirect URL based on role
     */
    private function getRedirectUrl($roleName) {
        switch ($roleName) {
            case 'Administrator':
                return 'admin/dashboard.php';
            case 'Head of Notary Office':
                return 'supervisor/dashboard.php';
            case 'Notary':
                return 'notary/dashboard.php';
            default:
                return 'index.php';
        }
    }
    
    /**
     * تغيير كلمة المرور
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // التحقق من كلمة المرور الحالية
            $user = $this->database->selectOne(
                "SELECT password_hash FROM users WHERE user_id = ?",
                [$userId]
            );
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة'];
            }
            
            // التحقق من قوة كلمة المرور الجديدة
            $validation = validatePassword($newPassword);
            if ($validation !== true) {
                return ['success' => false, 'message' => implode('<br>', $validation)];
            }
            
            // تحديث كلمة المرور
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $result = $this->database->update(
                "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?",
                [$newPasswordHash, $userId]
            );
            
            if ($result) {
                // تسجيل العملية
                logActivity($userId, 'password_changed');
                
                return ['success' => true, 'message' => 'تم تغيير كلمة المرور بنجاح'];
            } else {
                return ['success' => false, 'message' => 'فشل في تغيير كلمة المرور'];
            }
            
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تغيير كلمة المرور'];
        }
    }
    
    /**
     * إعادة تعيين كلمة المرور
     * Reset password
     */
    public function resetPassword($email) {
        try {
            // البحث عن المستخدم
            $user = $this->database->selectOne(
                "SELECT user_id, username, first_name, last_name FROM users WHERE email = ? AND is_active = 1",
                [$email]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'البريد الإلكتروني غير موجود'];
            }
            
            // إنشاء كلمة مرور جديدة
            $newPassword = generateRandomPassword(12);
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // تحديث كلمة المرور
            $result = $this->database->update(
                "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?",
                [$newPasswordHash, $user['user_id']]
            );
            
            if ($result) {
                // تسجيل العملية
                logActivity($user['user_id'], 'password_reset');
                
                // إرسال كلمة المرور الجديدة (في التطبيق الحقيقي يجب إرسالها عبر البريد الإلكتروني)
                return [
                    'success' => true, 
                    'message' => 'تم إعادة تعيين كلمة المرور بنجاح',
                    'new_password' => $newPassword // في التطبيق الحقيقي لا يجب إرجاع كلمة المرور
                ];
            } else {
                return ['success' => false, 'message' => 'فشل في إعادة تعيين كلمة المرور'];
            }
            
        } catch (Exception $e) {
            error_log("Reset password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء إعادة تعيين كلمة المرور'];
        }
    }
    
    /**
     * التحقق من الصلاحيات المتقدمة
     * Check advanced permissions
     */
    public function checkPermission($action, $resource = null) {
        $user = getCurrentUser();
        if (!$user) {
            return false;
        }
        
        $role = $user['role_name'];
        
        // صلاحيات مدير النظام
        if ($role === 'Administrator') {
            return true;
        }
        
        // صلاحيات رئيس قلم التوثيق
        if ($role === 'Head of Notary Office') {
            $supervisorPermissions = [
                'view_notaries', 'view_contracts', 'review_contracts',
                'view_reports', 'send_notifications', 'view_performance'
            ];
            return in_array($action, $supervisorPermissions);
        }
        
        // صلاحيات الأمين الشرعي
        if ($role === 'Notary') {
            $notaryPermissions = [
                'create_contract', 'edit_own_contract', 'view_own_contracts',
                'submit_contract', 'view_own_reports', 'update_profile'
            ];
            return in_array($action, $notaryPermissions);
        }
        
        return false;
    }
    
    /**
     * التحقق من الوصول للمورد
     * Check resource access
     */
    public function canAccessResource($resourceType, $resourceId) {
        $user = getCurrentUser();
        if (!$user) {
            return false;
        }
        
        // مدير النظام يمكنه الوصول لكل شيء
        if ($user['role_name'] === 'Administrator') {
            return true;
        }
        
        // التحقق حسب نوع المورد
        switch ($resourceType) {
            case 'contract':
                return $this->canAccessContract($resourceId, $user);
            case 'user':
                return $this->canAccessUser($resourceId, $user);
            case 'district':
                return $this->canAccessDistrict($resourceId, $user);
            default:
                return false;
        }
    }
    
    /**
     * التحقق من الوصول للعقد
     * Check contract access
     */
    private function canAccessContract($contractId, $user) {
        $contract = $this->database->selectOne(
            "SELECT notary_id, district_id FROM contracts WHERE contract_id = ?",
            [$contractId]
        );
        
        if (!$contract) {
            return false;
        }
        
        // الأمين يمكنه الوصول لعقوده فقط
        if ($user['role_name'] === 'Notary') {
            return $contract['notary_id'] == $user['user_id'];
        }
        
        // رئيس القلم يمكنه الوصول لعقود مديريته
        if ($user['role_name'] === 'Head of Notary Office') {
            return $contract['district_id'] == $user['district_id'];
        }
        
        return false;
    }
    
    /**
     * التحقق من الوصول للمستخدم
     * Check user access
     */
    private function canAccessUser($userId, $user) {
        // المستخدم يمكنه الوصول لملفه الشخصي
        if ($userId == $user['user_id']) {
            return true;
        }
        
        // رئيس القلم يمكنه الوصول للأمناء في مديريته
        if ($user['role_name'] === 'Head of Notary Office') {
            $targetUser = $this->database->selectOne(
                "SELECT district_id, role_id FROM users WHERE user_id = ?",
                [$userId]
            );
            
            return $targetUser && 
                   $targetUser['district_id'] == $user['district_id'] && 
                   $targetUser['role_id'] == 3; // دور الأمين
        }
        
        return false;
    }
    
    /**
     * التحقق من الوصول للمديرية
     * Check district access
     */
    private function canAccessDistrict($districtId, $user) {
        // رئيس القلم والأمين يمكنهما الوصول لمديريتهما فقط
        if (in_array($user['role_name'], ['Head of Notary Office', 'Notary'])) {
            return $districtId == $user['district_id'];
        }
        
        return false;
    }
}

// إنشاء مثيل عام للمصادقة
$auth = new Auth($database);
?>

