<?php
/**
 * ملف تسجيل العمليات
 * Logger File
 * 
 * يحتوي على وظائف تسجيل العمليات والأنشطة
 * Contains activity logging functions
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

class Logger {
    
    private static $logPath;
    private static $initialized = false;
    
    /**
     * تهيئة نظام التسجيل
     * Initialize logging system
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        self::$logPath = LOG_PATH;
        
        // إنشاء مجلد السجلات إذا لم يكن موجوداً
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
        
        self::$initialized = true;
    }
    
    /**
     * تسجيل نشاط المستخدم
     * Log user activity
     */
    public static function logActivity($userId, $action, $details = null, $resourceType = null, $resourceId = null) {
        self::init();
        
        global $database;
        
        try {
            // تسجيل في قاعدة البيانات
            $database->insert(
                "INSERT INTO log_access (user_id, action, ip_address, user_agent, details, timestamp) VALUES (?, ?, ?, ?, ?, NOW())",
                [
                    $userId,
                    $action,
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $details
                ]
            );
            
            // تسجيل في ملف منفصل
            self::writeToFile('activity', [
                'user_id' => $userId,
                'action' => $action,
                'details' => $details,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            self::logError("Failed to log activity: " . $e->getMessage());
        }
    }
    
    /**
     * تسجيل عمليات تسجيل الدخول
     * Log login attempts
     */
    public static function logLogin($username, $success, $reason = null) {
        self::init();
        
        $logData = [
            'username' => $username,
            'success' => $success,
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::writeToFile('login', $logData);
    }
    
    /**
     * تسجيل عمليات قاعدة البيانات
     * Log database operations
     */
    public static function logDatabase($operation, $table, $data = null, $userId = null) {
        self::init();
        
        $logData = [
            'operation' => $operation,
            'table' => $table,
            'data' => $data,
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::writeToFile('database', $logData);
    }
    
    /**
     * تسجيل عمليات الملفات
     * Log file operations
     */
    public static function logFile($operation, $filename, $userId = null, $details = null) {
        self::init();
        
        $logData = [
            'operation' => $operation,
            'filename' => $filename,
            'user_id' => $userId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::writeToFile('file', $logData);
    }
    
    /**
     * تسجيل الأخطاء الأمنية
     * Log security events
     */
    public static function logSecurity($event, $severity = 'medium', $details = null) {
        self::init();
        
        $logData = [
            'event' => $event,
            'severity' => $severity,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::writeToFile('security', $logData);
        
        // إرسال تنبيه فوري للأحداث الأمنية الخطيرة
        if ($severity === 'high') {
            self::notifySecurityEvent($logData);
        }
    }
    
    /**
     * تسجيل عمليات النظام
     * Log system events
     */
    public static function logSystem($event, $level = 'info', $details = null) {
        self::init();
        
        $logData = [
            'event' => $event,
            'level' => $level,
            'details' => $details,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::writeToFile('system', $logData);
    }
    
    /**
     * تسجيل أداء النظام
     * Log performance metrics
     */
    public static function logPerformance($operation, $duration, $details = null) {
        self::init();
        
        $logData = [
            'operation' => $operation,
            'duration' => $duration,
            'details' => $details,
            'memory_usage' => memory_get_usage(true),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        self::writeToFile('performance', $logData);
    }
    
    /**
     * كتابة البيانات في ملف السجل
     * Write data to log file
     */
    private static function writeToFile($type, $data) {
        $filename = self::$logPath . $type . '_' . date('Y-m-d') . '.log';
        
        $logEntry = date('Y-m-d H:i:s') . ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        
        file_put_contents($filename, $logEntry, FILE_APPEND | LOCK_EX);
        
        // تدوير الملف إذا كان كبيراً
        if (filesize($filename) > 50 * 1024 * 1024) { // 50 ميجابايت
            self::rotateLogFile($filename);
        }
    }
    
    /**
     * تدوير ملف السجل
     * Rotate log file
     */
    private static function rotateLogFile($filename) {
        $backupFile = $filename . '.' . time() . '.bak';
        rename($filename, $backupFile);
        
        // ضغط الملف القديم
        if (function_exists('gzencode')) {
            $content = file_get_contents($backupFile);
            file_put_contents($backupFile . '.gz', gzencode($content));
            unlink($backupFile);
        }
        
        // حذف الملفات القديمة (أكثر من 90 يوم)
        $pattern = dirname($filename) . '/' . basename($filename, '.log') . '_*.log.*.gz';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            if (filemtime($file) < time() - (90 * 24 * 60 * 60)) {
                unlink($file);
            }
        }
    }
    
    /**
     * قراءة سجل معين
     * Read specific log
     */
    public static function readLog($type, $date = null, $lines = 100) {
        self::init();
        
        $date = $date ?: date('Y-m-d');
        $filename = self::$logPath . $type . '_' . $date . '.log';
        
        if (!file_exists($filename)) {
            return [];
        }
        
        $content = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $content = array_slice($content, -$lines);
        
        $logs = [];
        foreach ($content as $line) {
            $parts = explode(' | ', $line, 2);
            if (count($parts) === 2) {
                $logs[] = [
                    'timestamp' => $parts[0],
                    'data' => json_decode($parts[1], true)
                ];
            }
        }
        
        return array_reverse($logs);
    }
    
    /**
     * البحث في السجلات
     * Search logs
     */
    public static function searchLogs($type, $query, $dateFrom = null, $dateTo = null) {
        self::init();
        
        $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-7 days'));
        $dateTo = $dateTo ?: date('Y-m-d');
        
        $results = [];
        $currentDate = $dateFrom;
        
        while ($currentDate <= $dateTo) {
            $logs = self::readLog($type, $currentDate, 10000);
            
            foreach ($logs as $log) {
                $logString = json_encode($log, JSON_UNESCAPED_UNICODE);
                if (stripos($logString, $query) !== false) {
                    $results[] = $log;
                }
            }
            
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
        
        return $results;
    }
    
    /**
     * الحصول على إحصائيات السجلات
     * Get log statistics
     */
    public static function getLogStats($type, $date = null) {
        self::init();
        
        $date = $date ?: date('Y-m-d');
        $logs = self::readLog($type, $date, 10000);
        
        $stats = [
            'total_entries' => count($logs),
            'first_entry' => null,
            'last_entry' => null,
            'hourly_distribution' => array_fill(0, 24, 0)
        ];
        
        if (!empty($logs)) {
            $stats['first_entry'] = $logs[count($logs) - 1]['timestamp'];
            $stats['last_entry'] = $logs[0]['timestamp'];
            
            foreach ($logs as $log) {
                $hour = (int)date('H', strtotime($log['timestamp']));
                $stats['hourly_distribution'][$hour]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * تنظيف السجلات القديمة
     * Clean old logs
     */
    public static function cleanOldLogs($days = 90) {
        self::init();
        
        $cutoffDate = date('Y-m-d', strtotime("-$days days"));
        $pattern = self::$logPath . '*_*.log*';
        $files = glob($pattern);
        
        $deletedCount = 0;
        foreach ($files as $file) {
            $filename = basename($file);
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                if ($matches[1] < $cutoffDate) {
                    unlink($file);
                    $deletedCount++;
                }
            }
        }
        
        return $deletedCount;
    }
    
    /**
     * إرسال تنبيه للأحداث الأمنية
     * Notify security events
     */
    private static function notifySecurityEvent($eventData) {
        global $database;
        
        try {
            // إرسال إشعار لجميع المدراء
            $admins = $database->select(
                "SELECT user_id FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'Administrator'"
            );
            
            foreach ($admins as $admin) {
                $message = "تنبيه أمني: " . $eventData['event'];
                if ($eventData['details']) {
                    $message .= " - " . $eventData['details'];
                }
                
                $database->insert(
                    "INSERT INTO notifications (user_id, message, notification_type) VALUES (?, ?, ?)",
                    [$admin['user_id'], $message, 'security_alert']
                );
            }
        } catch (Exception $e) {
            // تجاهل الأخطاء لتجنب حلقة لا نهائية
        }
    }
    
    /**
     * تسجيل خطأ في نظام التسجيل نفسه
     * Log error in logging system itself
     */
    private static function logError($message) {
        $errorFile = self::$logPath . 'logger_errors.log';
        $logEntry = date('Y-m-d H:i:s') . ' | ' . $message . PHP_EOL;
        file_put_contents($errorFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * تصدير السجلات
     * Export logs
     */
    public static function exportLogs($type, $dateFrom, $dateTo, $format = 'json') {
        $logs = [];
        $currentDate = $dateFrom;
        
        while ($currentDate <= $dateTo) {
            $dailyLogs = self::readLog($type, $currentDate, 10000);
            $logs = array_merge($logs, $dailyLogs);
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
        
        switch ($format) {
            case 'csv':
                return self::exportToCsv($logs);
            case 'xml':
                return self::exportToXml($logs);
            default:
                return json_encode($logs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * تصدير إلى CSV
     * Export to CSV
     */
    private static function exportToCsv($logs) {
        $output = "Timestamp,Data\n";
        foreach ($logs as $log) {
            $output .= '"' . $log['timestamp'] . '","' . str_replace('"', '""', json_encode($log['data'], JSON_UNESCAPED_UNICODE)) . "\"\n";
        }
        return $output;
    }
    
    /**
     * تصدير إلى XML
     * Export to XML
     */
    private static function exportToXml($logs) {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<logs>\n";
        foreach ($logs as $log) {
            $xml .= "  <log>\n";
            $xml .= "    <timestamp>" . htmlspecialchars($log['timestamp']) . "</timestamp>\n";
            $xml .= "    <data>" . htmlspecialchars(json_encode($log['data'], JSON_UNESCAPED_UNICODE)) . "</data>\n";
            $xml .= "  </log>\n";
        }
        $xml .= "</logs>";
        return $xml;
    }
}

// دوال مساعدة سريعة
function logActivity($userId, $action, $details = null, $resourceType = null, $resourceId = null) {
    Logger::logActivity($userId, $action, $details, $resourceType, $resourceId);
}

function logSecurity($event, $severity = 'medium', $details = null) {
    Logger::logSecurity($event, $severity, $details);
}

function logSystem($event, $level = 'info', $details = null) {
    Logger::logSystem($event, $level, $details);
}
?>

