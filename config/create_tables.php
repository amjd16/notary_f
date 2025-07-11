<?php
/**
 * ملف إنشاء جداول قاعدة البيانات
 * Database Tables Creation File
 * 
 * يحتوي على الاستعلامات اللازمة لإنشاء جميع جداول النظام
 * Contains SQL queries to create all system tables
 */

function createTables($pdo) {
    $tables = [
        // جدول الأدوار
        'roles' => "
            CREATE TABLE IF NOT EXISTS roles (
                role_id INT AUTO_INCREMENT PRIMARY KEY,
                role_name VARCHAR(50) UNIQUE NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول المحافظات
        'provinces' => "
            CREATE TABLE IF NOT EXISTS provinces (
                province_id INT AUTO_INCREMENT PRIMARY KEY,
                province_name VARCHAR(255) UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول المديريات
        'districts' => "
            CREATE TABLE IF NOT EXISTS districts (
                district_id INT AUTO_INCREMENT PRIMARY KEY,
                district_name VARCHAR(255) NOT NULL,
                province_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (province_id) REFERENCES provinces(province_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول العزل/القرى
        'villages' => "
            CREATE TABLE IF NOT EXISTS villages (
                village_id INT AUTO_INCREMENT PRIMARY KEY,
                village_name VARCHAR(255) NOT NULL,
                district_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (district_id) REFERENCES districts(district_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول أنواع العقود
        'contract_types' => "
            CREATE TABLE IF NOT EXISTS contract_types (
                contract_type_id INT AUTO_INCREMENT PRIMARY KEY,
                type_name VARCHAR(100) UNIQUE NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول التراخيص
        'licenses' => "
            CREATE TABLE IF NOT EXISTS licenses (
                license_id INT AUTO_INCREMENT PRIMARY KEY,
                license_number VARCHAR(100) UNIQUE NOT NULL,
                issue_date DATE NOT NULL,
                expiry_date DATE NOT NULL,
                status ENUM('active', 'suspended', 'revoked') DEFAULT 'active',
                suspension_reason TEXT,
                revocation_reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول المستخدمين
        'users' => "
            CREATE TABLE IF NOT EXISTS users (
                user_id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role_id INT NOT NULL,
                first_name VARCHAR(255) NOT NULL,
                last_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE,
                phone_number VARCHAR(20),
                license_id INT,
                district_id INT,
                village_id INT,
                is_active BOOLEAN DEFAULT TRUE,
                last_login TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (role_id) REFERENCES roles(role_id),
                FOREIGN KEY (license_id) REFERENCES licenses(license_id),
                FOREIGN KEY (district_id) REFERENCES districts(district_id),
                FOREIGN KEY (village_id) REFERENCES villages(village_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول العقود/المحررات
        'contracts' => "
            CREATE TABLE IF NOT EXISTS contracts (
                contract_id INT AUTO_INCREMENT PRIMARY KEY,
                contract_number VARCHAR(255) UNIQUE NOT NULL,
                contract_date DATE NOT NULL,
                contract_type_id INT NOT NULL,
                notary_id INT NOT NULL,
                district_id INT NOT NULL,
                village_id INT,
                parties_data JSON,
                contract_content TEXT NOT NULL,
                digital_file_path VARCHAR(255),
                status ENUM('draft', 'submitted', 'reviewed', 'approved', 'rejected') DEFAULT 'draft',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (contract_type_id) REFERENCES contract_types(contract_type_id),
                FOREIGN KEY (notary_id) REFERENCES users(user_id),
                FOREIGN KEY (district_id) REFERENCES districts(district_id),
                FOREIGN KEY (village_id) REFERENCES villages(village_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول تقديمات قيد التوثيق
        'submissions' => "
            CREATE TABLE IF NOT EXISTS submissions (
                submission_id INT AUTO_INCREMENT PRIMARY KEY,
                contract_id INT UNIQUE NOT NULL,
                submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                review_date TIMESTAMP NULL,
                reviewer_id INT,
                status ENUM('pending', 'in_review', 'accepted', 'rejected') DEFAULT 'pending',
                notes TEXT,
                errors_found TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (contract_id) REFERENCES contracts(contract_id) ON DELETE CASCADE,
                FOREIGN KEY (reviewer_id) REFERENCES users(user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول الأداء
        'performance' => "
            CREATE TABLE IF NOT EXISTS performance (
                performance_id INT AUTO_INCREMENT PRIMARY KEY,
                notary_id INT NOT NULL,
                evaluation_date DATE NOT NULL,
                contracts_count INT DEFAULT 0,
                timely_reports_count INT DEFAULT 0,
                complaints_count INT DEFAULT 0,
                accuracy_score DECIMAL(5,2),
                overall_score DECIMAL(5,2),
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (notary_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول الإشعارات
        'notifications' => "
            CREATE TABLE IF NOT EXISTS notifications (
                notification_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                message TEXT NOT NULL,
                notification_type VARCHAR(50),
                is_read BOOLEAN DEFAULT FALSE,
                link VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول الإعدادات
        'settings' => "
            CREATE TABLE IF NOT EXISTS settings (
                setting_id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(255) UNIQUE NOT NULL,
                setting_value TEXT,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول سجلات الدخول
        'log_access' => "
            CREATE TABLE IF NOT EXISTS log_access (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                action VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                details TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول الأسئلة الشائعة
        'faq' => "
            CREATE TABLE IF NOT EXISTS faq (
                faq_id INT AUTO_INCREMENT PRIMARY KEY,
                question TEXT NOT NULL,
                answer TEXT NOT NULL,
                category VARCHAR(100),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول نماذج العقود الثابتة
        'templates' => "
            CREATE TABLE IF NOT EXISTS templates (
                template_id INT AUTO_INCREMENT PRIMARY KEY,
                template_name VARCHAR(255) UNIQUE NOT NULL,
                template_content TEXT NOT NULL,
                contract_type_id INT,
                created_by INT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (contract_type_id) REFERENCES contract_types(contract_type_id),
                FOREIGN KEY (created_by) REFERENCES users(user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // جدول التعميمات
        'announcements' => "
            CREATE TABLE IF NOT EXISTS announcements (
                announcement_id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                published_date DATE NOT NULL,
                published_by INT,
                target_role_id INT,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (published_by) REFERENCES users(user_id),
                FOREIGN KEY (target_role_id) REFERENCES roles(role_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];
    
    try {
        // إنشاء الجداول
        foreach ($tables as $tableName => $sql) {
            $pdo->exec($sql);
        }
        
        // إدراج البيانات الأساسية
        insertInitialData($pdo);
        
        return true;
    } catch (PDOException $e) {
        throw new Exception("خطأ في إنشاء الجداول: " . $e->getMessage());
    }
}

function insertInitialData($pdo) {
    // إدراج الأدوار الأساسية
    $roles = [
        ['Administrator', 'مدير النظام - صلاحيات كاملة'],
        ['Head of Notary Office', 'رئيس قلم التوثيق - إشراف على الأمناء'],
        ['Notary', 'أمين شرعي - تحرير العقود']
    ];
    
    foreach ($roles as $role) {
        $pdo->exec("INSERT IGNORE INTO roles (role_name, description) VALUES ('{$role[0]}', '{$role[1]}')");
    }
    
    // إدراج أنواع العقود الأساسية
    $contractTypes = [
        ['زواج', 'عقد زواج شرعي'],
        ['طلاق', 'وثيقة طلاق'],
        ['بيع', 'عقد بيع عقار أو منقول'],
        ['وصية', 'وصية شرعية'],
        ['وكالة', 'وكالة قانونية'],
        ['إقرار', 'إقرار شخصي'],
        ['شهادة', 'شهادة رسمية']
    ];
    
    foreach ($contractTypes as $type) {
        $pdo->exec("INSERT IGNORE INTO contract_types (type_name, description) VALUES ('{$type[0]}', '{$type[1]}')");
    }
    
    // إدراج المحافظات اليمنية الأساسية
    $provinces = [
        'صنعاء', 'عدن', 'تعز', 'الحديدة', 'إب', 'ذمار', 'حضرموت', 'المحويت',
        'حجة', 'صعدة', 'عمران', 'البيضاء', 'لحج', 'أبين', 'شبوة', 'المهرة',
        'الجوف', 'مأرب', 'الضالع', 'ريمة', 'سقطرى'
    ];
    
    foreach ($provinces as $province) {
        $pdo->exec("INSERT IGNORE INTO provinces (province_name) VALUES ('{$province}')");
    }
    
    // إدراج الأسئلة الشائعة الأساسية
    $faqs = [
        [
            'كيف يمكنني تحرير عقد زواج؟',
            'لتحرير عقد زواج، يجب التأكد من توفر جميع الوثائق المطلوبة وحضور الطرفين أو وكلائهما المعتمدين.',
            'توثيق'
        ],
        [
            'ما هي المستندات المطلوبة لعقد البيع؟',
            'يتطلب عقد البيع صورة من الهوية الشخصية للبائع والمشتري، وثيقة ملكية العقار، وشهود معتمدين.',
            'عقارات'
        ],
        [
            'كيف يمكنني تقديم تقرير دوري؟',
            'يمكن تقديم التقرير الدوري من خلال النظام الإلكتروني في الموعد المحدد شهرياً.',
            'تقارير'
        ]
    ];
    
    foreach ($faqs as $faq) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO faq (question, answer, category) VALUES (?, ?, ?)");
        $stmt->execute($faq);
    }
    
    // إدراج الإعدادات الافتراضية
    $settings = [
        ['system_name', 'نظام إدارة الأمناء الشرعيين', 'اسم النظام'],
        ['theme_color', '#2c3e50', 'لون النظام الأساسي'],
        ['font_family', 'Tajawal', 'خط النظام'],
        ['max_file_size', '10485760', 'الحد الأقصى لحجم الملف (بايت)'],
        ['backup_frequency', 'weekly', 'تكرار النسخ الاحتياطي'],
        ['session_timeout', '3600', 'مهلة انتهاء الجلسة (ثانية)']
    ];
    
    foreach ($settings as $setting) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        $stmt->execute($setting);
    }
}
?>

