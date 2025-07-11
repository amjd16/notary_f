<?php
/**
 * صفحة مراجعة وتدقيق المعاملات
 * Transaction Review and Audit Page for Supervisor
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
$pageTitle = "مراجعة وتدقيق المعاملات";
$pageDescription = "مراجعة وتدقيق المعاملات التي تمت في المديرية.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "مراجعة المعاملات"]
];

// جلب معرف المديرية للمستخدم الحالي
$user_district_id = getCurrentUser()["district_id"];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "update_transaction_status":
            $transaction_id = (int)$_POST["transaction_id"];
            $new_status = sanitizeInput($_POST["new_status"]);
            
            // التأكد من أن المعاملة تابعة للمديرية قبل التعديل
            $transaction_data = $database->selectOne("SELECT t.*, u.district_id FROM transactions t JOIN users u ON t.notary_id = u.user_id WHERE t.transaction_id = ?", [$transaction_id]);
            
            if ($transaction_data && $transaction_data["district_id"] == $user_district_id) {
                $result = $database->update("UPDATE transactions SET status = ? WHERE transaction_id = ?", [$new_status, $transaction_id]);
                if ($result) {
                    setFlashMessage("success", "تم تحديث حالة المعاملة بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء تحديث حالة المعاملة.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لتعديل هذه المعاملة.");
            }
            break;
    }
    
    redirect("transactions.php");
}

// جلب المعاملات التابعة للمديرية الحالية
$transactions = $database->select("SELECT t.*, u.full_name as notary_name, u.username as notary_username 
                                   FROM transactions t 
                                   JOIN users u ON t.notary_id = u.user_id 
                                   WHERE u.district_id = ? 
                                   ORDER BY t.transaction_date DESC", [$user_district_id]);

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">قائمة المعاملات</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="transactionsTable">
                        <thead>
                            <tr>
                                <th>رقم المعاملة</th>
                                <th>الأمين</th>
                                <th>نوع المعاملة</th>
                                <th>تاريخ المعاملة</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction["transaction_number"]); ?></td>
                                <td><?php echo htmlspecialchars($transaction["notary_name"]); ?> (<?php echo htmlspecialchars($transaction["notary_username"]); ?>)</td>
                                <td><?php echo htmlspecialchars($transaction["transaction_type"]); ?></td>
                                <td><?php echo formatDate($transaction["transaction_date"]); ?></td>
                                <td>
                                    <?php if ($transaction["status"] === "completed"): ?>
                                        <span class="badge bg-success">مكتملة</span>
                                    <?php elseif ($transaction["status"] === "pending"): ?>
                                        <span class="badge bg-warning">معلقة</span>
                                    <?php elseif ($transaction["status"] === "rejected"): ?>
                                        <span class="badge bg-danger">مرفوضة</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">غير معروفة</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="viewTransactionDetails(<?php echo $transaction["transaction_id"]; ?>)">
                                            <i class="fas fa-eye"></i> عرض
                                        </button>
                                        <?php if ($transaction["status"] === "pending"): ?>
                                            <button type="button" class="btn btn-outline-success" 
                                                    onclick="updateTransactionStatus(<?php echo $transaction["transaction_id"]; ?>, 'completed')">
                                                <i class="fas fa-check"></i> قبول
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="updateTransactionStatus(<?php echo $transaction["transaction_id"]; ?>, 'rejected')">
                                                <i class="fas fa-times"></i> رفض
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

<!-- Modal for Transaction Details -->
<div class="modal fade" id="transactionDetailsModal" tabindex="-1" aria-labelledby="transactionDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transactionDetailsModalLabel">تفاصيل المعاملة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body" id="transactionDetailsContent">
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
'$(document).ready(function() {
    $("#transactionsTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 3, "desc" ]],
        "pageLength": 25
    });
});

function viewTransactionDetails(transactionId) {
    fetch("../api/get_transaction_details.php?id=" + transactionId)
        .then(response => response.text())
        .then(html => {
            document.getElementById("transactionDetailsContent").innerHTML = html;
            var modal = new bootstrap.Modal(document.getElementById("transactionDetailsModal"));
            modal.show();
        })
        .catch(error => {
            console.error("Error:", error);
            alert("حدث خطأ أثناء جلب تفاصيل المعاملة.");
        });
}

function updateTransactionStatus(transactionId, newStatus) {
    if (confirm("هل أنت متأكد من تغيير حالة هذه المعاملة إلى " + (newStatus === "completed" ? "مكتملة" : "مرفوضة") + "؟")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            ' . generateCsrfField() . '
            <input type="hidden" name="action" value="update_transaction_status">
            <input type="hidden" name="transaction_id" value="${transactionId}">
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

