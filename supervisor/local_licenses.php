<?php
/**
 * صفحة إدارة التراخيص المحلية
 * Local Licenses Management Page for Supervisor
 */

// تعريف الوصول للنظام
define("SYSTEM_ACCESS", true);

// تضمين الملفات المطلوبة
require_once "../config/config.php";
require_once "../config/database.php";
require_once "../includes/helpers.php";
require_once "../includes/session.php";
require_once "../includes/auth.php";
require_once "../includes/license_manager.php";
require_once "../includes/page_guard.php";

// حماية الصفحة: تتطلب صلاحية رئيس قلم التوثيق
requireRole("Supervisor");

// إعدادات الصفحة
$pageTitle = "إدارة التراخيص المحلية";
$pageDescription = "إدارة تراخيص الأمناء الشرعيين في المديرية.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "إدارة التراخيص المحلية"]
];

// جلب معرف المديرية للمستخدم الحالي
$user_district_id = getCurrentUser()["district_id"];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "issue_license":
            $notary_id = (int)$_POST["notary_id"];
            $license_number = sanitizeInput($_POST["license_number"]);
            $issue_date = sanitizeInput($_POST["issue_date"]);
            $expiry_date = sanitizeInput($_POST["expiry_date"]);
            
            // التأكد من أن الأمين تابع لنفس المديرية
            $notary_data = $database->selectOne("SELECT district_id FROM users WHERE user_id = ?", [$notary_id]);
            if ($notary_data && $notary_data["district_id"] == $user_district_id) {
                $result = $licenseManager->issueLicense($notary_id, $license_number, $issue_date, $expiry_date);
                if ($result) {
                    setFlashMessage("success", "تم إصدار الترخيص بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء إصدار الترخيص.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لإصدار ترخيص لهذا الأمين.");
            }
            break;
            
        case "renew_license":
            $license_id = (int)$_POST["license_id"];
            $new_expiry_date = sanitizeInput($_POST["new_expiry_date"]);
            
            // التأكد من أن الترخيص تابع لأمين في نفس المديرية
            $license_data = $database->selectOne("SELECT l.license_id FROM licenses l JOIN users u ON l.notary_id = u.user_id WHERE l.license_id = ? AND u.district_id = ?", [$license_id, $user_district_id]);
            if ($license_data) {
                $result = $licenseManager->renewLicense($license_id, $new_expiry_date);
                if ($result) {
                    setFlashMessage("success", "تم تجديد الترخيص بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء تجديد الترخيص.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لتجديد هذا الترخيص.");
            }
            break;
            
        case "revoke_license":
            $license_id = (int)$_POST["license_id"];
            
            // التأكد من أن الترخيص تابع لأمين في نفس المديرية
            $license_data = $database->selectOne("SELECT l.license_id FROM licenses l JOIN users u ON l.notary_id = u.user_id WHERE l.license_id = ? AND u.district_id = ?", [$license_id, $user_district_id]);
            if ($license_data) {
                $result = $licenseManager->revokeLicense($license_id);
                if ($result) {
                    setFlashMessage("success", "تم إلغاء الترخيص بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء إلغاء الترخيص.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لإلغاء هذا الترخيص.");
            }
            break;
    }
    
    redirect("local_licenses.php");
}

// جلب التراخيص الخاصة بالأمناء في المديرية الحالية
$licenses = $database->select("SELECT l.*, u.full_name as notary_name, u.username as notary_username 
                               FROM licenses l 
                               JOIN users u ON l.notary_id = u.user_id 
                               WHERE u.district_id = ? 
                               ORDER BY l.issue_date DESC", [$user_district_id]);

// جلب الأمناء في المديرية الحالية (لإصدار التراخيص)
$notaries_in_district = $database->select("SELECT user_id, full_name FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = ?) AND district_id = ?", ["Notary", $user_district_id]);

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة التراخيص المحلية</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#issueLicenseModal">
                    <i class="fas fa-plus me-2"></i>إصدار ترخيص جديد
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="licensesTable">
                        <thead>
                            <tr>
                                <th>رقم الترخيص</th>
                                <th>الأمين</th>
                                <th>تاريخ الإصدار</th>
                                <th>تاريخ الانتهاء</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($licenses as $license): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($license["license_number"]); ?></td>
                                <td><?php echo htmlspecialchars($license["notary_name"]); ?> (<?php echo htmlspecialchars($license["notary_username"]); ?>)</td>
                                <td><?php echo formatDate($license["issue_date"]); ?></td>
                                <td><?php echo formatDate($license["expiry_date"]); ?></td>
                                <td>
                                    <?php if ($license["status"] === "active"): ?>
                                        <span class="badge bg-success">نشط</span>
                                    <?php elseif ($license["status"] === "expired"): ?>
                                        <span class="badge bg-danger">منتهي</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">ملغى</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($license["status"] === "active"): ?>
                                            <button type="button" class="btn btn-outline-success" 
                                                    onclick="renewLicense(<?php echo $license["license_id"]; ?>, 
                                                    '<?php echo $license["expiry_date"]; ?>')">
                                                <i class="fas fa-sync-alt"></i> تجديد
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="revokeLicense(<?php echo $license["license_id"]; ?>)">
                                                <i class="fas fa-ban"></i> إلغاء
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Issue License -->
<div class="modal fade" id="issueLicenseModal" tabindex="-1" aria-labelledby="issueLicenseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="issueLicenseModalLabel">إصدار ترخيص جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="issue_license">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="notary_id" class="form-label">الأمين</label>
                        <select class="form-select" id="notary_id" name="notary_id" required>
                            <option value="">اختر أمين</option>
                            <?php foreach ($notaries_in_district as $notary): ?>
                                <option value="<?php echo $notary["user_id"]; ?>"><?php echo htmlspecialchars($notary["full_name"]); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="license_number" class="form-label">رقم الترخيص</label>
                        <input type="text" class="form-control" id="license_number" name="license_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="issue_date" class="form-label">تاريخ الإصدار</label>
                        <input type="date" class="form-control" id="issue_date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="expiry_date" class="form-label">تاريخ الانتهاء</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إصدار الترخيص</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for Renew License -->
<div class="modal fade" id="renewLicenseModal" tabindex="-1" aria-labelledby="renewLicenseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renewLicenseModalLabel">تجديد الترخيص</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="renew_license">
                <input type="hidden" name="license_id" id="renew_license_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new_expiry_date" class="form-label">تاريخ الانتهاء الجديد</label>
                        <input type="date" class="form-control" id="new_expiry_date" name="new_expiry_date" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تجديد الترخيص</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// JavaScript مخصص للصفحة
$pageJS = 
'$(document).ready(function() {
    $("#licensesTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 2, "desc" ]],
        "pageLength": 25
    });
});

function renewLicense(licenseId, currentExpiryDate) {
    $("#renew_license_id").val(licenseId);
    $("#new_expiry_date").val(currentExpiryDate);
    var renewModal = new bootstrap.Modal(document.getElementById("renewLicenseModal"));
    renewModal.show();
}

function revokeLicense(licenseId) {
    if (confirm("هل أنت متأكد من إلغاء هذا الترخيص؟")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            ' . generateCsrfField() . '
            <input type="hidden" name="action" value="revoke_license">
            <input type="hidden" name="license_id" value="${licenseId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
';

// ملفات JavaScript إضافية
$additionalJS = [
    "https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js",
    "https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"
];

// ملفات CSS إضافية
$additionalCSS = [
    "https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css"
];

// تضمين تذييل الصفحة
include "../templates/footer.php";
?>

