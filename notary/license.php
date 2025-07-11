<?php
/**
 * صفحة عرض الترخيص الشخصي للأمين الشرعي
 * Personal License View Page for Notary
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

// حماية الصفحة: تتطلب صلاحية الأمين الشرعي
requireRole("Notary");

// إعدادات الصفحة
$pageTitle = "ترخيصي الشخصي";
$pageDescription = "عرض تفاصيل الترخيص الشخصي للأمين الشرعي.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "ترخيصي الشخصي"]
];

// جلب معرف المستخدم الحالي
$current_user = getCurrentUser();
$notary_id = $current_user["user_id"];

// جلب الترخيص الحالي للأمين
$current_license = $database->selectOne("SELECT * FROM licenses WHERE notary_id = ? ORDER BY issue_date DESC LIMIT 1", [$notary_id]);

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">تفاصيل الترخيص</h5>
            </div>
            <div class="card-body">
                <?php if ($current_license): ?>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>رقم الترخيص:</strong>
                        </div>
                        <div class="col-md-8">
                            <?php echo htmlspecialchars($current_license["license_number"]); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>تاريخ الإصدار:</strong>
                        </div>
                        <div class="col-md-8">
                            <?php echo formatDate($current_license["issue_date"]); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>تاريخ الانتهاء:</strong>
                        </div>
                        <div class="col-md-8">
                            <?php echo formatDate($current_license["expiry_date"]); ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>الحالة:</strong>
                        </div>
                        <div class="col-md-8">
                            <?php if ($current_license["status"] === "active"): ?>
                                <span class="badge bg-success">نشط</span>
                            <?php elseif ($current_license["status"] === "expired"): ?>
                                <span class="badge bg-danger">منتهي</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">ملغى</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>ملاحظات:</strong>
                        </div>
                        <div class="col-md-8">
                            <?php echo htmlspecialchars($current_license["notes"] ?? "لا توجد ملاحظات."); ?>
                        </div>
                    </div>
                    
                    <?php if ($current_license["status"] === "expired"): ?>
                        <div class="alert alert-warning mt-4" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ترخيصك منتهي الصلاحية. يرجى التواصل مع رئيس قلم التوثيق لتجديده.
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        لا يوجد ترخيص مسجل باسمك حالياً. يرجى التواصل مع الإدارة.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين تذييل الصفحة
include "../templates/footer.php";
?>

