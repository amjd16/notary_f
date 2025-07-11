<?php
/**
 * ملف الوظائف المساعدة
 * Helper Functions File
 * 
 * يحتوي على وظائف مساعدة عامة للنظام
 * Contains general helper functions for the system
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

/**
 * تنظيف وتأمين البيانات المدخلة
 * Clean and secure input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * التحقق من صحة البريد الإلكتروني
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * التحقق من قوة كلمة المرور
 * Validate password strength
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "كلمة المرور يجب أن تكون " . PASSWORD_MIN_LENGTH . " أحرف على الأقل";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "كلمة المرور يجب أن تحتوي على حرف كبير واحد على الأقل";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "كلمة المرور يجب أن تحتوي على حرف صغير واحد على الأقل";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "كلمة المرور يجب أن تحتوي على رقم واحد على الأقل";
    }
    
    return empty($errors) ? true : $errors;
}

/**
 * تنسيق التاريخ للعرض
 * Format date for display
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    if (empty($date)) return '';
    
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

/**
 * تنسيق التاريخ بالعربية
 * Format date in Arabic
 */
function formatDateArabic($date) {
    if (empty($date)) return '';
    
    $months = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];
    
    $dateObj = new DateTime($date);
    $day = $dateObj->format('d');
    $month = $months[(int)$dateObj->format('m')];
    $year = $dateObj->format('Y');
    
    return "$day $month $year";
}

/**
 * تحويل الأرقام إلى العربية
 * Convert numbers to Arabic
 */
function toArabicNumbers($string) {
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    return str_replace($english, $arabic, $string);
}

/**
 * تحويل الأرقام إلى الإنجليزية
 * Convert numbers to English
 */
function toEnglishNumbers($string) {
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    
    return str_replace($arabic, $english, $string);
}

/**
 * تنسيق حجم الملف
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * إنشاء رمز عشوائي
 * Generate random token
 */
function generateRandomToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * إنشاء كلمة مرور عشوائية
 * Generate random password
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

/**
 * التحقق من نوع الملف المسموح
 * Check allowed file type
 */
function isAllowedFileType($filename) {
    $allowedTypes = json_decode(ALLOWED_FILE_TYPES, true);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    return in_array($extension, $allowedTypes);
}

/**
 * إنشاء اسم ملف فريد
 * Generate unique filename
 */
function generateUniqueFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $basename = pathinfo($originalName, PATHINFO_FILENAME);
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    
    return $basename . '_' . $timestamp . '_' . $random . '.' . $extension;
}

/**
 * إرسال إشعار للمستخدم
 * Send notification to user
 */
function sendNotification($userId, $message, $type = 'info', $link = null) {
    global $database;
    
    return $database->insert(
        "INSERT INTO notifications (user_id, message, notification_type, link) VALUES (?, ?, ?, ?)",
        [$userId, $message, $type, $link]
    );
}

/**
 * الحصول على عدد الإشعارات غير المقروءة
 * Get unread notifications count
 */
function getUnreadNotificationsCount($userId) {
    global $database;
    
    return $database->count('notifications', 'user_id = ? AND is_read = 0', [$userId]);
}

/**
 * تسجيل عملية في سجل النظام
 * Log system activity
 */
function logActivity($userId, $action, $details = null) {
    global $database;
    
    return $database->insert(
        "INSERT INTO log_access (user_id, action, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?)",
        [
            $userId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $details
        ]
    );
}

/**
 * التحقق من صلاحية المستخدم
 * Check user permission
 */
function hasPermission($requiredRole) {
    if (!isset($_SESSION['role_name'])) {
        return false;
    }
    
    $userRole = $_SESSION['role_name'];
    
    // مدير النظام له صلاحيات كاملة
    if ($userRole === 'Administrator') {
        return true;
    }
    
    // التحقق من الدور المطلوب
    return $userRole === $requiredRole;
}

/**
 * إعادة توجيه المستخدم
 * Redirect user
 */
function redirect($url, $permanent = false) {
    if ($permanent) {
        header('HTTP/1.1 301 Moved Permanently');
    }
    header('Location: ' . $url);
    exit;
}

/**
 * عرض رسالة خطأ وإيقاف التنفيذ
 * Display error message and stop execution
 */
function showError($message, $code = 500) {
    http_response_code($code);
    include 'templates/error.php';
    exit;
}

/**
 * التحقق من طلب AJAX
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * إرجاع استجابة JSON
 * Return JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * تشفير البيانات الحساسة
 * Encrypt sensitive data
 */
function encryptData($data) {
    $key = ENCRYPTION_KEY;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

/**
 * فك تشفير البيانات
 * Decrypt data
 */
function decryptData($data) {
    $key = ENCRYPTION_KEY;
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
}

/**
 * التحقق من رمز CSRF
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * إنشاء رمز CSRF
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * تنظيف النص من HTML
 * Strip HTML from text
 */
function stripHtml($text) {
    return strip_tags($text);
}

/**
 * اقتطاع النص
 * Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * التحقق من صحة رقم الهاتف اليمني
 * Validate Yemeni phone number
 */
function validateYemeniPhone($phone) {
    // إزالة المسافات والرموز
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // أنماط أرقام الهواتف اليمنية
    $patterns = [
        '/^(\+967|00967|967)?[17][0-9]{8}$/', // أرقام الهاتف المحمول
        '/^(\+967|00967|967)?[1-7][0-9]{6,7}$/' // أرقام الهاتف الثابت
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $phone)) {
            return true;
        }
    }
    
    return false;
}

/**
 * تحويل النص إلى URL صديق
 * Convert text to URL-friendly slug
 */
function createSlug($text) {
    // تحويل النص إلى أحرف صغيرة
    $text = mb_strtolower($text, 'UTF-8');
    
    // استبدال المسافات بشرطات
    $text = preg_replace('/\s+/', '-', $text);
    
    // إزالة الأحرف غير المرغوبة
    $text = preg_replace('/[^a-z0-9\-\u0600-\u06FF]/', '', $text);
    
    // إزالة الشرطات المتتالية
    $text = preg_replace('/-+/', '-', $text);
    
    // إزالة الشرطات من البداية والنهاية
    return trim($text, '-');
}

/**
 * حساب المسافة بين تاريخين
 * Calculate time difference between dates
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'منذ لحظات';
    if ($time < 3600) return 'منذ ' . floor($time/60) . ' دقيقة';
    if ($time < 86400) return 'منذ ' . floor($time/3600) . ' ساعة';
    if ($time < 2592000) return 'منذ ' . floor($time/86400) . ' يوم';
    if ($time < 31536000) return 'منذ ' . floor($time/2592000) . ' شهر';
    
    return 'منذ ' . floor($time/31536000) . ' سنة';
}

/**
 * التحقق من وجود الإنترنت
 * Check internet connection
 */
function hasInternetConnection() {
    $connected = @fsockopen("www.google.com", 80);
    if ($connected) {
        fclose($connected);
        return true;
    }
    return false;
}

/**
 * إنشاء رقم مرجعي فريد
 * Generate unique reference number
 */
function generateReferenceNumber($prefix = 'REF') {
    $year = date('Y');
    $month = date('m');
    $day = date('d');
    $time = date('His');
    $random = mt_rand(100, 999);
    
    return $prefix . $year . $month . $day . $time . $random;
}
?>

