<?php
/**
 * ملف الاتصال بقاعدة البيانات
 * Database Connection File
 * 
 * يحتوي على فئة الاتصال بقاعدة البيانات والعمليات الأساسية
 * Contains database connection class and basic operations
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

class Database {
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    private $pdo;
    private $error;

    public function __construct() {
        $this->host = DB_HOST;
        $this->dbname = DB_NAME;
        $this->username = DB_USERNAME;
        $this->password = DB_PASSWORD;
        $this->charset = DB_CHARSET;
        
        $this->connect();
    }

    /**
     * الاتصال بقاعدة البيانات
     * Connect to database
     */
    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
        ];

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->logError("Database connection failed: " . $e->getMessage());
            die("خطأ في الاتصال بقاعدة البيانات");
        }
    }

    /**
     * الحصول على كائن PDO
     * Get PDO object
     */
    public function getPdo() {
        return $this->pdo;
    }

    /**
     * تنفيذ استعلام SELECT
     * Execute SELECT query
     */
    public function select($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError("Select query failed: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }

    /**
     * تنفيذ استعلام SELECT لسجل واحد
     * Execute SELECT query for single record
     */
    public function selectOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->logError("SelectOne query failed: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }

    /**
     * تنفيذ استعلام INSERT
     * Execute INSERT query
     */
    public function insert($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            return $result ? $this->pdo->lastInsertId() : false;
        } catch (PDOException $e) {
            $this->logError("Insert query failed: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }

    /**
     * تنفيذ استعلام UPDATE
     * Execute UPDATE query
     */
    public function update($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError("Update query failed: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }

    /**
     * تنفيذ استعلام DELETE
     * Execute DELETE query
     */
    public function delete($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError("Delete query failed: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }

    /**
     * تنفيذ استعلام عام
     * Execute general query
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            $this->logError("Execute query failed: " . $e->getMessage() . " | SQL: " . $sql);
            return false;
        }
    }

    /**
     * بدء معاملة
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * تأكيد المعاملة
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * إلغاء المعاملة
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }

    /**
     * الحصول على عدد السجلات
     * Get record count
     */
    public function count($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        
        $result = $this->selectOne($sql, $params);
        return $result ? $result['count'] : 0;
    }

    /**
     * التحقق من وجود سجل
     * Check if record exists
     */
    public function exists($table, $where, $params = []) {
        return $this->count($table, $where, $params) > 0;
    }

    /**
     * تسجيل الأخطاء
     * Log errors
     */
    private function logError($message) {
        if (LOG_ERRORS) {
            $logFile = LOG_PATH . 'database_errors.log';
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * الحصول على آخر خطأ
     * Get last error
     */
    public function getError() {
        return $this->error;
    }

    /**
     * إغلاق الاتصال
     * Close connection
     */
    public function close() {
        $this->pdo = null;
    }

    /**
     * تنظيف البيانات
     * Sanitize data
     */
    public function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * تشفير كلمة المرور
     * Hash password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * التحقق من كلمة المرور
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * إنشاء رمز عشوائي
     * Generate random token
     */
    public function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    /**
     * تشفير البيانات
     * Encrypt data
     */
    public function encrypt($data) {
        $key = ENCRYPTION_KEY;
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * فك تشفير البيانات
     * Decrypt data
     */
    public function decrypt($data) {
        $key = ENCRYPTION_KEY;
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
    }
}

// إنشاء مثيل عام لقاعدة البيانات
$database = new Database();
?>

