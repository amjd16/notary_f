<?php
/**
 * صفحة إعدادات النظام العامة
 * General System Settings Page
 */

// تعريف الوصول للنظام
define("SYSTEM_ACCESS", true);

// تضمين الملفات المطلوبة
require_once "../config/config.php";
require_once "../config/database.php";
require_once "../includes/helpers.php";
require_once "../includes/session.php";
require_once "../includes/auth.php";
require_once "../includes/page_guard.php";

// حماية الصفحة: تتطلب صلاحية مدير النظام
requireRole("Administrator");

// إعدادات الصفحة
$pageTitle = "إعدادات النظام";
$pageDescription = "إدارة الإعدادات العامة للنظام.";
$breadcrumbs = [
    ["title" => "إدارة النظام"],
    ["title" => "إعدادات النظام"]
];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "update_settings":
            $settings = [
                "site_name" => sanitizeInput($_POST["site_name"]),
                "site_description" => sanitizeInput($_POST["site_description"]),
                "admin_email" => sanitizeInput($_POST["admin_email"]),
                "records_per_page" => (int)$_POST["records_per_page"] ?? 10,
                "license_expiry_alert_days" => (int)$_POST["license_expiry_alert_days"] ?? 30,
                "enable_registration" => isset($_POST["enable_registration"]) ? 1 : 0,
                "default_role_id" => (int)$_POST["default_role_id"] ?? 2 // Default to Notary
            ];

            $success = true;
            foreach ($settings as $key => $value) {
                // Check if setting exists, if not, insert it
                $existingSetting = $database->selectOne("SELECT * FROM settings WHERE setting_key = ?", [$key]);
                if ($existingSetting) {
                    $result = $database->update("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
                } else {
                    $result = $database->insert("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value]);
                }
                if (!$result) {
                    $success = false;
                    break;
                }
            }

            if ($success) {
                setFlashMessage("success", "تم تحديث الإعدادات بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء تحديث الإعدادات.");
            }
            break;
    }
    
    redirect("settings.php");
}

// جلب الإعدادات الحالية
$currentSettings = [];
$settingsFromDB = $database->select("SELECT * FROM settings");
foreach ($settingsFromDB as $setting) {
    $currentSettings[$setting["setting_key"]] = $setting["setting_value"];
}

// جلب الأدوار لعرضها في قائمة الدور الافتراضي
$roles = $database->select("SELECT role_id, role_name FROM roles");

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">الإعدادات العامة للنظام</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo generateCsrfField(); ?>
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="form-group mb-3">
                        <label for="site_name" class="form-label">اسم الموقع</label>
                        <input type="text" class="form-control" id="site_name" name="site_name" 
                               value="<?php echo htmlspecialchars($currentSettings["site_name"] ?? "."); ?>" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="site_description" class="form-label">وصف الموقع</label>
                        <textarea class="form-control" id="site_description" name="site_description" rows="3">
                            <?php echo htmlspecialchars($currentSettings["site_description"] ?? "."); ?>
                        </textarea>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="admin_email" class="form-label">بريد المدير الإلكتروني</label>
                        <input type="email" class="form-control" id="admin_email" name="admin_email" 
                               value="<?php echo htmlspecialchars($currentSettings["admin_email"] ?? "."); ?>" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="records_per_page" class="form-label">عدد السجلات لكل صفحة (الجداول)</label>
                        <input type="number" class="form-control" id="records_per_page" name="records_per_page" 
                               value="<?php echo htmlspecialchars($currentSettings["records_per_page"] ?? 10); ?>" min="5" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="license_expiry_alert_days" class="form-label">أيام التنبيه قبل انتهاء الترخيص</label>
                        <input type="number" class="form-control" id="license_expiry_alert_days" name="license_expiry_alert_days" 
                               value="<?php echo htmlspecialchars($currentSettings["license_expiry_alert_days"] ?? 30); ?>" min="0" required>
                    </div>

                    <div class="form-group mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="enable_registration" name="enable_registration" 
                               <?php echo (isset($currentSettings["enable_registration"]) && $currentSettings["enable_registration"] == 1) ? "checked" : ""; ?>>
                        <label class="form-check-label" for="enable_registration">تمكين تسجيل المستخدمين الجدد</label>
                    </div>

                    <div class="form-group mb-3">
                        <label for="default_role_id" class="form-label">الدور الافتراضي للمستخدمين الجدد</label>
                        <select class="form-control" id="default_role_id" name="default_role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role["role_id"]; ?>"
                                    <?php echo (isset($currentSettings["default_role_id"]) && $currentSettings["default_role_id"] == $role["role_id"]) ? "selected" : ""; ?>>
                                    <?php echo htmlspecialchars($role["role_name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">حفظ الإعدادات</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين تذييل الصفحة
include "../templates/footer.php";
?>

