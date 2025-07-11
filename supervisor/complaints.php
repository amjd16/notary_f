<?php
/**
 * صفحة إدارة الشكاوى والمتابعة
 * Complaints and Follow-up Management Page for Supervisor
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
$pageTitle = "إدارة الشكاوى والمتابعة";
$pageDescription = "إدارة الشكاوى والمتابعة المتعلقة بالأمناء في المديرية.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "إدارة الشكاوى"]
];

// جلب معرف المديرية للمستخدم الحالي
$user_district_id = getCurrentUser()["district_id"];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "add_complaint":
            $notary_id = (int)$_POST["notary_id"];
            $complaint_text = sanitizeInput($_POST["complaint_text"]);
            $status = "open";
            
            // التأكد من أن الأمين تابع لنفس المديرية
            $notary_data = $database->selectOne("SELECT district_id FROM users WHERE user_id = ?", [$notary_id]);
            if ($notary_data && $notary_data["district_id"] == $user_district_id) {
                $result = $database->insert("INSERT INTO complaints (notary_id, supervisor_id, complaint_text, status) VALUES (?, ?, ?, ?)", 
                                          [$notary_id, getCurrentUser()["user_id"], $complaint_text, $status]);
                if ($result) {
                    setFlashMessage("success", "تم إضافة الشكوى بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء إضافة الشكوى.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لإضافة شكوى لهذا الأمين.");
            }
            break;
            
        case "update_complaint_status":
            $complaint_id = (int)$_POST["complaint_id"];
            $new_status = sanitizeInput($_POST["new_status"]);
            
            // التأكد من أن الشكوى تابعة للمديرية
            $complaint_data = $database->selectOne("SELECT c.complaint_id FROM complaints c JOIN users u ON c.notary_id = u.user_id WHERE c.complaint_id = ? AND u.district_id = ?", [$complaint_id, $user_district_id]);
            if ($complaint_data) {
                $result = $database->update("UPDATE complaints SET status = ? WHERE complaint_id = ?", [$new_status, $complaint_id]);
                if ($result) {
                    setFlashMessage("success", "تم تحديث حالة الشكوى بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء تحديث حالة الشكوى.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لتعديل هذه الشكوى.");
            }
            break;
    }
    
    redirect("complaints.php");
}

// جلب الشكاوى الخاصة بالأمناء في المديرية الحالية
$complaints = $database->select("SELECT c.*, u.full_name as notary_name, u.username as notary_username 
                                 FROM complaints c 
                                 JOIN users u ON c.notary_id = u.user_id 
                                 WHERE u.district_id = ? 
                                 ORDER BY c.created_at DESC", [$user_district_id]);

// جلب الأمناء في المديرية الحالية (لإضافة شكوى)
$notaries_in_district = $database->select("SELECT user_id, full_name FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = ?) AND district_id = ?", ["Notary", $user_district_id]);

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة الشكاوى</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addComplaintModal">
                    <i class="fas fa-plus me-2"></i>إضافة شكوى جديدة
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="complaintsTable">
                        <thead>
                            <tr>
                                <th>رقم الشكوى</th>
                                <th>الأمين</th>
                                <th>نص الشكوى</th>
                                <th>تاريخ الإنشاء</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $complaint): ?>
                            <tr>
                                <td><?php echo $complaint["complaint_id"]; ?></td>
                                <td><?php echo htmlspecialchars($complaint["notary_name"]); ?> (<?php echo htmlspecialchars($complaint["notary_username"]); ?>)</td>
                                <td><?php echo htmlspecialchars(substr($complaint["complaint_text"], 0, 100)); ?>...</td>
                                <td><?php echo formatDate($complaint["created_at"]); ?></td>
                                <td>
                                    <?php if ($complaint["status"] === "open"): ?>
                                        <span class="badge bg-warning">مفتوحة</span>
                                    <?php elseif ($complaint["status"] === "in_progress"): ?>
                                        <span class="badge bg-info">قيد المتابعة</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">مغلقة</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="viewComplaintDetails(<?php echo $complaint["complaint_id"]; ?>)">
                                            <i class="fas fa-eye"></i> عرض
                                        </button>
                                        <?php if ($complaint["status"] !== "closed"): ?>
                                            <button type="button" class="btn btn-outline-success" 
                                                    onclick="updateComplaintStatus(<?php echo $complaint["complaint_id"]; ?>, 
                                                    (this.dataset.newStatus = (this.dataset.newStatus === \"in_progress\") ? \"closed\" : \"in_progress\"))">
                                                <i class="fas fa-check"></i> 
                                                <?php echo ($complaint["status"] === "open") ? "بدء المتابعة" : "إغلاق"; ?>
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

<!-- Modal for Add Complaint -->
<div class="modal fade" id="addComplaintModal" tabindex="-1" aria-labelledby="addComplaintModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addComplaintModalLabel">إضافة شكوى جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="add_complaint">
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
                        <label for="complaint_text" class="form-label">نص الشكوى</label>
                        <textarea class="form-control" id="complaint_text" name="complaint_text" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة الشكوى</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for Complaint Details -->
<div class="modal fade" id="complaintDetailsModal" tabindex="-1" aria-labelledby="complaintDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="complaintDetailsModalLabel">تفاصيل الشكوى</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body" id="complaintDetailsContent">
                <!-- Content will be loaded here via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<?php
// JavaScript مخصص للصفحة
$pageJS = 

// تهيئة جدول الشكاوى
$(document).ready(function() {
    $("#complaintsTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 3, "desc" ]],
        "pageLength": 25
    });
});

// عرض تفاصيل الشكوى
function viewComplaintDetails(complaintId) {
    fetch("../api/get_complaint_details.php?id=" + complaintId)
        .then(response => response.text())
        .then(html => {
            document.getElementById("complaintDetailsContent").innerHTML = html;
            var modal = new bootstrap.Modal(document.getElementById("complaintDetailsModal"));
            modal.show();
        })
        .catch(error => {
            console.error("Error:", error);
            alert("حدث خطأ أثناء جلب تفاصيل الشكوى.");
        });
}

// تحديث حالة الشكوى
function updateComplaintStatus(complaintId, newStatus) {
    if (confirm("هل أنت متأكد من تغيير حالة هذه الشكوى؟")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            <input type="hidden" name="_csrf_token" value="<?php echo $_SESSION[\"csrf_token\"]; ?>">
            <input type="hidden" name="action" value="update_complaint_status">
            <input type="hidden" name="complaint_id" value="${complaintId}">
            <input type="hidden" name="new_status" value="${newStatus}">
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

