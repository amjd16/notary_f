<?php
/**
 * صفحة إدارة المعاملات للأمين الشرعي
 * Transactions Management Page for Notary
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
$pageTitle = "إدارة المعاملات";
$pageDescription = "إدارة المعاملات الخاصة بالأمين الشرعي.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "إدارة المعاملات"]
];

// جلب معرف المستخدم الحالي
$current_user = getCurrentUser();
$notary_id = $current_user["user_id"];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "add_transaction":
            $transaction_number = sanitizeInput($_POST["transaction_number"]);
            $transaction_type = sanitizeInput($_POST["transaction_type"]);
            $client_name = sanitizeInput($_POST["client_name"]);
            $client_id = sanitizeInput($_POST["client_id"]);
            $transaction_details = sanitizeInput($_POST["transaction_details"]);
            $transaction_date = sanitizeInput($_POST["transaction_date"]);
            
            $result = $database->insert("INSERT INTO transactions (notary_id, transaction_number, transaction_type, client_name, client_id, transaction_details, transaction_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')", 
                                      [$notary_id, $transaction_number, $transaction_type, $client_name, $client_id, $transaction_details, $transaction_date]);
            
            if ($result) {
                setFlashMessage("success", "تم إضافة المعاملة بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء إضافة المعاملة.");
            }
            break;
            
        case "edit_transaction":
            $transaction_id = (int)$_POST["transaction_id"];
            $transaction_number = sanitizeInput($_POST["transaction_number"]);
            $transaction_type = sanitizeInput($_POST["transaction_type"]);
            $client_name = sanitizeInput($_POST["client_name"]);
            $client_id = sanitizeInput($_POST["client_id"]);
            $transaction_details = sanitizeInput($_POST["transaction_details"]);
            $transaction_date = sanitizeInput($_POST["transaction_date"]);
            
            // التأكد من أن المعاملة تابعة للأمين الحالي
            $transaction_data = $database->selectOne("SELECT notary_id FROM transactions WHERE transaction_id = ?", [$transaction_id]);
            if ($transaction_data && $transaction_data["notary_id"] == $notary_id) {
                $result = $database->update("UPDATE transactions SET transaction_number = ?, transaction_type = ?, client_name = ?, client_id = ?, transaction_details = ?, transaction_date = ? WHERE transaction_id = ?", 
                                          [$transaction_number, $transaction_type, $client_name, $client_id, $transaction_details, $transaction_date, $transaction_id]);
                
                if ($result) {
                    setFlashMessage("success", "تم تحديث المعاملة بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء تحديث المعاملة.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لتعديل هذه المعاملة.");
            }
            break;
            
        case "delete_transaction":
            $transaction_id = (int)$_POST["transaction_id"];
            
            // التأكد من أن المعاملة تابعة للأمين الحالي ولم يتم اعتمادها بعد
            $transaction_data = $database->selectOne("SELECT notary_id, status FROM transactions WHERE transaction_id = ?", [$transaction_id]);
            if ($transaction_data && $transaction_data["notary_id"] == $notary_id && $transaction_data["status"] === "pending") {
                $result = $database->delete("DELETE FROM transactions WHERE transaction_id = ?", [$transaction_id]);
                
                if ($result) {
                    setFlashMessage("success", "تم حذف المعاملة بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء حذف المعاملة.");
                }
            } else {
                setFlashMessage("error", "لا يمكن حذف هذه المعاملة.");
            }
            break;
    }
    
    redirect("transactions.php");
}

// جلب المعاملات الخاصة بالأمين الحالي
$transactions = $database->select("SELECT * FROM transactions WHERE notary_id = ? ORDER BY transaction_date DESC", [$notary_id]);

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة المعاملات</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                    <i class="fas fa-plus me-2"></i>إضافة معاملة جديدة
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="transactionsTable">
                        <thead>
                            <tr>
                                <th>رقم المعاملة</th>
                                <th>النوع</th>
                                <th>اسم العميل</th>
                                <th>رقم الهوية</th>
                                <th>التاريخ</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction["transaction_number"]); ?></td>
                                <td><?php echo htmlspecialchars($transaction["transaction_type"]); ?></td>
                                <td><?php echo htmlspecialchars($transaction["client_name"]); ?></td>
                                <td><?php echo htmlspecialchars($transaction["client_id"]); ?></td>
                                <td><?php echo formatDate($transaction["transaction_date"]); ?></td>
                                <td>
                                    <?php if ($transaction["status"] === "completed"): ?>
                                        <span class="badge bg-success">مكتملة</span>
                                    <?php elseif ($transaction["status"] === "pending"): ?>
                                        <span class="badge bg-warning">معلقة</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">مرفوضة</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="viewTransaction(<?php echo $transaction["transaction_id"]; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($transaction["status"] === "pending"): ?>
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="editTransaction(<?php echo $transaction["transaction_id"]; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteTransaction(<?php echo $transaction["transaction_id"]; ?>)">
                                                <i class="fas fa-trash"></i>
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

<!-- Modal for Add Transaction -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTransactionModalLabel">إضافة معاملة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="add_transaction">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="transaction_number" class="form-label">رقم المعاملة</label>
                            <input type="text" class="form-control" id="transaction_number" name="transaction_number" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="transaction_type" class="form-label">نوع المعاملة</label>
                            <select class="form-select" id="transaction_type" name="transaction_type" required>
                                <option value="">اختر نوع المعاملة</option>
                                <option value="توثيق عقد">توثيق عقد</option>
                                <option value="شهادة وراثة">شهادة وراثة</option>
                                <option value="وكالة">وكالة</option>
                                <option value="إقرار">إقرار</option>
                                <option value="تصديق توقيع">تصديق توقيع</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="client_name" class="form-label">اسم العميل</label>
                            <input type="text" class="form-control" id="client_name" name="client_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="client_id" class="form-label">رقم الهوية</label>
                            <input type="text" class="form-control" id="client_id" name="client_id" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="transaction_date" class="form-label">تاريخ المعاملة</label>
                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="transaction_details" class="form-label">تفاصيل المعاملة</label>
                        <textarea class="form-control" id="transaction_details" name="transaction_details" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة المعاملة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for Edit Transaction -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTransactionModalLabel">تعديل المعاملة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="edit_transaction">
                <input type="hidden" name="transaction_id" id="edit_transaction_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_transaction_number" class="form-label">رقم المعاملة</label>
                            <input type="text" class="form-control" id="edit_transaction_number" name="transaction_number" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_transaction_type" class="form-label">نوع المعاملة</label>
                            <select class="form-select" id="edit_transaction_type" name="transaction_type" required>
                                <option value="">اختر نوع المعاملة</option>
                                <option value="توثيق عقد">توثيق عقد</option>
                                <option value="شهادة وراثة">شهادة وراثة</option>
                                <option value="وكالة">وكالة</option>
                                <option value="إقرار">إقرار</option>
                                <option value="تصديق توقيع">تصديق توقيع</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_client_name" class="form-label">اسم العميل</label>
                            <input type="text" class="form-control" id="edit_client_name" name="client_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_client_id" class="form-label">رقم الهوية</label>
                            <input type="text" class="form-control" id="edit_client_id" name="client_id" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_transaction_date" class="form-label">تاريخ المعاملة</label>
                        <input type="date" class="form-control" id="edit_transaction_date" name="transaction_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_transaction_details" class="form-label">تفاصيل المعاملة</label>
                        <textarea class="form-control" id="edit_transaction_details" name="transaction_details" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for View Transaction -->
<div class="modal fade" id="viewTransactionModal" tabindex="-1" aria-labelledby="viewTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTransactionModalLabel">تفاصيل المعاملة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body" id="viewTransactionContent">
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
$pageJS = '
$(document).ready(function() {
    $("#transactionsTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 4, "desc" ]],
        "pageLength": 25
    });
});

function viewTransaction(transactionId) {
    fetch("../api/get_transaction_details.php?id=" + transactionId)
        .then(response => response.text())
        .then(html => {
            document.getElementById("viewTransactionContent").innerHTML = html;
            var modal = new bootstrap.Modal(document.getElementById("viewTransactionModal"));
            modal.show();
        })
        .catch(error => {
            console.error("Error:", error);
            alert("حدث خطأ أثناء جلب تفاصيل المعاملة.");
        });
}

function editTransaction(transactionId) {
    fetch("../api/get_transaction_details.php?id=" + transactionId)
        .then(response => response.json())
        .then(data => {
            document.getElementById("edit_transaction_id").value = data.transaction_id;
            document.getElementById("edit_transaction_number").value = data.transaction_number;
            document.getElementById("edit_transaction_type").value = data.transaction_type;
            document.getElementById("edit_client_name").value = data.client_name;
            document.getElementById("edit_client_id").value = data.client_id;
            document.getElementById("edit_transaction_date").value = data.transaction_date;
            document.getElementById("edit_transaction_details").value = data.transaction_details;
            
            var modal = new bootstrap.Modal(document.getElementById("editTransactionModal"));
            modal.show();
        })
        .catch(error => {
            console.error("Error:", error);
            alert("حدث خطأ أثناء جلب بيانات المعاملة.");
        });
}

function deleteTransaction(transactionId) {
    if (confirm("هل أنت متأكد من حذف هذه المعاملة؟")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            ' . generateCsrfField() . '
            <input type="hidden" name="action" value="delete_transaction">
            <input type="hidden" name="transaction_id" value="${transactionId}">
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

