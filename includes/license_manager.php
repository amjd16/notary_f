<?php
/**
 * ملف إدارة التراخيص
 * License Management File
 * 
 * يحتوي على وظائف إدارة تراخيص الأمناء الشرعيين
 * Contains notary license management functions
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

class LicenseManager {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    /**
     * إنشاء ترخيص جديد
     * Create new license
     */
    public function createLicense($licenseData) {
        try {
            // التحقق من البيانات المطلوبة
            $requiredFields = ['license_number', 'notary_id', 'issue_date', 'expiry_date'];
            foreach ($requiredFields as $field) {
                if (empty($licenseData[$field])) {
                    return ['success' => false, 'message' => "الحقل $field مطلوب"];
                }
            }
            
            // التحقق من عدم تكرار رقم الترخيص
            if ($this->licenseNumberExists($licenseData['license_number'])) {
                return ['success' => false, 'message' => 'رقم الترخيص موجود مسبقاً'];
            }
            
            // التحقق من وجود المستخدم وأنه أمين شرعي
            $notary = $this->database->selectOne(
                "SELECT u.*, r.role_name FROM users u 
                 JOIN roles r ON u.role_id = r.role_id 
                 WHERE u.user_id = ?",
                [$licenseData['notary_id']]
            );
            
            if (!$notary) {
                return ['success' => false, 'message' => 'المستخدم غير موجود'];
            }
            
            if ($notary['role_name'] !== 'Notary') {
                return ['success' => false, 'message' => 'المستخدم ليس أميناً شرعياً'];
            }
            
            // التحقق من عدم وجود ترخيص نشط للأمين
            $existingLicense = $this->database->selectOne(
                "SELECT license_id FROM licenses WHERE notary_id = ? AND status = 'active'",
                [$licenseData['notary_id']]
            );
            
            if ($existingLicense) {
                return ['success' => false, 'message' => 'يوجد ترخيص نشط للأمين مسبقاً'];
            }
            
            // إعداد البيانات للإدراج
            $insertData = [
                'license_number' => sanitizeInput($licenseData['license_number']),
                'notary_id' => (int)$licenseData['notary_id'],
                'issue_date' => $licenseData['issue_date'],
                'expiry_date' => $licenseData['expiry_date'],
                'issuing_authority' => sanitizeInput($licenseData['issuing_authority'] ?? 'وزارة العدل'),
                'status' => 'active',
                'notes' => sanitizeInput($licenseData['notes'] ?? '')
            ];
            
            // إدراج الترخيص
            $licenseId = $this->database->insert(
                "INSERT INTO licenses (license_number, notary_id, issue_date, expiry_date, issuing_authority, status, notes) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                array_values($insertData)
            );
            
            if ($licenseId) {
                // ربط الترخيص بالمستخدم
                $this->database->update(
                    "UPDATE users SET license_id = ? WHERE user_id = ?",
                    [$licenseId, $licenseData['notary_id']]
                );
                
                // تسجيل العملية
                logActivity(
                    $_SESSION['user_id'] ?? null, 
                    'create_license', 
                    "Created license {$licenseData['license_number']} for notary {$notary['username']}", 
                    'license', 
                    $licenseId
                );
                
                // إرسال إشعار للأمين
                sendNotification(
                    $licenseData['notary_id'], 
                    "تم إصدار ترخيص جديد برقم: {$licenseData['license_number']}", 
                    'license_issued'
                );
                
                return [
                    'success' => true, 
                    'message' => 'تم إنشاء الترخيص بنجاح',
                    'license_id' => $licenseId
                ];
            } else {
                return ['success' => false, 'message' => 'فشل في إنشاء الترخيص'];
            }
            
        } catch (Exception $e) {
            error_log("Create license error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء إنشاء الترخيص'];
        }
    }
    
    /**
     * تحديث ترخيص
     * Update license
     */
    public function updateLicense($licenseId, $licenseData) {
        try {
            // التحقق من وجود الترخيص
            $existingLicense = $this->getLicenseById($licenseId);
            if (!$existingLicense) {
                return ['success' => false, 'message' => 'الترخيص غير موجود'];
            }
            
            // إعداد البيانات للتحديث
            $updateFields = [];
            $updateValues = [];
            
            $allowedFields = ['license_number', 'issue_date', 'expiry_date', 'issuing_authority', 'status', 'notes'];
            
            foreach ($allowedFields as $field) {
                if (isset($licenseData[$field])) {
                    // التحقق من عدم تكرار رقم الترخيص
                    if ($field === 'license_number' && $this->licenseNumberExists($licenseData[$field], $licenseId)) {
                        return ['success' => false, 'message' => 'رقم الترخيص موجود مسبقاً'];
                    }
                    
                    $updateFields[] = "$field = ?";
                    $updateValues[] = sanitizeInput($licenseData[$field]);
                }
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'message' => 'لا توجد بيانات للتحديث'];
            }
            
            // إضافة تاريخ التحديث
            $updateFields[] = "updated_at = NOW()";
            $updateValues[] = $licenseId;
            
            // تنفيذ التحديث
            $result = $this->database->update(
                "UPDATE licenses SET " . implode(', ', $updateFields) . " WHERE license_id = ?",
                $updateValues
            );
            
            if ($result !== false) {
                // تسجيل العملية
                logActivity(
                    $_SESSION['user_id'] ?? null, 
                    'update_license', 
                    "Updated license {$existingLicense['license_number']}", 
                    'license', 
                    $licenseId
                );
                
                // إرسال إشعار للأمين في حالة تغيير الحالة
                if (isset($licenseData['status']) && $licenseData['status'] !== $existingLicense['status']) {
                    $statusText = $this->getStatusText($licenseData['status']);
                    sendNotification(
                        $existingLicense['notary_id'], 
                        "تم تغيير حالة ترخيصك إلى: $statusText", 
                        'license_status_changed'
                    );
                }
                
                return ['success' => true, 'message' => 'تم تحديث الترخيص بنجاح'];
            } else {
                return ['success' => false, 'message' => 'فشل في تحديث الترخيص'];
            }
            
        } catch (Exception $e) {
            error_log("Update license error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تحديث الترخيص'];
        }
    }
    
    /**
     * تجميد ترخيص
     * Suspend license
     */
    public function suspendLicense($licenseId, $reason = null) {
        return $this->changeLicenseStatus($licenseId, 'suspended', $reason);
    }
    
    /**
     * إلغاء ترخيص
     * Revoke license
     */
    public function revokeLicense($licenseId, $reason = null) {
        return $this->changeLicenseStatus($licenseId, 'revoked', $reason);
    }
    
    /**
     * تفعيل ترخيص
     * Activate license
     */
    public function activateLicense($licenseId) {
        return $this->changeLicenseStatus($licenseId, 'active');
    }
    
    /**
     * تغيير حالة الترخيص
     * Change license status
     */
    private function changeLicenseStatus($licenseId, $newStatus, $reason = null) {
        try {
            $license = $this->getLicenseById($licenseId);
            if (!$license) {
                return ['success' => false, 'message' => 'الترخيص غير موجود'];
            }
            
            $result = $this->database->update(
                "UPDATE licenses SET status = ?, notes = CONCAT(IFNULL(notes, ''), ?, ?), updated_at = NOW() WHERE license_id = ?",
                [
                    $newStatus,
                    "\n" . date('Y-m-d H:i:s') . " - تغيير الحالة إلى: " . $this->getStatusText($newStatus),
                    $reason ? " - السبب: $reason" : "",
                    $licenseId
                ]
            );
            
            if ($result !== false) {
                // تسجيل العملية
                logActivity(
                    $_SESSION['user_id'] ?? null, 
                    'change_license_status', 
                    "Changed license {$license['license_number']} status to $newStatus" . ($reason ? " - Reason: $reason" : ""), 
                    'license', 
                    $licenseId
                );
                
                // إرسال إشعار للأمين
                $statusText = $this->getStatusText($newStatus);
                sendNotification(
                    $license['notary_id'], 
                    "تم تغيير حالة ترخيصك إلى: $statusText" . ($reason ? " - السبب: $reason" : ""), 
                    'license_status_changed'
                );
                
                return ['success' => true, 'message' => "تم تغيير حالة الترخيص إلى: $statusText"];
            } else {
                return ['success' => false, 'message' => 'فشل في تغيير حالة الترخيص'];
            }
            
        } catch (Exception $e) {
            error_log("Change license status error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تغيير حالة الترخيص'];
        }
    }
    
    /**
     * تجديد ترخيص
     * Renew license
     */
    public function renewLicense($licenseId, $newExpiryDate) {
        try {
            $license = $this->getLicenseById($licenseId);
            if (!$license) {
                return ['success' => false, 'message' => 'الترخيص غير موجود'];
            }
            
            $result = $this->database->update(
                "UPDATE licenses SET expiry_date = ?, status = 'active', 
                 notes = CONCAT(IFNULL(notes, ''), ?, ?), updated_at = NOW() 
                 WHERE license_id = ?",
                [
                    $newExpiryDate,
                    "\n" . date('Y-m-d H:i:s') . " - تم تجديد الترخيص",
                    " - تاريخ الانتهاء الجديد: $newExpiryDate",
                    $licenseId
                ]
            );
            
            if ($result !== false) {
                // تسجيل العملية
                logActivity(
                    $_SESSION['user_id'] ?? null, 
                    'renew_license', 
                    "Renewed license {$license['license_number']} until $newExpiryDate", 
                    'license', 
                    $licenseId
                );
                
                // إرسال إشعار للأمين
                sendNotification(
                    $license['notary_id'], 
                    "تم تجديد ترخيصك حتى تاريخ: $newExpiryDate", 
                    'license_renewed'
                );
                
                return ['success' => true, 'message' => 'تم تجديد الترخيص بنجاح'];
            } else {
                return ['success' => false, 'message' => 'فشل في تجديد الترخيص'];
            }
            
        } catch (Exception $e) {
            error_log("Renew license error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تجديد الترخيص'];
        }
    }
    
    /**
     * الحصول على ترخيص بالمعرف
     * Get license by ID
     */
    public function getLicenseById($licenseId) {
        return $this->database->selectOne(
            "SELECT l.*, u.username, u.first_name, u.last_name, u.email,
                    d.district_name, p.province_name
             FROM licenses l 
             JOIN users u ON l.notary_id = u.user_id 
             LEFT JOIN districts d ON u.district_id = d.district_id 
             LEFT JOIN provinces p ON d.province_id = p.province_id 
             WHERE l.license_id = ?",
            [$licenseId]
        );
    }
    
    /**
     * الحصول على ترخيص برقم الترخيص
     * Get license by license number
     */
    public function getLicenseByNumber($licenseNumber) {
        return $this->database->selectOne(
            "SELECT l.*, u.username, u.first_name, u.last_name, u.email,
                    d.district_name, p.province_name
             FROM licenses l 
             JOIN users u ON l.notary_id = u.user_id 
             LEFT JOIN districts d ON u.district_id = d.district_id 
             LEFT JOIN provinces p ON d.province_id = p.province_id 
             WHERE l.license_number = ?",
            [$licenseNumber]
        );
    }
    
    /**
     * الحصول على ترخيص الأمين
     * Get notary license
     */
    public function getNotaryLicense($notaryId) {
        return $this->database->selectOne(
            "SELECT * FROM licenses WHERE notary_id = ? ORDER BY created_at DESC LIMIT 1",
            [$notaryId]
        );
    }
    
    /**
     * الحصول على قائمة التراخيص
     * Get licenses list
     */
    public function getLicenses($filters = [], $limit = 50, $offset = 0) {
        $whereConditions = [];
        $params = [];
        
        // تطبيق الفلاتر
        if (!empty($filters['status'])) {
            $whereConditions[] = "l.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['district_id'])) {
            $whereConditions[] = "u.district_id = ?";
            $params[] = $filters['district_id'];
        }
        
        if (!empty($filters['expiring_soon'])) {
            $whereConditions[] = "l.expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) AND l.status = 'active'";
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(l.license_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // إضافة معاملات الترقيم
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->database->select(
            "SELECT l.*, u.username, u.first_name, u.last_name, u.email,
                    d.district_name, p.province_name,
                    CASE 
                        WHEN l.expiry_date <= NOW() THEN 'expired'
                        WHEN l.expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'expiring_soon'
                        ELSE 'valid'
                    END as expiry_status
             FROM licenses l 
             JOIN users u ON l.notary_id = u.user_id 
             LEFT JOIN districts d ON u.district_id = d.district_id 
             LEFT JOIN provinces p ON d.province_id = p.province_id 
             $whereClause 
             ORDER BY l.created_at DESC 
             LIMIT ? OFFSET ?",
            $params
        );
    }
    
    /**
     * عدد التراخيص
     * Count licenses
     */
    public function countLicenses($filters = []) {
        $whereConditions = [];
        $params = [];
        
        // تطبيق نفس الفلاتر
        if (!empty($filters['status'])) {
            $whereConditions[] = "l.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['district_id'])) {
            $whereConditions[] = "u.district_id = ?";
            $params[] = $filters['district_id'];
        }
        
        if (!empty($filters['expiring_soon'])) {
            $whereConditions[] = "l.expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) AND l.status = 'active'";
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(l.license_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $result = $this->database->selectOne(
            "SELECT COUNT(*) as count FROM licenses l 
             JOIN users u ON l.notary_id = u.user_id 
             $whereClause",
            $params
        );
        
        return $result ? $result['count'] : 0;
    }
    
    /**
     * التحقق من وجود رقم الترخيص
     * Check if license number exists
     */
    public function licenseNumberExists($licenseNumber, $excludeLicenseId = null) {
        $sql = "SELECT COUNT(*) as count FROM licenses WHERE license_number = ?";
        $params = [$licenseNumber];
        
        if ($excludeLicenseId) {
            $sql .= " AND license_id != ?";
            $params[] = $excludeLicenseId;
        }
        
        $result = $this->database->selectOne($sql, $params);
        return $result && $result['count'] > 0;
    }
    
    /**
     * الحصول على التراخيص المنتهية الصلاحية
     * Get expired licenses
     */
    public function getExpiredLicenses() {
        return $this->database->select(
            "SELECT l.*, u.username, u.first_name, u.last_name, u.email,
                    d.district_name, p.province_name
             FROM licenses l 
             JOIN users u ON l.notary_id = u.user_id 
             LEFT JOIN districts d ON u.district_id = d.district_id 
             LEFT JOIN provinces p ON d.province_id = p.province_id 
             WHERE l.expiry_date <= NOW() AND l.status = 'active'
             ORDER BY l.expiry_date ASC"
        );
    }
    
    /**
     * الحصول على التراخيص التي ستنتهي قريباً
     * Get licenses expiring soon
     */
    public function getLicensesExpiringSoon($days = 30) {
        return $this->database->select(
            "SELECT l.*, u.username, u.first_name, u.last_name, u.email,
                    d.district_name, p.province_name,
                    DATEDIFF(l.expiry_date, NOW()) as days_remaining
             FROM licenses l 
             JOIN users u ON l.notary_id = u.user_id 
             LEFT JOIN districts d ON u.district_id = d.district_id 
             LEFT JOIN provinces p ON d.province_id = p.province_id 
             WHERE l.expiry_date <= DATE_ADD(NOW(), INTERVAL ? DAY) 
             AND l.expiry_date > NOW() 
             AND l.status = 'active'
             ORDER BY l.expiry_date ASC",
            [$days]
        );
    }
    
    /**
     * الحصول على إحصائيات التراخيص
     * Get license statistics
     */
    public function getLicenseStats() {
        $stats = [];
        
        // إجمالي التراخيص
        $stats['total_licenses'] = $this->database->count('licenses');
        
        // التراخيص النشطة
        $stats['active_licenses'] = $this->database->count('licenses', "status = 'active'");
        
        // التراخيص المجمدة
        $stats['suspended_licenses'] = $this->database->count('licenses', "status = 'suspended'");
        
        // التراخيص الملغاة
        $stats['revoked_licenses'] = $this->database->count('licenses', "status = 'revoked'");
        
        // التراخيص المنتهية
        $stats['expired_licenses'] = $this->database->count('licenses', "expiry_date <= NOW() AND status = 'active'");
        
        // التراخيص التي ستنتهي خلال 30 يوم
        $stats['expiring_soon'] = $this->database->count(
            'licenses', 
            "expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY) AND expiry_date > NOW() AND status = 'active'"
        );
        
        // التراخيص الجديدة هذا الشهر
        $stats['new_this_month'] = $this->database->count(
            'licenses', 
            'created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)'
        );
        
        return $stats;
    }
    
    /**
     * الحصول على نص الحالة
     * Get status text
     */
    private function getStatusText($status) {
        $statusTexts = [
            'active' => 'نشط',
            'suspended' => 'مجمد',
            'revoked' => 'ملغى',
            'expired' => 'منتهي الصلاحية'
        ];
        
        return $statusTexts[$status] ?? $status;
    }
    
    /**
     * إرسال تنبيهات انتهاء الصلاحية
     * Send expiry notifications
     */
    public function sendExpiryNotifications() {
        try {
            // التراخيص التي ستنتهي خلال 30 يوم
            $expiringSoon = $this->getLicensesExpiringSoon(30);
            
            foreach ($expiringSoon as $license) {
                $daysRemaining = $license['days_remaining'];
                $message = "تنبيه: ترخيصك سينتهي خلال $daysRemaining يوم. يرجى التواصل مع الإدارة للتجديد.";
                
                sendNotification($license['notary_id'], $message, 'license_expiry_warning');
            }
            
            // التراخيص المنتهية
            $expired = $this->getExpiredLicenses();
            
            foreach ($expired as $license) {
                $message = "تنبيه: انتهت صلاحية ترخيصك. يرجى التواصل مع الإدارة فوراً.";
                
                sendNotification($license['notary_id'], $message, 'license_expired');
                
                // تجميد الترخيص تلقائياً
                $this->suspendLicense($license['license_id'], 'انتهاء الصلاحية');
            }
            
            return [
                'success' => true,
                'expiring_soon' => count($expiringSoon),
                'expired' => count($expired)
            ];
            
        } catch (Exception $e) {
            error_log("Send expiry notifications error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء إرسال التنبيهات'];
        }
    }
    
    /**
     * تصدير بيانات التراخيص
     * Export licenses data
     */
    public function exportLicenses($format = 'csv', $filters = []) {
        $licenses = $this->getLicenses($filters, 10000, 0);
        
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($licenses);
            default:
                return json_encode($licenses, JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * تصدير إلى CSV
     * Export to CSV
     */
    private function exportToCsv($licenses) {
        $output = "رقم الترخيص,اسم الأمين,المديرية,تاريخ الإصدار,تاريخ الانتهاء,الحالة,الجهة المصدرة\n";
        
        foreach ($licenses as $license) {
            $output .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $license['license_number'],
                $license['first_name'] . ' ' . $license['last_name'],
                $license['district_name'] ?? '',
                $license['issue_date'],
                $license['expiry_date'],
                $this->getStatusText($license['status']),
                $license['issuing_authority']
            );
        }
        
        return $output;
    }
}

// إنشاء مثيل عام لإدارة التراخيص
$licenseManager = new LicenseManager($database);
?>

