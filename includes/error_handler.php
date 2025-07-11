<?php
/**
 * ملف معالجة الأخطاء
 * Error Handler File
 * 
 * يحتوي على وظائف معالجة وتسجيل الأخطاء
 * Contains error handling and logging functions
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

class ErrorHandler {
    
    private static $logFile;
    private static $initialized = false;
    
    /**
     * تهيئة معالج الأخطاء
     * Initialize error handler
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        self::$logFile = LOG_PATH . 'errors.log';
        
        // إنشاء مجلد السجلات إذا لم يكن موجوداً
        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0755, true);
        }
        
        // تعيين معالجات الأخطاء
        set_error_handler([__CLASS__, 'handleError']);
        set_exception_handler([__CLASS__, 'handleException']);
        register_shutdown_function([__CLASS__, 'handleFatalError']);
        
        self::$initialized = true;
    }
    
    /**
     * معالجة الأخطاء العادية
     * Handle regular errors
     */
    public static function handleError($severity, $message, $file, $line) {
        // تجاهل الأخطاء المكبوتة بـ @
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorType = self::getErrorType($severity);
        $errorMessage = "[$errorType] $message in $file on line $line";
        
        // تسجيل الخطأ
        self::logError($errorMessage, $severity);
        
        // عرض الخطأ في وضع التطوير
        if (DEBUG_MODE) {
            self::displayError($errorMessage, $severity);
        }
        
        // إيقاف التنفيذ للأخطاء الخطيرة
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            self::handleFatalError();
        }
        
        return true;
    }
    
    /**
     * معالجة الاستثناءات
     * Handle exceptions
     */
    public static function handleException($exception) {
        $errorMessage = sprintf(
            "[EXCEPTION] %s in %s on line %d\nStack trace:\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        // تسجيل الاستثناء
        self::logError($errorMessage, E_ERROR);
        
        // عرض صفحة خطأ مناسبة
        if (!DEBUG_MODE) {
            self::showErrorPage(500, 'حدث خطأ داخلي في الخادم');
        } else {
            self::displayError($errorMessage, E_ERROR);
        }
        
        exit(1);
    }
    
    /**
     * معالجة الأخطاء الخطيرة
     * Handle fatal errors
     */
    public static function handleFatalError() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $errorMessage = sprintf(
                "[FATAL] %s in %s on line %d",
                $error['message'],
                $error['file'],
                $error['line']
            );
            
            // تسجيل الخطأ الخطير
            self::logError($errorMessage, $error['type']);
            
            // عرض صفحة خطأ
            if (!DEBUG_MODE) {
                self::showErrorPage(500, 'حدث خطأ خطير في النظام');
            }
        }
    }
    
    /**
     * تسجيل الخطأ في ملف السجل
     * Log error to file
     */
    private static function logError($message, $severity = E_ERROR) {
        if (!LOG_ERRORS) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $userId = $_SESSION['user_id'] ?? 'guest';
        
        $logEntry = sprintf(
            "[%s] [IP: %s] [User: %s] [URI: %s] %s\nUser Agent: %s\n%s\n",
            $timestamp,
            $ip,
            $userId,
            $requestUri,
            $message,
            $userAgent,
            str_repeat('-', 80)
        );
        
        // كتابة السجل
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // تدوير ملف السجل إذا كان كبيراً جداً (أكبر من 10 ميجابايت)
        if (file_exists(self::$logFile) && filesize(self::$logFile) > 10 * 1024 * 1024) {
            self::rotateLogFile();
        }
        
        // إرسال تنبيه للمدير في حالة الأخطاء الخطيرة
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            self::notifyAdmin($message);
        }
    }
    
    /**
     * عرض الخطأ في وضع التطوير
     * Display error in debug mode
     */
    private static function displayError($message, $severity) {
        $errorClass = self::getErrorClass($severity);
        
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border: 1px solid #f5c6cb; border-radius: 5px; font-family: monospace;'>";
        echo "<strong>خطأ في النظام:</strong><br>";
        echo "<span class='$errorClass'>" . htmlspecialchars($message) . "</span>";
        echo "</div>";
    }
    
    /**
     * عرض صفحة خطأ مخصصة
     * Show custom error page
     */
    private static function showErrorPage($code, $message) {
        http_response_code($code);
        
        // محاولة تحميل قالب الخطأ المخصص
        $errorTemplate = "templates/error_{$code}.php";
        if (file_exists($errorTemplate)) {
            include $errorTemplate;
        } else {
            // صفحة خطأ افتراضية
            self::showDefaultErrorPage($code, $message);
        }
        
        exit;
    }
    
    /**
     * عرض صفحة خطأ افتراضية
     * Show default error page
     */
    private static function showDefaultErrorPage($code, $message) {
        ?>
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>خطأ <?php echo $code; ?> - <?php echo SYSTEM_NAME; ?></title>
            <style>
                body {
                    font-family: 'Tajawal', Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0;
                    padding: 0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .error-container {
                    background: white;
                    padding: 40px;
                    border-radius: 15px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 500px;
                    width: 90%;
                }
                .error-code {
                    font-size: 72px;
                    font-weight: bold;
                    color: #e74c3c;
                    margin-bottom: 20px;
                }
                .error-message {
                    font-size: 24px;
                    color: #2c3e50;
                    margin-bottom: 30px;
                }
                .error-description {
                    color: #7f8c8d;
                    margin-bottom: 30px;
                    line-height: 1.6;
                }
                .back-button {
                    background: #3498db;
                    color: white;
                    padding: 12px 30px;
                    border: none;
                    border-radius: 5px;
                    text-decoration: none;
                    display: inline-block;
                    transition: background 0.3s;
                }
                .back-button:hover {
                    background: #2980b9;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-code"><?php echo $code; ?></div>
                <div class="error-message"><?php echo htmlspecialchars($message); ?></div>
                <div class="error-description">
                    نعتذر عن هذا الخطأ. يرجى المحاولة مرة أخرى أو التواصل مع الدعم الفني.
                </div>
                <a href="javascript:history.back()" class="back-button">العودة للخلف</a>
                <a href="index.php" class="back-button" style="margin-right: 10px;">الصفحة الرئيسية</a>
            </div>
        </body>
        </html>
        <?php
    }
    
    /**
     * الحصول على نوع الخطأ
     * Get error type
     */
    private static function getErrorType($severity) {
        $types = [
            E_ERROR => 'FATAL ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE ERROR',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE ERROR',
            E_CORE_WARNING => 'CORE WARNING',
            E_COMPILE_ERROR => 'COMPILE ERROR',
            E_COMPILE_WARNING => 'COMPILE WARNING',
            E_USER_ERROR => 'USER ERROR',
            E_USER_WARNING => 'USER WARNING',
            E_USER_NOTICE => 'USER NOTICE',
            E_STRICT => 'STRICT NOTICE',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER DEPRECATED'
        ];
        
        return $types[$severity] ?? 'UNKNOWN ERROR';
    }
    
    /**
     * الحصول على فئة CSS للخطأ
     * Get CSS class for error
     */
    private static function getErrorClass($severity) {
        if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            return 'error-fatal';
        } elseif (in_array($severity, [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING])) {
            return 'error-warning';
        } else {
            return 'error-notice';
        }
    }
    
    /**
     * تدوير ملف السجل
     * Rotate log file
     */
    private static function rotateLogFile() {
        $backupFile = self::$logFile . '.' . date('Y-m-d-H-i-s') . '.bak';
        rename(self::$logFile, $backupFile);
        
        // حذف الملفات القديمة (أكثر من 30 يوم)
        $files = glob(LOG_PATH . 'errors.log.*.bak');
        foreach ($files as $file) {
            if (filemtime($file) < time() - (30 * 24 * 60 * 60)) {
                unlink($file);
            }
        }
    }
    
    /**
     * إرسال تنبيه للمدير
     * Notify admin
     */
    private static function notifyAdmin($message) {
        // يمكن تطوير هذه الوظيفة لإرسال بريد إلكتروني أو إشعار
        // للمدير في حالة حدوث أخطاء خطيرة
        
        // مثال: إضافة إشعار في قاعدة البيانات
        try {
            global $database;
            if ($database) {
                // البحث عن المدير
                $admin = $database->selectOne(
                    "SELECT user_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'Administrator' LIMIT 1"
                );
                
                if ($admin) {
                    $database->insert(
                        "INSERT INTO notifications (user_id, message, notification_type) VALUES (?, ?, ?)",
                        [$admin['user_id'], "خطأ خطير في النظام: " . substr($message, 0, 200), 'error']
                    );
                }
            }
        } catch (Exception $e) {
            // تجاهل الأخطاء في إرسال التنبيه لتجنب حلقة لا نهائية
        }
    }
    
    /**
     * تسجيل خطأ مخصص
     * Log custom error
     */
    public static function logCustomError($message, $context = []) {
        $contextStr = empty($context) ? '' : ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        self::logError("[CUSTOM] $message$contextStr", E_USER_ERROR);
    }
    
    /**
     * تسجيل تحذير مخصص
     * Log custom warning
     */
    public static function logCustomWarning($message, $context = []) {
        $contextStr = empty($context) ? '' : ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        self::logError("[CUSTOM WARNING] $message$contextStr", E_USER_WARNING);
    }
    
    /**
     * الحصول على سجل الأخطاء
     * Get error log
     */
    public static function getErrorLog($lines = 100) {
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $content = file_get_contents(self::$logFile);
        $entries = explode(str_repeat('-', 80), $content);
        
        // أخذ آخر عدد من الإدخالات
        $entries = array_slice(array_filter($entries), -$lines);
        
        return array_reverse($entries);
    }
    
    /**
     * مسح سجل الأخطاء
     * Clear error log
     */
    public static function clearErrorLog() {
        if (file_exists(self::$logFile)) {
            return unlink(self::$logFile);
        }
        return true;
    }
}

// تهيئة معالج الأخطاء
ErrorHandler::init();
?>

