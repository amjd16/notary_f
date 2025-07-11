<?php
/**
 * صفحة إعدادات الملف الشخصي للأمين الشرعي
 * Personal Profile Settings Page for Notary
 */

// تعريف الوصول للنظام
define("SYSTEM_ACCESS", true);

// تضمين الملفات المطلوبة
require_once "../config/config.php";
require_once "../config/database.php";
require_once "../includes/helpers.php";
require_once "../includes/session.php";
require_once "../includes/auth.php";
require_once "../includes/file_upload.php";
require_once "../includes/page_guard.php";

// حماية الصفحة: تتطلب صلاحية الأمين الشرعي
requireRole("Notary");

// إعدادات الصفحة
$pageTitle = "إعدادات الملف الشخصي";
$pageDescription = "إدارة معلومات الملف الشخصي وتغيير كلمة المرور.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "إعدادات الملف الشخصي"]
];

// جلب معرف المستخدم الحالي
$current_user = getCurrentUser();
$notary_id = $current_user["user_id"];

// جلب بيانات الأمين من قاعدة البيانات
$notary_data = $database->selectOne("SELECT * FROM users WHERE user_id = ?", [$notary_id]);

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "update_profile":
            $full_name = sanitizeInput($_POST["full_name"]);
            $email = sanitizeInput($_POST["email"]);
            $phone_number = sanitizeInput($_POST["phone_number"]);
            $address = sanitizeInput($_POST["address"]);
            
            $update_fields = [
                "full_name" => $full_name,
                "email" => $email,
                "phone_number" => $phone_number,
                "address" => $address
            ];

            // معالجة رفع الصورة الشخصية
            if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] === UPLOAD_ERR_OK) {
                $fileUpload = new FileUpload();
                $upload_result = $fileUpload->uploadFile($_FILES["profile_picture"], "profile_pictures");
                
                if ($upload_result["success"]) {
                    $update_fields["profile_picture"] = $upload_result["file_path"];
                    // حذف الصورة القديمة إذا وجدت
                    if (!empty($notary_data["profile_picture"]) && file_exists($notary_data["profile_picture"])) {
                        unlink($notary_data["profile_picture"]);
                    }
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء رفع الصورة: " . $upload_result["message"]);
                    redirect("profile.php");
                }
            }

            $result = $database->update("UPDATE users SET " . implode(" = ?, ", array_keys($update_fields)) . " = ? WHERE user_id = ?", 
                                      array_values($update_fields), $notary_id);
            
            if ($result) {
                setFlashMessage("success", "تم تحديث الملف الشخصي بنجاح.");
                // تحديث بيانات المستخدم في الجلسة
                $_SESSION["user"]["full_name"] = $full_name;
                $_SESSION["user"]["email"] = $email;
                $_SESSION["user"]["phone_number"] = $phone_number;
                $_SESSION["user"]["address"] = $address;
                if (isset($update_fields["profile_picture"])) {
                    $_SESSION["user"]["profile_picture"] = $update_fields["profile_picture"];
                }
            } else {
                setFlashMessage("error", "حدث خطأ أثناء تحديث الملف الشخصي.");
            }
            redirect("profile.php");
            break;
            
        case "change_password":
            $current_password = sanitizeInput($_POST["current_password"]);
            $new_password = sanitizeInput($_POST["new_password"]);
            $confirm_password = sanitizeInput($_POST["confirm_password"]);
            
            if (!password_verify($current_password, $notary_data["password"])) {
                setFlashMessage("error", "كلمة المرور الحالية غير صحيحة.");
            } elseif ($new_password !== $confirm_password) {
                setFlashMessage("error", "كلمة المرور الجديدة وتأكيدها غير متطابقين.");
            } elseif (strlen($new_password) < 6) {
                setFlashMessage("error", "يجب أن تكون كلمة المرور الجديدة 6 أحرف على الأقل.");
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $result = $database->update("UPDATE users SET password = ? WHERE user_id = ?", [$hashed_password, $notary_id]);
                
                if ($result) {
                    setFlashMessage("success", "تم تغيير كلمة المرور بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء تغيير كلمة المرور.");
                }
            }
            redirect("profile.php");
            break;
    }
}

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">تعديل الملف الشخصي</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo generateCsrfField(); ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="text-center mb-4">
                        <img src="<?php echo htmlspecialchars($notary_data["profile_picture"] ?? ". . /assets/images/default_avatar.png"); ?>" 
                             alt="صورة شخصية" class="img-thumbnail rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                        <div class="mt-2">
                            <label for="profile_picture" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-camera me-2"></i>تغيير الصورة
                            </label>
                            <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="d-none">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">الاسم الكامل</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               value="<?php echo htmlspecialchars($notary_data["full_name"]); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">البريد الإلكتروني</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($notary_data["email"]); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone_number" class="form-label">رقم الهاتف</label>
                        <input type="text" class="form-control" id="phone_number" name="phone_number" 
                               value="<?php echo htmlspecialchars($notary_data["phone_number"] ?? ""); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">العنوان</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($notary_data["address"] ?? ""); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">تغيير كلمة المرور</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo generateCsrfField(); ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">كلمة المرور الحالية</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">كلمة المرور الجديدة</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">تأكيد كلمة المرور الجديدة</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">تغيير كلمة المرور</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين تذييل الصفحة
include "../templates/footer.php";
?>

