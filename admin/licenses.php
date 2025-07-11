<?php
/**
 * صفحة إدارة التراخيص
 * Licenses Management Page
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
require_once "../includes/user_manager.php";
require_once "../includes/page_guard.php";

// حماية الصفحة: تتطلب صلاحية مدير النظام
requireRole("Administrator");

// إعدادات الصفحة
$pageTitle = "إدارة التراخيص";
$pageDescription = "إصدار، تجديد، وإلغاء تراخيص الأمناء الشرعيين.";
$breadcrumbs = [
    ["title" => "إدارة النظام"],
    ["title" => "إدارة التراخيص"]
];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "issue_license":
            $result = $licenseManager->issueLicense($_POST);
            if ($result) {
                setFlashMessage("success", "تم إصدار الترخيص بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء إصدار الترخيص.");
            }
            break;
            
        case "renew_license":
            $result = $licenseManager->renewLicense($_POST["license_id"], $_POST["new_expiry_date"]);
            if ($result) {
                setFlashMessage("success", "تم تجديد الترخيص بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء تجديد الترخيص.");
            }
            break;
            
        case "revoke_license":
            $result = $licenseManager->revokeLicense($_POST["license_id"]);
            if ($result) {
                setFlashMessage("success", "تم إلغاء الترخيص بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء إلغاء الترخيص.");
            }
            break;
    }
    
    redirect("licenses.php");
}

// جلب قائمة التراخيص
$licenses = $licenseManager->getAllLicenses();
$notaries = $userManager->getUsersByRole("Notary"); // جلب الأمناء فقط

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة التراخيص</h5>
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
                                <th>الأمين الشرعي</th>
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
                                <td><?php echo htmlspecialchars($license["notary_name"]); ?></td>
                                <td><?php echo formatDate($license["issue_date"]); ?></td>
                                <td><?php echo formatDate($license["expiry_date"]); ?></td>
                                <td>
                                    <?php if ($license["status"] === "active"): ?>
                                        <span class="badge bg-success">نشط</span>
                                    <?php elseif ($license["status"] === "expired"): ?>
                                        <span class="badge bg-danger">منتهي</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">ملغى</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="renewLicense(<?php echo $license["license_id"]; ?>, 
                                                '<?php echo $license["expiry_date"]; ?>')">
                                            <i class="fas fa-sync-alt"></i> تجديد
                                        </button>
                                        <?php if ($license["status"] === "active"): ?>
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

<!-- نافذة إصدار ترخيص جديد -->
<div class="modal fade" id="issueLicenseModal" tabindex="-1" aria-labelledby="issueLicenseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="issueLicenseModalLabel">إصدار ترخيص جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="issueLicenseForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="issue_license">
                
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="notary_id" class="form-label">الأمين الشرعي</label>
                        <select class="form-control" id="notary_id" name="notary_id" required>
                            <option value="">اختر الأمين</option>
                            <?php foreach ($notaries as $notary): ?>
                                <option value="<?php echo $notary["user_id"]; ?>">
                                    <?php echo htmlspecialchars($notary["full_name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label for="license_number" class="form-label">رقم الترخيص</label>
                        <input type="text" class="form-control" id="license_number" name="license_number" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="issue_date" class="form-label">تاريخ الإصدار</label>
                        <input type="date" class="form-control" id="issue_date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group mb-3">
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

<!-- نافذة تجديد الترخيص -->
<div class="modal fade" id="renewLicenseModal" tabindex="-1" aria-labelledby="renewLicenseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renewLicenseModalLabel">تجديد الترخيص</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="renewLicenseForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="renew_license">
                <input type="hidden" name="license_id" id="renew_license_id">
                
                <div class="modal-body">
                    <p>الترخيص الحالي ينتهي في: <strong id="current_expiry_date"></strong></p>
                    <div class="form-group mb-3">
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
'// تهيئة جدول التراخيص
$(document).ready(function() {
    $("#licensesTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 2, "desc" ]],
        "pageLength": 25
    });
});

// دالة تجديد الترخيص
function renewLicense(licenseId, currentExpiryDate) {
    document.getElementById("renew_license_id").value = licenseId;
    document.getElementById("current_expiry_date").textContent = currentExpiryDate;
    
    // تعيين تاريخ الانتهاء الجديد ليكون بعد سنة من التاريخ الحالي أو تاريخ الانتهاء الحالي
    const today = new Date();
    const currentExp = new Date(currentExpiryDate);
    const newDate = new Date(Math.max(today.getTime(), currentExp.getTime()));
    newDate.setFullYear(newDate.getFullYear() + 1);
    
    const year = newDate.getFullYear();
    const month = String(newDate.getMonth() + 1).padStart(2, "0");
    const day = String(newDate.getDate()).padStart(2, "0");
    document.getElementById("new_expiry_date").value = `${year}-${month}-${day}`;

    const modal = new bootstrap.Modal(document.getElementById("renewLicenseModal"));
    modal.show();
}

// دالة إلغاء الترخيص
function revokeLicense(licenseId) {
    if (confirm("هل أنت متأكد من إلغاء هذا الترخيص؟ هذا الإجراء لا يمكن التراجع عنه.")) {
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

// التحقق من تواريخ الإصدار والانتهاء عند إصدار ترخيص جديد
document.getElementById("issueLicenseForm").addEventListener("submit", function(e) {
    const issueDate = new Date(document.getElementById("issue_date").value);
    const expiryDate = new Date(document.getElementById("expiry_date").value);
    
    if (expiryDate <= issueDate) {
        e.preventDefault();
        alert("تاريخ الانتهاء يجب أن يكون بعد تاريخ الإصدار.");
        return false;
    }
});

// التحقق من تاريخ الانتهاء الجديد عند تجديد الترخيص
document.getElementById("renewLicenseForm").addEventListener("submit", function(e) {
    const currentExpiryDateText = document.getElementById("current_expiry_date").textContent;
    const currentExpiryDate = new Date(currentExpiryDateText);
    const newExpiryDate = new Date(document.getElementById("new_expiry_date").value);
    
    if (newExpiryDate <= currentExpiryDate) {
        e.preventDefault();
        alert("تاريخ الانتهاء الجديد يجب أن يكون بعد تاريخ الانتهاء الحالي.");
        return false;
    }
});
';

// ملفات JavaScript إضافية (لـ DataTables)
$additionalJS = [
    "https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js",
    "https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"
];

// ملفات CSS إضافية (لـ DataTables)
$additionalCSS = [
    "https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css"
];

// تضمين تذييل الصفحة
include "../templates/footer.php";
?>

