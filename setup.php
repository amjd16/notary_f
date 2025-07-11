<?php
/**
 * ملف الإعداد الأولي لنظام إدارة الأمناء الشرعيين
 * Initial Setup Configuration for Notary Management System
 * 
 * هذا الملف يستخدم لإعداد النظام لأول مرة
 * This file is used for initial system setup
 */

// منع الوصول المباشر للملف
if (!defined('SETUP_MODE')) {
    define('SETUP_MODE', true);
}

// إعدادات قاعدة البيانات الافتراضية
$default_config = [
    'db_host' => 'localhost',
    'db_name' => 'notary_system',
    'db_username' => 'root',
    'db_password' => '',
    'db_charset' => 'utf8mb4',
    
    // إعدادات النظام الأساسية
    'system_name' => 'نظام إدارة الأمناء الشرعيين',
    'system_version' => '1.0.0',
    'default_language' => 'ar',
    'timezone' => 'Asia/Aden',
    
    // إعدادات المدير الافتراضي
    'admin_username' => 'admin',
    'admin_password' => 'admin123',
    'admin_email' => 'admin@notary.gov.ye',
    'admin_first_name' => 'مدير',
    'admin_last_name' => 'النظام',
    
    // إعدادات الأمان
    'session_timeout' => 3600, // ساعة واحدة
    'password_min_length' => 8,
    'enable_biometric_auth' => false,
    
    // إعدادات الملفات
    'upload_max_size' => '10M',
    'allowed_file_types' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
    
    // إعدادات التصميم
    'theme_color' => '#2c3e50',
    'font_family' => 'Tajawal',
    'rtl_support' => true
];

/**
 * دالة لإنشاء ملف الإعدادات
 */
function createConfigFile($config) {
    $config_content = "<?php\n";
    $config_content .= "/**\n";
    $config_content .= " * ملف إعدادات نظام إدارة الأمناء الشرعيين\n";
    $config_content .= " * Notary Management System Configuration\n";
    $config_content .= " * \n";
    $config_content .= " * تم إنشاؤه تلقائياً في: " . date('Y-m-d H:i:s') . "\n";
    $config_content .= " * Generated automatically on: " . date('Y-m-d H:i:s') . "\n";
    $config_content .= " */\n\n";
    
    $config_content .= "// منع الوصول المباشر\n";
    $config_content .= "if (!defined('SYSTEM_ACCESS')) {\n";
    $config_content .= "    die('Access denied');\n";
    $config_content .= "}\n\n";
    
    foreach ($config as $key => $value) {
        if (is_string($value)) {
            $config_content .= "define('" . strtoupper($key) . "', '" . addslashes($value) . "');\n";
        } elseif (is_bool($value)) {
            $config_content .= "define('" . strtoupper($key) . "', " . ($value ? 'true' : 'false') . ");\n";
        } elseif (is_array($value)) {
            $config_content .= "define('" . strtoupper($key) . "', '" . json_encode($value, JSON_UNESCAPED_UNICODE) . "');\n";
        } else {
            $config_content .= "define('" . strtoupper($key) . "', " . $value . ");\n";
        }
    }
    
    $config_content .= "\n// إعدادات إضافية\n";
    $config_content .= "define('BASE_URL', 'http://' . \$_SERVER['HTTP_HOST'] . dirname(\$_SERVER['SCRIPT_NAME']));\n";
    $config_content .= "define('SYSTEM_PATH', dirname(__FILE__));\n";
    $config_content .= "define('UPLOAD_PATH', SYSTEM_PATH . '/uploads/');\n";
    $config_content .= "define('LOG_PATH', SYSTEM_PATH . '/logs/');\n";
    
    return $config_content;
}

/**
 * دالة لإنشاء قاعدة البيانات والجداول
 */
