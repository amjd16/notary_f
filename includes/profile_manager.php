<?php
/**
 * ملف إدارة الملف الشخصي
 * Profile Management File
 * 
 * يحتوي على وظائف إدارة الملف الشخصي للمستخدمين
 * Contains user profile management functions
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

class ProfileManager {
    
    private $database;
    private $fileUpload;
    
    public function __construct($database, $fileUpload) {
        $this->database = $database;
        $this->fileUpload = $fileUpload;
    }
    
    /**
     * الحصول على الملف الشخصي
     * Get user profile
     */
    public function getProfile($userId) {
        try {
            $profile = $this->database->selectOne(
                "SELECT u.*, r.role_name, d.district_name, p.province_name, v.village_name,
                        l.license_number, l.issue_date, l.expiry_date, l.status as license_status
                 FROM users u 
                 JOIN roles r ON u.role_id = r.role_id 
                 LEFT JOIN districts d ON u.district_id = d.district_id 
                 LEFT JOIN provinces p ON d.province_id = p.province_id 
                 LEFT JOIN villages v ON u.village_id = v.village_id 
                 LEFT JOIN licenses l ON u.license_id = l.license_id 
                 WHERE u.user_id = ?",
                [$userId]
            );
            
            if ($profile) {
                // إخفاء كلمة المرور
                unset($profile['password_hash']);
                unset($profile['remember_token']);
                
                // إضافة معلومات إضافية
                $profile['full_name'] = $profile['first_name'] . ' ' . $profile['last_name'];
                $profile['profile_image_url'] = $this->getProfileImageUrl($userId);
                $profile['account_age_days'] = $this->getAccountAge($profile['created_at']);
                $profile['last_login_formatted'] = $profile['last_login'] ? formatDateArabic($profile['last_login']) : 'لم يسجل دخول من قبل';
                
                return $profile;
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Get profile error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * تحديث الملف الشخصي
     * Update user profile
     */
    public function updateProfile($userId, $profileData) {
        try {
            // التحقق من وجود المستخدم
            $existingProfile = $this->getProfile($userId);
            if (!$existingProfile) {
                return ['success' => false, 'message' => 'المستخدم غير موجود'];
            }
            
            // التحقق من الصلاحية (المستخدم يمكنه تحديث ملفه الشخصي فقط أو المدير)
            $currentUser = getCurrentUser();
            if ($currentUser['user_id'] != $userId && $currentUser['role_name'] !== 'Administrator') {
                return ['success' => false, 'message' => 'ليس لديك الصلاحية لتحديث هذا الملف الشخصي'];
            }
            
            // إعداد البيانات للتحديث
            $updateFields = [];
            $updateValues = [];
            
            // الحقول القابلة للتحديث
            $allowedFields = ['first_name', 'last_name', 'email', 'phone_number', 'bio', 'address'];
            
            foreach ($allowedFields as $field) {
                if (isset($profileData[$field])) {
                    // التحقق من صحة البريد الإلكتروني
                    if ($field === 'email') {
                        if (!validateEmail($profileData[$field])) {
                            return ['success' => false, 'message' => 'البريد الإلكتروني غير صحيح'];
                        }
                        
                        // التحقق من عدم تكرار البريد الإلكتروني
                        $emailExists = $this->database->selectOne(
                            "SELECT user_id FROM users WHERE email = ? AND user_id != ?",
                            [$profileData[$field], $userId]
                        );
                        
                        if ($emailExists) {
                            return ['success' => false, 'message' => 'البريد الإلكتروني موجود مسبقاً'];
                        }
                    }
                    
                    // التحقق من رقم الهاتف اليمني
                    if ($field === 'phone_number' && !empty($profileData[$field])) {
                        if (!validateYemeniPhone($profileData[$field])) {
                            return ['success' => false, 'message' => 'رقم الهاتف غير صحيح'];
                        }
                    }
                    
                    $updateFields[] = "$field = ?";
                    $updateValues[] = sanitizeInput($profileData[$field]);
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
                // تحديث الجلسة إذا كان المستخدم يحدث ملفه الشخصي
                if ($currentUser['user_id'] == $userId) {
                    if (isset($profileData['first_name']) || isset($profileData['last_name'])) {
                        $firstName = $profileData['first_name'] ?? $existingProfile['first_name'];
                        $lastName = $profileData['last_name'] ?? $existingProfile['last_name'];
                        $_SESSION['full_name'] = $firstName . ' ' . $lastName;
                    }
                }
                
                // تسجيل العملية
                logActivity(
                    $currentUser['user_id'],
                    'update_profile',
                    "Updated profile for user: {$existingProfile['username']}",
                    'user',
                    $userId
                );
                
                return ['success' => true, 'message' => 'تم تحديث الملف الشخصي بنجاح'];
            } else {
                return ['success' => false, 'message' => 'فشل في تحديث الملف الشخصي'];
            }
            
        } catch (Exception $e) {
            error_log("Update profile error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تحديث الملف الشخصي'];
        }
    }
    
    /**
     * رفع صورة الملف الشخصي
     * Upload profile image
     */
    public function uploadProfileImage($userId, $imageFile) {
        try {
            // التحقق من وجود المستخدم
            $user = $this->getProfile($userId);
            if (!$user) {
                return ['success' => false, 'message' => 'المستخدم غير موجود'];
            }
            
            // التحقق من الصلاحية
            $currentUser = getCurrentUser();
            if ($currentUser['user_id'] != $userId && $currentUser['role_name'] !== 'Administrator') {
                return ['success' => false, 'message' => 'ليس لديك الصلاحية لتحديث هذه الصورة'];
            }
            
            // التحقق من أن الملف صورة
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            $fileExtension = strtolower(pathinfo($imageFile['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedTypes)) {
                return ['success' => false, 'message' => 'نوع الملف غير مدعوم. الأنواع المدعومة: ' . implode(', ', $allowedTypes)];
            }
            
            // رفع الصورة
            $uploadResult = $this->fileUpload->uploadFile($imageFile, 'images', 'profile_' . $userId);
            
            if ($uploadResult && $uploadResult['success']) {
                // حذف الصورة القديمة إذا كانت موجودة
                $oldImage = $this->database->selectOne(
                    "SELECT profile_image FROM users WHERE user_id = ?",
                    [$userId]
                );
                
                if ($oldImage && $oldImage['profile_image']) {
                    $this->fileUpload->deleteFile($oldImage['profile_image'], 'images');
                }
                
                // تحديث مسار الصورة في قاعدة البيانات
                $updateResult = $this->database->update(
                    "UPDATE users SET profile_image = ?, updated_at = NOW() WHERE user_id = ?",
                    [$uploadResult['filename'], $userId]
                );
                
                if ($updateResult !== false) {
                    // إنشاء صورة مصغرة
                    $thumbnailUrl = $this->fileUpload->generateThumbnail($uploadResult['filename'], 'images');
                    
                    // تسجيل العملية
                    logActivity(
                        $currentUser['user_id'],
                        'upload_profile_image',
                        "Uploaded profile image for user: {$user['username']}",
                        'user',
                        $userId
                    );
                    
                    return [
                        'success' => true,
                        'message' => 'تم رفع الصورة بنجاح',
                        'image_url' => $uploadResult['url'],
                        'thumbnail_url' => $thumbnailUrl
                    ];
                } else {
                    // حذف الملف المرفوع في حالة فشل التحديث
                    $this->fileUpload->deleteFile($uploadResult['filename'], 'images');
                    return ['success' => false, 'message' => 'فشل في حفظ مسار الصورة'];
                }
            } else {
                $errors = $this->fileUpload->getErrors();
                return ['success' => false, 'message' => implode('<br>', $errors)];
            }
            
        } catch (Exception $e) {
            error_log("Upload profile image error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء رفع الصورة'];
        }
    }
    
    /**
     * حذف صورة الملف الشخصي
     * Delete profile image
     */
    public function deleteProfileImage($userId) {
        try {
            // التحقق من وجود المستخدم
            $user = $this->getProfile($userId);
            if (!$user) {
                return ['success' => false, 'message' => 'المستخدم غير موجود'];
            }
            
            // التحقق من الصلاحية
            $currentUser = getCurrentUser();
            if ($currentUser['user_id'] != $userId && $currentUser['role_name'] !== 'Administrator') {
                return ['success' => false, 'message' => 'ليس لديك الصلاحية لحذف هذه الصورة'];
            }
            
            // الحصول على مسار الصورة
            $imageData = $this->database->selectOne(
                "SELECT profile_image FROM users WHERE user_id = ?",
                [$userId]
            );
            
            if ($imageData && $imageData['profile_image']) {
                // حذف الصورة من الخادم
                $this->fileUpload->deleteFile($imageData['profile_image'], 'images');
                
                // حذف الصورة المصغرة
                $this->fileUpload->deleteFile('thumb_' . $imageData['profile_image'], 'images/thumbnails');
                
                // تحديث قاعدة البيانات
                $result = $this->database->update(
                    "UPDATE users SET profile_image = NULL, updated_at = NOW() WHERE user_id = ?",
                    [$userId]
                );
                
                if ($result !== false) {
                    // تسجيل العملية
                    logActivity(
                        $currentUser['user_id'],
                        'delete_profile_image',
                        "Deleted profile image for user: {$user['username']}",
                        'user',
                        $userId
                    );
                    
                    return ['success' => true, 'message' => 'تم حذف الصورة بنجاح'];
                } else {
                    return ['success' => false, 'message' => 'فشل في حذف الصورة من قاعدة البيانات'];
                }
            } else {
                return ['success' => false, 'message' => 'لا توجد صورة للحذف'];
            }
            
        } catch (Exception $e) {
            error_log("Delete profile image error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء حذف الصورة'];
        }
    }
    
    /**
     * الحصول على رابط صورة الملف الشخصي
     * Get profile image URL
     */
    public function getProfileImageUrl($userId) {
        try {
            $imageData = $this->database->selectOne(
                "SELECT profile_image FROM users WHERE user_id = ?",
                [$userId]
            );
            
            if ($imageData && $imageData['profile_image']) {
                return BASE_URL . '/uploads/images/' . $imageData['profile_image'];
            } else {
                // إرجاع صورة افتراضية
                return BASE_URL . '/assets/images/default-avatar.png';
            }
            
        } catch (Exception $e) {
            error_log("Get profile image URL error: " . $e->getMessage());
            return BASE_URL . '/assets/images/default-avatar.png';
        }
    }
    
    /**
     * الحصول على عمر الحساب
     * Get account age
     */
    private function getAccountAge($createdAt) {
        $created = new DateTime($createdAt);
        $now = new DateTime();
        $diff = $now->diff($created);
        
        return $diff->days;
    }
    
    /**
     * الحصول على إحصائيات الملف الشخصي
     * Get profile statistics
     */
    public function getProfileStats($userId) {
        try {
            $stats = [];
            
            // عدد العقود (للأمناء فقط)
            $user = $this->getProfile($userId);
            if ($user && $user['role_name'] === 'Notary') {
                $stats['total_contracts'] = $this->database->count('contracts', 'notary_id = ?', [$userId]);
                $stats['pending_contracts'] = $this->database->count('contracts', 'notary_id = ? AND status = ?', [$userId, 'pending']);
                $stats['approved_contracts'] = $this->database->count('contracts', 'notary_id = ? AND status = ?', [$userId, 'approved']);
                
                // آخر عقد
                $lastContract = $this->database->selectOne(
                    "SELECT created_at FROM contracts WHERE notary_id = ? ORDER BY created_at DESC LIMIT 1",
                    [$userId]
                );
                $stats['last_contract_date'] = $lastContract ? $lastContract['created_at'] : null;
            }
            
            // عدد الإشعارات غير المقروءة
            $stats['unread_notifications'] = getUnreadNotificationsCount($userId);
            
            // آخر نشاط
            $lastActivity = $this->database->selectOne(
                "SELECT timestamp FROM log_access WHERE user_id = ? ORDER BY timestamp DESC LIMIT 1",
                [$userId]
            );
            $stats['last_activity'] = $lastActivity ? $lastActivity['timestamp'] : null;
            
            // عدد مرات تسجيل الدخول
            $stats['login_count'] = $this->database->count('log_access', 'user_id = ? AND action = ?', [$userId, 'login']);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Get profile stats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * تحديث إعدادات الخصوصية
     * Update privacy settings
     */
    public function updatePrivacySettings($userId, $settings) {
        try {
            // التحقق من الصلاحية
            $currentUser = getCurrentUser();
            if ($currentUser['user_id'] != $userId && $currentUser['role_name'] !== 'Administrator') {
                return ['success' => false, 'message' => 'ليس لديك الصلاحية لتحديث هذه الإعدادات'];
            }
            
            // إعدادات الخصوصية المدعومة
            $allowedSettings = [
                'show_email' => 'boolean',
                'show_phone' => 'boolean',
                'allow_notifications' => 'boolean',
                'show_last_login' => 'boolean'
            ];
            
            $updateFields = [];
            $updateValues = [];
            
            foreach ($allowedSettings as $setting => $type) {
                if (isset($settings[$setting])) {
                    $value = $type === 'boolean' ? (bool)$settings[$setting] : $settings[$setting];
                    $updateFields[] = "$setting = ?";
                    $updateValues[] = $value;
                }
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'message' => 'لا توجد إعدادات للتحديث'];
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
                logActivity(
                    $currentUser['user_id'],
                    'update_privacy_settings',
                    "Updated privacy settings for user ID: $userId",
                    'user',
                    $userId
                );
                
                return ['success' => true, 'message' => 'تم تحديث إعدادات الخصوصية بنجاح'];
            } else {
                return ['success' => false, 'message' => 'فشل في تحديث إعدادات الخصوصية'];
            }
            
        } catch (Exception $e) {
            error_log("Update privacy settings error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تحديث إعدادات الخصوصية'];
        }
    }
    
    /**
     * الحصول على سجل النشاط
     * Get activity log
     */
    public function getActivityLog($userId, $limit = 20) {
        try {
            // التحقق من الصلاحية
            $currentUser = getCurrentUser();
            if ($currentUser['user_id'] != $userId && $currentUser['role_name'] !== 'Administrator') {
                return [];
            }
            
            return $this->database->select(
                "SELECT action, details, ip_address, timestamp 
                 FROM log_access 
                 WHERE user_id = ? 
                 ORDER BY timestamp DESC 
                 LIMIT ?",
                [$userId, $limit]
            );
            
        } catch (Exception $e) {
            error_log("Get activity log error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * تصدير الملف الشخصي
     * Export profile data
     */
    public function exportProfile($userId, $format = 'json') {
        try {
            // التحقق من الصلاحية
            $currentUser = getCurrentUser();
            if ($currentUser['user_id'] != $userId && $currentUser['role_name'] !== 'Administrator') {
                return ['success' => false, 'message' => 'ليس لديك الصلاحية لتصدير هذا الملف الشخصي'];
            }
            
            // جمع البيانات
            $profile = $this->getProfile($userId);
            $stats = $this->getProfileStats($userId);
            $activityLog = $this->getActivityLog($userId, 100);
            
            $exportData = [
                'profile' => $profile,
                'statistics' => $stats,
                'activity_log' => $activityLog,
                'export_date' => date('Y-m-d H:i:s')
            ];
            
            // تسجيل العملية
            logActivity(
                $currentUser['user_id'],
                'export_profile',
                "Exported profile data for user ID: $userId",
                'user',
                $userId
            );
            
            switch ($format) {
                case 'csv':
                    return $this->exportToCsv($exportData);
                case 'xml':
                    return $this->exportToXml($exportData);
                default:
                    return json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            
        } catch (Exception $e) {
            error_log("Export profile error: " . $e->getMessage());
            return ['success' => false, 'message' => 'حدث خطأ أثناء تصدير الملف الشخصي'];
        }
    }
    
    /**
     * تصدير إلى CSV
     * Export to CSV
     */
    private function exportToCsv($data) {
        $output = "البيانات الشخصية\n";
        $output .= "الاسم,البريد الإلكتروني,الهاتف,الدور,تاريخ الإنشاء\n";
        $profile = $data['profile'];
        $output .= sprintf(
            '"%s","%s","%s","%s","%s"' . "\n",
            $profile['full_name'],
            $profile['email'],
            $profile['phone_number'] ?? '',
            $profile['role_name'],
            $profile['created_at']
        );
        
        return $output;
    }
    
    /**
     * تصدير إلى XML
     * Export to XML
     */
    private function exportToXml($data) {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<profile>\n";
        
        foreach ($data['profile'] as $key => $value) {
            $xml .= "  <$key>" . htmlspecialchars($value) . "</$key>\n";
        }
        
        $xml .= "</profile>";
        return $xml;
    }
}

// إنشاء مثيل عام لإدارة الملف الشخصي
$profileManager = new ProfileManager($database, $fileUpload);
?>

