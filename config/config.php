<?php
/**
 * ملف إعدادات نظام إدارة الأمناء الشرعيين
 * Notary Management System Configuration
 * 
 * يحتوي على الإعدادات الأساسية للنظام
 * Contains basic system configurations
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'notary_h');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_CHARSET', 'utf8mb4');

// إعدادات النظام الأساسية
define('SYSTEM_NAME', 'نظام إدارة الأمناء الشرعيين');
define('SYSTEM_VERSION', '1.0.0');
define('DEFAULT_LANGUAGE', 'ar');
define('TIMEZONE', 'Asia/Aden');

// إعدادات الأمان
define('SESSION_TIMEOUT', 3600); // ساعة واحدة
define('PASSWORD_MIN_LENGTH', 8);
define('ENABLE_BIOMETRIC_AUTH', false);

// إعدادات الملفات
define('UPLOAD_MAX_SIZE', '10M');
define('ALLOWED_FILE_TYPES', json_encode(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']));

// إعدادات التصميم
define('THEME_COLOR', '#2c3e50');
define('FONT_FAMILY', 'Tajawal');
define('RTL_SUPPORT', true);

// إعدادات إضافية
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']));
define('SYSTEM_PATH', dirname(__FILE__));
define('UPLOAD_PATH', SYSTEM_PATH . '/uploads/');
define('LOG_PATH', SYSTEM_PATH . '/logs/');

// إعدادات البريد الإلكتروني (اختيارية)
define('MAIL_HOST', '');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_FROM_EMAIL', 'noreply@notary.gov.ye');
define('MAIL_FROM_NAME', 'نظام إدارة الأمناء الشرعيين');

// إعدادات التشفير
define('ENCRYPTION_KEY', 'your-secret-encryption-key-here');

// إعدادات التطبيق
define('DEBUG_MODE', false);
define('LOG_ERRORS', true);
define('MAINTENANCE_MODE', false);

// تعيين المنطقة الزمنية
date_default_timezone_set(TIMEZONE);

// تعيين الترميز
mb_internal_encoding('UTF-8');

// إعدادات الجلسة
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);

// بدء الجلسة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