function createDatabase($config) {
    try {
        // الاتصال بـ MySQL بدون تحديد قاعدة بيانات
        $pdo = new PDO(
            "mysql:host={$config['db_host']};charset={$config['db_charset']}", 
            $config['db_username'], 
            $config['db_password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // إنشاء قاعدة البيانات
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // الاتصال بقاعدة البيانات المنشأة
        $pdo = new PDO(
            "mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['db_charset']}", 
            $config['db_username'], 
            $config['db_password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        return $pdo;
        
    } catch (PDOException $e) {
        throw new Exception("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
    }
}

/**
 * دالة لإنشاء المدير الافتراضي
 */
function createDefaultAdmin($pdo, $config) {
    $password_hash = password_hash($config['admin_password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password_hash, role_id, first_name, last_name, email, is_active, created_at) 
        VALUES (?, ?, 1, ?, ?, ?, 1, NOW())
    ");
    
    return $stmt->execute([
        $config['admin_username'],
        $password_hash,
        $config['admin_first_name'],
        $config['admin_last_name'],
        $config['admin_email']
    ]);
}

// معالجة طلب الإعداد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_submit'])) {
    try {
        // دمج الإعدادات المرسلة مع الافتراضية
        $config = array_merge($default_config, $_POST);
        
        // إنشاء قاعدة البيانات
        $pdo = createDatabase($config);
        
        // إنشاء ملف الإعدادات
        $config_content = createConfigFile($config);
        file_put_contents(__DIR__ . '/config/config.php', $config_content);
        
        // تضمين ملف إنشاء الجداول
        include __DIR__ . '/config/create_tables.php';
        createTables($pdo);
        
        // إنشاء المدير الافتراضي
        createDefaultAdmin($pdo, $config);
        
        $success_message = "تم إعداد النظام بنجاح! يمكنك الآن تسجيل الدخول.";
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعداد نظام إدارة الأمناء الشرعيين</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .setup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .setup-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .setup-header p {
            color: #7f8c8d;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
            font-family: 'Tajawal', sans-serif;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .setup-btn {
            width: 100%;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s;
            font-family: 'Tajawal', sans-serif;
        }
        
        .setup-btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .section-title {
            color: #2c3e50;
            font-size: 20px;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .setup-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>إعداد نظام إدارة الأمناء الشرعيين</h1>
            <p>مرحباً بك! يرجى ملء البيانات التالية لإعداد النظام</p>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
                <br><br>
                <a href="index.php" style="color: #155724; text-decoration: underline;">انتقل إلى صفحة تسجيل الدخول</a>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!isset($success_message)): ?>
        <form method="POST" action="">
            <div class="section-title">إعدادات قاعدة البيانات</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="db_host">خادم قاعدة البيانات</label>
                    <input type="text" id="db_host" name="db_host" value="<?php echo $default_config['db_host']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_name">اسم قاعدة البيانات</label>
                    <input type="text" id="db_name" name="db_name" value="<?php echo $default_config['db_name']; ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="db_username">اسم المستخدم</label>
                    <input type="text" id="db_username" name="db_username" value="<?php echo $default_config['db_username']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="db_password">كلمة المرور</label>
                    <input type="password" id="db_password" name="db_password" value="<?php echo $default_config['db_password']; ?>">
                </div>
            </div>
            
            <div class="section-title">إعدادات المدير</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="admin_username">اسم المستخدم للمدير</label>
                    <input type="text" id="admin_username" name="admin_username" value="<?php echo $default_config['admin_username']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="admin_password">كلمة مرور المدير</label>
                    <input type="password" id="admin_password" name="admin_password" value="<?php echo $default_config['admin_password']; ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="admin_first_name">الاسم الأول</label>
                    <input type="text" id="admin_first_name" name="admin_first_name" value="<?php echo $default_config['admin_first_name']; ?>" required>
                </div>
                <div class="form-group">
                    <label for="admin_last_name">الاسم الأخير</label>
                    <input type="text" id="admin_last_name" name="admin_last_name" value="<?php echo $default_config['admin_last_name']; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="admin_email">البريد الإلكتروني للمدير</label>
                <input type="email" id="admin_email" name="admin_email" value="<?php echo $default_config['admin_email']; ?>" required>
            </div>
            
            <div class="section-title">إعدادات النظام</div>
            
            <div class="form-group">
                <label for="system_name">اسم النظام</label>
                <input type="text" id="system_name" name="system_name" value="<?php echo $default_config['system_name']; ?>" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="default_language">اللغة الافتراضية</label>
                    <select id="default_language" name="default_language">
                        <option value="ar" <?php echo $default_config['default_language'] === 'ar' ? 'selected' : ''; ?>>العربية</option>
                        <option value="en" <?php echo $default_config['default_language'] === 'en' ? 'selected' : ''; ?>>English</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="timezone">المنطقة الزمنية</label>
                    <select id="timezone" name="timezone">
                        <option value="Asia/Aden" <?php echo $default_config['timezone'] === 'Asia/Aden' ? 'selected' : ''; ?>>آسيا/عدن</option>
                        <option value="Asia/Riyadh" <?php echo $default_config['timezone'] === 'Asia/Riyadh' ? 'selected' : ''; ?>>آسيا/الرياض</option>
                        <option value="UTC" <?php echo $default_config['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" name="setup_submit" class="setup-btn">
                إعداد النظام
            </button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>

