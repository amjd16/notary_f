<?php
/**
 * ملف إدارة المستخدمين
 * User Management File
 * 
 * يحتوي على وظائف إدارة المستخدمين
 * Contains user management functions
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

class UserManager {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    /**
     * إنشاء مستخدم جديد
     * Create new user
     */
    public function createUser($userData) {
        try {
            // التحقق من البيانات المطلوبة
            $requiredFields = ['username', 'password', 'role_id', 'first_name', 'last_name', 'email'];
            foreach ($requiredFields as $field) {
                if (empty($userData[$field])) {
                    return ['success' => false, 'message' => "الحقل $field مطلوب"];
                }
            }
            
            // التحقق من عدم تكرار اسم المستخدم
            if ($this->usernameExists($userData['username'])) {
                return ['success' => false, 'message' => 'اسم المستخدم موجود مسبقاً'];
            }
            
            // التحقق من عدم تكرار البريد الإلكتروني
            if ($this->emailExists($userData['email'])) {
                return ['success' => false, 'message' => 'البريد الإلكتروني موجود مسبقاً'];
            }
            
            // التحقق من صحة البريد الإلكتروني
            if (!validateEmail($userData['email'])) {
                return ['success' => false, 'message' => 'البريد الإلكتروني غير صحيح'];
            }
            
            // التحقق من قوة كلمة المرور
            $passwordValidation = validatePassword($userData['password']);
            if ($passwordValidation !== true) {
                return ['success' => false, 'message' => implode('<br>', $passwordValidation)];
            }
            
            // تشفير كلمة المرور
            $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // إعداد البيانات للإدراج
            $insertData = [
                'username' => sanitizeInput($userData['username']),
                'password_hash' => $passwordHash,
                'role_id' => (int)$userData['role_id'],
                'first_name' => sanitizeInput($userData['first_name']),
                'last_name' => sanitizeInput($userData['last_name']),
                'email' => sanitizeInput($userData['email']),
                'phone_number' => sanitizeInput($userData['phone_number'] ?? ''),
                'district_id' => !empty($userData['district_id']) ? (int)$userData['district_id'] : null,
                'village_id' => !empty($userData['village_id']) ? (int)$userData['village_id'] : null,
                'is_active' => isset($userData['is_active']) ? (bool)$userData['is_active'] : true
            ];
            
            // إدراج المستخدم
            $userId = $this->database->insert(
                "INSERT INTO users (username, password_hash, role_id, first_name, last_name, email, phone_number, district_id, village_id, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                array_values($insertData)
            );
            
            if ($userId) {
                // تسجيل العملية
                logActivity($_SESSION['user_id'] ?? null, 'create_user', "Created user: {$userData['username']}", 'user', $userId);
                
                // إرسال إشعار للمستخدم الجديد
                sendNotification($userId, 'مرحباً بك في نظام إدارة الأمناء الشرعيين', 'welcome');
                
                return [
                    'success' => true, 
                    'message' => 'تم إنشاء المستخدم بنجاح',
                    'user_id' => $userId
                ];
            } else {
                return ['success' => false, 'message' => 'فشل في إنشاء المستخدم'];
            }
            
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء إنشاء المستخدم'];
        }
    }
    
    /**
     * تحديث بيانات المستخدم
     * Update user data
     */
    public function updateUser($userId, $userData) {
        try {
            // التحقق من وجود المستخدم
            $existingUser = $this->getUserById($userId);
            if (!$existingUser) {
                return ['success' => false, 'message' => 'المستخدم غير موجود'];
            }
            
            // إعداد البيانات للتحديث
            $updateFields = [];
            $updateValues = [];
            
            // الحقول القابلة للتحديث
            $allowedFields = ['first_name', 'last_name', 'email', 'phone_number', 'district_id', 'village_id', 'is_active'];
            
            foreach ($allowedFields as $field) {
                if (isset($userData[$field])) {
                    if ($field === 'email' && !validateEmail($userData[$field])) {
                        return ['success' => false, 'message' => 'البريد الإلكتروني غير صحيح'];
                    }
                    
                    if ($field === 'email' && $this->emailExists($userData[$field], $userId)) {
                        return ['success' => false, 'message' => 'البريد الإلكتروني موجود مسبقاً'];
                    }
                    
                    $updateFields[] = "$field = ?";
                    $updateValues[] = sanitizeInput($userData[$field]);
                }
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'message' => 'لا توجد بيانات للتحديث'];
            }
            
            // إضافة تاريخ التحديث
            $updateFields[] = "updated_at = NOW()";
            $updateValues[] = $userId;
            
            // تنفيذ التحديث
            $result = $this->database->update(
                "UPDATE users SET " . implode(', ', $updateFields) . " WHERE user_id = ?",
                $updateValues
            );
            
            if ($result !== false) {
                // تسجيل العملية
                logActivity($_SESSION['user_id'] ?? null, 'update_user', "Updated user: {$existingUser['username']}", 'user', $userId);
                
                return ['success' => true, 'message' => 'تم تحديث بيانات المستخدم بنجاح'];
            } else {
                return ['success' => false, 'message' => 'فشل في تحديث بيانات المستخدم'];
            }
            
        } catch (Exception $e) {
            error_log("Update user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تحديث بيانات المستخدم'];
        }
    }
    
    /**
     * حذف مستخدم
     * Delete user
     */
    public function deleteUser($userId) {
        try {
            // التحقق من وجود المستخدم
            $user = $this->getUserById($userId);
            if (!$user) {
                return ['success' => false, 'message' => 'المستخدم غير موجود'];
            }
            
            // منع حذف المدير الوحيد
            if ($user['role_name'] === 'Administrator') {
                $adminCount = $this->database->count('users u JOIN roles r ON u.role_id = r.role_id', "r.role_name = 'Administrator' AND u.is_active = 1");
                if ($adminCount <= 1) {
                    return ['success' => false, 'message' => 'لا يمكن حذف المدير الوحيد في النظام'];
                }
            }
            
            // بدء معاملة
            $this->database->beginTransaction();
            
            try {
                // حذف البيانات المرتبطة (أو تعيين null)
                $this->database->update("UPDATE contracts SET notary_id = NULL WHERE notary_id = ?", [$userId]);
                $this->database->update("UPDATE submissions SET reviewer_id = NULL WHERE reviewer_id = ?", [$userId]);
                $this->database->delete("DELETE FROM notifications WHERE user_id = ?", [$userId]);
                $this->database->delete("DELETE FROM performance WHERE notary_id = ?", [$userId]);
                
                // حذف المستخدم
                $result = $this->database->delete("DELETE FROM users WHERE user_id = ?", [$userId]);
                
                if ($result) {
                    $this->database->commit();
                    
                    // تسجيل العملية
                    logActivity($_SESSION['user_id'] ?? null, 'delete_user', "Deleted user: {$user['username']}", 'user', $userId);
                    
                    return ['success' => true, 'message' => 'تم حذف المستخدم بنجاح'];
                } else {
                    $this->database->rollback();
                    return ['success' => false, 'message' => 'فشل في حذف المستخدم'];
                }
                
            } catch (Exception $e) {
                $this->database->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Delete user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء حذف المستخدم'];
        }
    }
    
    /**
     * تفعيل/إلغاء تفعيل المستخدم
     * Activate/Deactivate user
     */
    public function toggleUserStatus($userId) {
        try {
            $user = $this->getUserById($userId);
            if (!$user) {
                return ['success' => false, 'message' => 'المستخدم غير موجود'];
            }
            
            $newStatus = !$user['is_active'];
            $action = $newStatus ? 'تفعيل' : 'إلغاء تفعيل';
            
            $result = $this->database->update(
                "UPDATE users SET is_active = ?, updated_at = NOW() WHERE user_id = ?",
                [$newStatus, $userId]
            );
            
            if ($result !== false) {
                // تسجيل العملية
                logActivity($_SESSION['user_id'] ?? null, 'toggle_user_status', "$action user: {$user['username']}", 'user', $userId);
                
                // إرسال إشعار للمستخدم
                $message = $newStatus ? 'تم تفعيل حسابك' : 'تم إلغاء تفعيل حسابك';
                sendNotification($userId, $message, 'account_status');
                
                return ['success' => true, 'message' => "تم $action المستخدم بنجاح"];
            } else {
                return ['success' => false, 'message' => "فشل في $action المستخدم"];
            }
            
        } catch (Exception $e) {
            error_log("Toggle user status error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تغيير حالة المستخدم'];
        }
    }
    
    /**
     * الحصول على مستخدم بالمعرف
     * Get user by ID
     */
    public function getUserById($userId) {
        return $this->database->selectOne(
            "SELECT u.*, r.role_name, d.district_name, p.province_name, v.village_name, l.status as license_status
             FROM users u 
             JOIN roles r ON u.role_id = r.role_id 
             LEFT JOIN districts d ON u.district_id = d.district_id 
             LEFT JOIN provinces p ON d.province_id = p.province_id 
             LEFT JOIN villages v ON u.village_id = v.village_id 
             LEFT JOIN licenses l ON u.license_id = l.license_id 
             WHERE u.user_id = ?",
            [$userId]
        );
    }
    
    /**
     * الحصول على مستخدم باسم المستخدم
     * Get user by username
     */
    public function getUserByUsername($username) {
        return $this->database->selectOne(
            "SELECT u.*, r.role_name FROM users u 
             JOIN roles r ON u.role_id = r.role_id 
             WHERE u.username = ?",
            [$username]
        );
    }
    
    /**
     * الحصول على قائمة المستخدمين
     * Get users list
     */
    public function getUsers($filters = [], $limit = 50, $offset = 0) {
        $whereConditions = [];
        $params = [];
        
        // تطبيق الفلاتر
        if (!empty($filters['role_id'])) {
            $whereConditions[] = "u.role_id = ?";
            $params[] = $filters['role_id'];
        }
        
        if (!empty($filters['district_id'])) {
            $whereConditions[] = "u.district_id = ?";
            $params[] = $filters['district_id'];
        }
        
        if (!empty($filters['is_active'])) {
            $whereConditions[] = "u.is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // إضافة معاملات الترقيم
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->database->select(
            "SELECT u.*, r.role_name, d.district_name, p.province_name 
             FROM users u 
             JOIN roles r ON u.role_id = r.role_id 
             LEFT JOIN districts d ON u.district_id = d.district_id 
             LEFT JOIN provinces p ON d.province_id = p.province_id 
             $whereClause 
             ORDER BY u.created_at DESC 
             LIMIT ? OFFSET ?",
            $params
        );
    }
    
    /**
     * عدد المستخدمين
     * Count users
     */
    public function countUsers($filters = []) {
        $whereConditions = [];
        $params = [];
        
        // تطبيق نفس الفلاتر
        if (!empty($filters['role_id'])) {
            $whereConditions[] = "u.role_id = ?";
            $params[] = $filters['role_id'];
        }
        
        if (!empty($filters['district_id'])) {
            $whereConditions[] = "u.district_id = ?";
            $params[] = $filters['district_id'];
        }
        
        if (!empty($filters['is_active'])) {
            $whereConditions[] = "u.is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $result = $this->database->selectOne(
            "SELECT COUNT(*) as count FROM users u $whereClause",
            $params
        );
        
        return $result ? $result['count'] : 0;
    }
    
    /**
     * التحقق من وجود اسم المستخدم
     * Check if username exists
     */
    public function usernameExists($username, $excludeUserId = null) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE username = ?";
        $params = [$username];
        
        if ($excludeUserId) {
            $sql .= " AND user_id != ?";
            $params[] = $excludeUserId;
        }
        
        $result = $this->database->selectOne($sql, $params);
        return $result && $result['count'] > 0;
    }
    
    /**
     * التحقق من وجود البريد الإلكتروني
     * Check if email exists
     */
    public function emailExists($email, $excludeUserId = null) {
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeUserId) {
            $sql .= " AND user_id != ?";
            $params[] = $excludeUserId;
        }
        
        $result = $this->database->selectOne($sql, $params);
        return $result && $result['count'] > 0;
    }
    
    /**
     * إعادة تعيين كلمة المرور
     * Reset user password
     */
    public function resetPassword($userId, $newPassword = null) {
        try {
            $user = $this->getUserById($userId);
            if (!$user) {
                return ['success' => false, 'message' => 'المستخدم غير موجود'];
            }
            
            // إنشاء كلمة مرور عشوائية إذا لم يتم تحديدها
            if (!$newPassword) {
                $newPassword = generateRandomPassword(12);
            }
            
            // التحقق من قوة كلمة المرور
            $passwordValidation = validatePassword($newPassword);
            if ($passwordValidation !== true) {
                return ['success' => false, 'message' => implode('<br>', $passwordValidation)];
            }
            
            // تشفير كلمة المرور الجديدة
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // تحديث كلمة المرور
            $result = $this->database->update(
                "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?",
                [$passwordHash, $userId]
            );
            
            if ($result !== false) {
                // تسجيل العملية
                logActivity($_SESSION['user_id'] ?? null, 'reset_password', "Reset password for user: {$user['username']}", 'user', $userId);
                
                // إرسال إشعار للمستخدم
                sendNotification($userId, 'تم إعادة تعيين كلمة المرور الخاصة بك', 'password_reset');
                
                return [
                    'success' => true, 
                    'message' => 'تم إعادة تعيين كلمة المرور بنجاح',
                    'new_password' => $newPassword
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
     * الحصول على إحصائيات المستخدمين
     * Get user statistics
     */
    public function getUserStats() {
        $stats = [];
        
        // إجمالي المستخدمين
        $stats['total_users'] = $this->database->count('users');
        
        // المستخدمين النشطين
        $stats['active_users'] = $this->database->count('users', 'is_active = 1');
        
        // المستخدمين حسب الدور
        $roleStats = $this->database->select(
            "SELECT r.role_name, COUNT(u.user_id) as count 
             FROM roles r 
             LEFT JOIN users u ON r.role_id = u.role_id AND u.is_active = 1 
             GROUP BY r.role_id, r.role_name"
        );
        
        $stats['by_role'] = [];
        foreach ($roleStats as $role) {
            $stats['by_role'][$role['role_name']] = $role['count'];
        }
        
        // المستخدمين الجدد هذا الشهر
        $stats['new_this_month'] = $this->database->count(
            'users', 
            'created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)'
        );
        
        // آخر تسجيل دخول
        $lastLogin = $this->database->selectOne(
            "SELECT MAX(last_login) as last_login FROM users WHERE last_login IS NOT NULL"
        );
        $stats['last_login'] = $lastLogin ? $lastLogin['last_login'] : null;
        
        return $stats;
    }
    
    /**
     * تصدير بيانات المستخدمين
     * Export users data
     */
    public function exportUsers($format = 'csv', $filters = []) {
        $users = $this->getUsers($filters, 10000, 0); // جلب جميع المستخدمين
        
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($users);
            case 'excel':
                return $this->exportToExcel($users);
            default:
                return json_encode($users, JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * تصدير إلى CSV
     * Export to CSV
     */
    private function exportToCsv($users) {
        $output = "اسم المستخدم,الاسم الأول,الاسم الأخير,البريد الإلكتروني,الدور,المديرية,الحالة,تاريخ الإنشاء\n";
        
        foreach ($users as $user) {
            $output .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $user['username'],
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['role_name'],
                $user['district_name'] ?? '',
                $user['is_active'] ? 'نشط' : 'غير نشط',
                $user['created_at']
            );
        }
        
        return $output;
    }
    
    /**
     * تصدير إلى Excel (تنسيق CSV متقدم)
     * Export to Excel (advanced CSV format)
     */
    private function exportToExcel($users) {
        // يمكن تطوير هذه الوظيفة لاستخدام مكتبة PHPSpreadsheet
        return $this->exportToCsv($users);
    }
}

// إنشاء مثيل عام لإدارة المستخدمين
$userManager = new UserManager($database);
?>

