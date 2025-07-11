<?php
/**
 * ملف إدارة الأدوار والصلاحيات
 * Role and Permission Management File
 * 
 * يحتوي على وظائف إدارة الأدوار والصلاحيات
 * Contains role and permission management functions
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

class RoleManager {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    /**
     * الحصول على جميع الأدوار
     * Get all roles
     */
    public function getAllRoles() {
        return $this->database->select(
            "SELECT * FROM roles ORDER BY role_id"
        );
    }
    
    /**
     * الحصول على دور بالمعرف
     * Get role by ID
     */
    public function getRoleById($roleId) {
        return $this->database->selectOne(
            "SELECT * FROM roles WHERE role_id = ?",
            [$roleId]
        );
    }
    
    /**
     * الحصول على دور بالاسم
     * Get role by name
     */
    public function getRoleByName($roleName) {
        return $this->database->selectOne(
            "SELECT * FROM roles WHERE role_name = ?",
            [$roleName]
        );
    }
    
    /**
     * إنشاء دور جديد
     * Create new role
     */
    public function createRole($roleName, $description = null) {
        try {
            // التحقق من عدم تكرار اسم الدور
            if ($this->getRoleByName($roleName)) {
                return ['success' => false, 'message' => 'اسم الدور موجود مسبقاً'];
            }
            
            $roleId = $this->database->insert(
                "INSERT INTO roles (role_name, description) VALUES (?, ?)",
                [$roleName, $description]
            );
            
            if ($roleId) {
                // تسجيل العملية
                logActivity($_SESSION['user_id'] ?? null, 'create_role', "Created role: $roleName", 'role', $roleId);
                
                return [
                    'success' => true, 
                    'message' => 'تم إنشاء الدور بنجاح',
                    'role_id' => $roleId
                ];
            } else {
                return ['success' => false, 'message' => 'فشل في إنشاء الدور'];
            }
            
        } catch (Exception $e) {
            error_log("Create role error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء إنشاء الدور'];
        }
    }
    
    /**
     * تحديث دور
     * Update role
     */
    public function updateRole($roleId, $roleName, $description = null) {
        try {
            // التحقق من وجود الدور
            $existingRole = $this->getRoleById($roleId);
            if (!$existingRole) {
                return ['success' => false, 'message' => 'الدور غير موجود'];
            }
            
            // التحقق من عدم تكرار اسم الدور
            $duplicateRole = $this->database->selectOne(
                "SELECT role_id FROM roles WHERE role_name = ? AND role_id != ?",
                [$roleName, $roleId]
            );
            
            if ($duplicateRole) {
                return ['success' => false, 'message' => 'اسم الدور موجود مسبقاً'];
            }
            
            $result = $this->database->update(
                "UPDATE roles SET role_name = ?, description = ? WHERE role_id = ?",
                [$roleName, $description, $roleId]
            );
            
            if ($result !== false) {
                // تسجيل العملية
                logActivity($_SESSION['user_id'] ?? null, 'update_role', "Updated role: $roleName", 'role', $roleId);
                
                return ['success' => true, 'message' => 'تم تحديث الدور بنجاح'];
            } else {
                return ['success' => false, 'message' => 'فشل في تحديث الدور'];
            }
            
        } catch (Exception $e) {
            error_log("Update role error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تحديث الدور'];
        }
    }
    
    /**
     * حذف دور
     * Delete role
     */
    public function deleteRole($roleId) {
        try {
            // التحقق من وجود الدور
            $role = $this->getRoleById($roleId);
            if (!$role) {
                return ['success' => false, 'message' => 'الدور غير موجود'];
            }
            
            // منع حذف الأدوار الأساسية
            $protectedRoles = ['Administrator', 'Head of Notary Office', 'Notary'];
            if (in_array($role['role_name'], $protectedRoles)) {
                return ['success' => false, 'message' => 'لا يمكن حذف هذا الدور'];
            }
            
            // التحقق من وجود مستخدمين بهذا الدور
            $userCount = $this->database->count('users', 'role_id = ?', [$roleId]);
            if ($userCount > 0) {
                return ['success' => false, 'message' => 'لا يمكن حذف الدور لوجود مستخدمين مرتبطين به'];
            }
            
            $result = $this->database->delete("DELETE FROM roles WHERE role_id = ?", [$roleId]);
            
            if ($result) {
                // تسجيل العملية
                logActivity($_SESSION['user_id'] ?? null, 'delete_role', "Deleted role: {$role['role_name']}", 'role', $roleId);
                
                return ['success' => true, 'message' => 'تم حذف الدور بنجاح'];
            } else {
                return ['success' => false, 'message' => 'فشل في حذف الدور'];
            }
            
        } catch (Exception $e) {
            error_log("Delete role error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء حذف الدور'];
        }
    }
    
    /**
     * الحصول على المستخدمين حسب الدور
     * Get users by role
     */
    public function getUsersByRole($roleId) {
        return $this->database->select(
            "SELECT u.*, d.district_name, p.province_name 
             FROM users u 
             LEFT JOIN districts d ON u.district_id = d.district_id 
             LEFT JOIN provinces p ON d.province_id = p.province_id 
             WHERE u.role_id = ? 
             ORDER BY u.first_name, u.last_name",
            [$roleId]
        );
    }
    
    /**
     * تغيير دور المستخدم
     * Change user role
     */
    public function changeUserRole($userId, $newRoleId) {
        try {
            // التحقق من وجود المستخدم
            $user = $this->database->selectOne(
                "SELECT u.*, r.role_name as current_role FROM users u 
                 JOIN roles r ON u.role_id = r.role_id 
                 WHERE u.user_id = ?",
                [$userId]
            );
            
            if (!$user) {
                return ['success' => false, 'message' => 'المستخدم غير موجود'];
            }
            
            // التحقق من وجود الدور الجديد
            $newRole = $this->getRoleById($newRoleId);
            if (!$newRole) {
                return ['success' => false, 'message' => 'الدور الجديد غير موجود'];
            }
            
            // منع تغيير دور المدير الوحيد
            if ($user['current_role'] === 'Administrator') {
                $adminCount = $this->database->count(
                    'users u JOIN roles r ON u.role_id = r.role_id', 
                    "r.role_name = 'Administrator' AND u.is_active = 1"
                );
                
                if ($adminCount <= 1 && $newRole['role_name'] !== 'Administrator') {
                    return ['success' => false, 'message' => 'لا يمكن تغيير دور المدير الوحيد في النظام'];
                }
            }
            
            $result = $this->database->update(
                "UPDATE users SET role_id = ?, updated_at = NOW() WHERE user_id = ?",
                [$newRoleId, $userId]
            );
            
            if ($result !== false) {
                // تسجيل العملية
                logActivity(
                    $_SESSION['user_id'] ?? null, 
                    'change_user_role', 
                    "Changed role for {$user['username']} from {$user['current_role']} to {$newRole['role_name']}", 
                    'user', 
                    $userId
                );
                
                // إرسال إشعار للمستخدم
                sendNotification($userId, "تم تغيير دورك في النظام إلى: {$newRole['role_name']}", 'role_change');
                
                return ['success' => true, 'message' => 'تم تغيير دور المستخدم بنجاح'];
            } else {
                return ['success' => false, 'message' => 'فشل في تغيير دور المستخدم'];
            }
            
        } catch (Exception $e) {
            error_log("Change user role error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تغيير دور المستخدم'];
        }
    }
    
    /**
     * التحقق من صلاحية المستخدم
     * Check user permission
     */
    public function hasPermission($userId, $permission) {
        $user = $this->database->selectOne(
            "SELECT r.role_name FROM users u 
             JOIN roles r ON u.role_id = r.role_id 
             WHERE u.user_id = ? AND u.is_active = 1",
            [$userId]
        );
        
        if (!$user) {
            return false;
        }
        
        return $this->roleHasPermission($user['role_name'], $permission);
    }
    
    /**
     * التحقق من صلاحية الدور
     * Check role permission
     */
    public function roleHasPermission($roleName, $permission) {
        // تعريف الصلاحيات لكل دور
        $permissions = [
            'Administrator' => [
                'manage_users', 'manage_roles', 'manage_licenses', 'manage_districts',
                'view_all_contracts', 'manage_contracts', 'view_reports', 'manage_system',
                'view_logs', 'manage_settings', 'export_data', 'backup_restore'
            ],
            'Head of Notary Office' => [
                'view_district_users', 'manage_district_notaries', 'view_district_contracts',
                'review_contracts', 'approve_contracts', 'view_district_reports',
                'send_notifications', 'view_performance', 'export_district_data'
            ],
            'Notary' => [
                'create_contract', 'edit_own_contract', 'view_own_contracts',
                'submit_contract', 'view_own_reports', 'update_profile',
                'upload_documents', 'view_notifications'
            ]
        ];
        
        return isset($permissions[$roleName]) && in_array($permission, $permissions[$roleName]);
    }
    
    /**
     * الحصول على صلاحيات الدور
     * Get role permissions
     */
    public function getRolePermissions($roleName) {
        $permissions = [
            'Administrator' => [
                'manage_users' => 'إدارة المستخدمين',
                'manage_roles' => 'إدارة الأدوار',
                'manage_licenses' => 'إدارة التراخيص',
                'manage_districts' => 'إدارة المديريات',
                'view_all_contracts' => 'عرض جميع العقود',
                'manage_contracts' => 'إدارة العقود',
                'view_reports' => 'عرض التقارير',
                'manage_system' => 'إدارة النظام',
                'view_logs' => 'عرض السجلات',
                'manage_settings' => 'إدارة الإعدادات',
                'export_data' => 'تصدير البيانات',
                'backup_restore' => 'النسخ الاحتياطي والاستعادة'
            ],
            'Head of Notary Office' => [
                'view_district_users' => 'عرض مستخدمي المديرية',
                'manage_district_notaries' => 'إدارة أمناء المديرية',
                'view_district_contracts' => 'عرض عقود المديرية',
                'review_contracts' => 'مراجعة العقود',
                'approve_contracts' => 'اعتماد العقود',
                'view_district_reports' => 'عرض تقارير المديرية',
                'send_notifications' => 'إرسال الإشعارات',
                'view_performance' => 'عرض الأداء',
                'export_district_data' => 'تصدير بيانات المديرية'
            ],
            'Notary' => [
                'create_contract' => 'إنشاء عقد',
                'edit_own_contract' => 'تعديل العقود الخاصة',
                'view_own_contracts' => 'عرض العقود الخاصة',
                'submit_contract' => 'تقديم عقد',
                'view_own_reports' => 'عرض التقارير الخاصة',
                'update_profile' => 'تحديث الملف الشخصي',
                'upload_documents' => 'رفع المستندات',
                'view_notifications' => 'عرض الإشعارات'
            ]
        ];
        
        return $permissions[$roleName] ?? [];
    }
    
    /**
     * الحصول على إحصائيات الأدوار
     * Get role statistics
     */
    public function getRoleStats() {
        $stats = [];
        
        $roleStats = $this->database->select(
            "SELECT r.role_name, r.description,
                    COUNT(u.user_id) as total_users,
                    COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as active_users,
                    COUNT(CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as active_last_month
             FROM roles r 
             LEFT JOIN users u ON r.role_id = u.role_id 
             GROUP BY r.role_id, r.role_name, r.description 
             ORDER BY r.role_id"
        );
        
        foreach ($roleStats as $role) {
            $stats[] = [
                'role_name' => $role['role_name'],
                'description' => $role['description'],
                'total_users' => (int)$role['total_users'],
                'active_users' => (int)$role['active_users'],
                'active_last_month' => (int)$role['active_last_month'],
                'permissions' => $this->getRolePermissions($role['role_name'])
            ];
        }
        
        return $stats;
    }
    
    /**
     * التحقق من إمكانية الوصول للمورد
     * Check resource access
     */
    public function canAccessResource($userId, $resourceType, $resourceId = null) {
        $user = $this->database->selectOne(
            "SELECT u.*, r.role_name FROM users u 
             JOIN roles r ON u.role_id = r.role_id 
             WHERE u.user_id = ? AND u.is_active = 1",
            [$userId]
        );
        
        if (!$user) {
            return false;
        }
        
        // مدير النظام يمكنه الوصول لكل شيء
        if ($user['role_name'] === 'Administrator') {
            return true;
        }
        
        // التحقق حسب نوع المورد
        switch ($resourceType) {
            case 'user':
                return $this->canAccessUser($user, $resourceId);
            case 'contract':
                return $this->canAccessContract($user, $resourceId);
            case 'district':
                return $this->canAccessDistrict($user, $resourceId);
            case 'report':
                return $this->canAccessReport($user, $resourceId);
            default:
                return false;
        }
    }
    
    /**
     * التحقق من الوصول للمستخدم
     * Check user access
     */
    private function canAccessUser($currentUser, $targetUserId) {
        // المستخدم يمكنه الوصول لملفه الشخصي
        if ($currentUser['user_id'] == $targetUserId) {
            return true;
        }
        
        // رئيس القلم يمكنه الوصول للأمناء في مديريته
        if ($currentUser['role_name'] === 'Head of Notary Office') {
            $targetUser = $this->database->selectOne(
                "SELECT u.district_id, r.role_name FROM users u 
                 JOIN roles r ON u.role_id = r.role_id 
                 WHERE u.user_id = ?",
                [$targetUserId]
            );
            
            return $targetUser && 
                   $targetUser['district_id'] == $currentUser['district_id'] && 
                   $targetUser['role_name'] === 'Notary';
        }
        
        return false;
    }
    
    /**
     * التحقق من الوصول للعقد
     * Check contract access
     */
    private function canAccessContract($user, $contractId) {
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
     * التحقق من الوصول للمديرية
     * Check district access
     */
    private function canAccessDistrict($user, $districtId) {
        // رئيس القلم والأمين يمكنهما الوصول لمديريتهما فقط
        if (in_array($user['role_name'], ['Head of Notary Office', 'Notary'])) {
            return $districtId == $user['district_id'];
        }
        
        return false;
    }
    
    /**
     * التحقق من الوصول للتقرير
     * Check report access
     */
    private function canAccessReport($user, $reportId) {
        // يمكن تطوير هذه الوظيفة حسب نوع التقرير
        return true;
    }
    
    /**
     * إنشاء دور مخصص
     * Create custom role
     */
    public function createCustomRole($roleName, $description, $permissions) {
        try {
            // إنشاء الدور
            $result = $this->createRole($roleName, $description);
            
            if ($result['success']) {
                // يمكن إضافة جدول للصلاحيات المخصصة هنا
                // حالياً نستخدم الصلاحيات المحددة مسبقاً
                
                return $result;
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Create custom role error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء إنشاء الدور المخصص'];
        }
    }
}

// إنشاء مثيل عام لإدارة الأدوار
$roleManager = new RoleManager($database);
?>

