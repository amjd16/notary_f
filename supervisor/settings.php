<?php
/**
 * صفحة إعدادات المديرية
 * District Settings Page for Supervisor
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

// حماية الصفحة: تتطلب صلاحية رئيس قلم التوثيق
requireRole("Supervisor");

// إعدادات الصفحة
$pageTitle = "إعدادات المديرية";
$pageDescription = "إدارة الإعدادات الخاصة بالمديرية.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "إعدادات المديرية"]
];

// جلب معرف المديرية للمستخدم الحالي
$user_district_id = getCurrentUser()["district_id"];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "update_district_settings":
            $district_name = sanitizeInput($_POST["district_name"]);
            $contact_email = sanitizeInput($_POST["contact_email"]);
            $contact_phone = sanitizeInput($_POST["contact_phone"]);
            
            $result = $database->update("UPDATE districts SET district_name = ?, contact_email = ?, contact_phone = ? WHERE district_id = ?", 
                                      [$district_name, $contact_email, $contact_phone, $user_district_id]);
            
            if ($result) {
                setFlashMessage("success", "تم تحديث إعدادات المديرية بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء تحديث إعدادات المديرية.");
            }
            break;
    }
    
    redirect("settings.php");
}

// جلب إعدادات المديرية الحالية
$district_settings = $database->selectOne("SELECT * FROM districts WHERE district_id = ?", [$user_district_id]);

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">إعدادات المديرية</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo generateCsrfField(); ?>
                    <input type="hidden" name="action" value="update_district_settings">
                    
                    <div class="form-group mb-3">
                        <label for="district_name" class="form-label">اسم المديرية</label>
                        <input type="text" class="form-control" id="district_name" name="district_name" 
                               value="<?php echo htmlspecialchars($district_settings["district_name"] ?? "."); ?>" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="contact_email" class="form-label">البريد الإلكتروني للتواصل</label>
                        <input type="email" class="form-control" id="contact_email" name="contact_email" 
                               value="<?php echo htmlspecialchars($district_settings["contact_email"] ?? "."); ?>">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="contact_phone" class="form-label">رقم الهاتف للتواصل</label>
                        <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                               value="<?php echo htmlspecialchars($district_settings["contact_phone"] ?? "."); ?>">
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

