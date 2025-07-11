<?php
/**
 * صفحة إدارة الوثائق والمستندات للأمين الشرعي
 * Documents Management Page for Notary
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
$pageTitle = "إدارة الوثائق والمستندات";
$pageDescription = "إدارة الوثائق والمستندات الخاصة بالمعاملات.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "إدارة الوثائق"]
];

// جلب معرف المستخدم الحالي
$current_user = getCurrentUser();
$notary_id = $current_user["user_id"];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "upload_document":
            $transaction_id = (int)$_POST["transaction_id"];
            $document_type = sanitizeInput($_POST["document_type"]);
            $document_description = sanitizeInput($_POST["document_description"]);
            
            // التأكد من أن المعاملة تابعة للأمين الحالي
            $transaction_data = $database->selectOne("SELECT notary_id FROM transactions WHERE transaction_id = ?", [$transaction_id]);
            if ($transaction_data && $transaction_data["notary_id"] == $notary_id) {
                
                if (isset($_FILES["document_file"]) && $_FILES["document_file"]["error"] === UPLOAD_ERR_OK) {
                    $fileUpload = new FileUpload();
                    $upload_result = $fileUpload->uploadFile($_FILES["document_file"], "documents");
                    
                    if ($upload_result["success"]) {
                        $result = $database->insert("INSERT INTO documents (transaction_id, document_type, document_description, file_path, file_name, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)", 
                                                  [$transaction_id, $document_type, $document_description, $upload_result["file_path"], $upload_result["original_name"], $upload_result["file_size"], $notary_id]);
                        
                        if ($result) {
                            setFlashMessage("success", "تم رفع الوثيقة بنجاح.");
                        } else {
                            setFlashMessage("error", "حدث خطأ أثناء حفظ بيانات الوثيقة.");
                        }
                    } else {
                        setFlashMessage("error", "حدث خطأ أثناء رفع الملف: " . $upload_result["message"]);
                    }
                } else {
                    setFlashMessage("error", "يرجى اختيار ملف للرفع.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لرفع وثيقة لهذه المعاملة.");
            }
            break;
            
        case "delete_document":
            $document_id = (int)$_POST["document_id"];
            
            // التأكد من أن الوثيقة تابعة لمعاملة الأمين الحالي
            $document_data = $database->selectOne("SELECT d.file_path, t.notary_id FROM documents d JOIN transactions t ON d.transaction_id = t.transaction_id WHERE d.document_id = ?", [$document_id]);
            if ($document_data && $document_data["notary_id"] == $notary_id) {
                $result = $database->delete("DELETE FROM documents WHERE document_id = ?", [$document_id]);
                
                if ($result) {
                    // حذف الملف من النظام
                    if (file_exists($document_data["file_path"])) {
                        unlink($document_data["file_path"]);
                    }
                    setFlashMessage("success", "تم حذف الوثيقة بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء حذف الوثيقة.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لحذف هذه الوثيقة.");
            }
            break;
    }
    
    redirect("documents.php");
}

// جلب الوثائق الخاصة بمعاملات الأمين الحالي
$documents = $database->select("SELECT d.*, t.transaction_number, t.transaction_type, t.client_name 
                                FROM documents d 
                                JOIN transactions t ON d.transaction_id = t.transaction_id 
                                WHERE t.notary_id = ? 
                                ORDER BY d.uploaded_at DESC", [$notary_id]);

// جلب المعاملات الخاصة بالأمين الحالي (لرفع الوثائق)
$transactions = $database->select("SELECT transaction_id, transaction_number, transaction_type, client_name FROM transactions WHERE notary_id = ? ORDER BY transaction_date DESC", [$notary_id]);

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة الوثائق والمستندات</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                    <i class="fas fa-upload me-2"></i>رفع وثيقة جديدة
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="documentsTable">
                        <thead>
                            <tr>
                                <th>اسم الملف</th>
                                <th>نوع الوثيقة</th>
                                <th>المعاملة</th>
                                <th>العميل</th>
                                <th>الحجم</th>
                                <th>تاريخ الرفع</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($document["file_name"]); ?></td>
                                <td><?php echo htmlspecialchars($document["document_type"]); ?></td>
                                <td><?php echo htmlspecialchars($document["transaction_number"]); ?> - <?php echo htmlspecialchars($document["transaction_type"]); ?></td>
                                <td><?php echo htmlspecialchars($document["client_name"]); ?></td>
                                <td><?php echo formatFileSize($document["file_size"]); ?></td>
                                <td><?php echo formatDate($document["uploaded_at"], true); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo htmlspecialchars($document["file_path"]); ?>" 
                                           class="btn btn-outline-info" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($document["file_path"]); ?>" 
                                           class="btn btn-outline-success" download>
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteDocument(<?php echo $document["document_id"]; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

<!-- Modal for Upload Document -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadDocumentModalLabel">رفع وثيقة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="upload_document">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="transaction_id" class="form-label">المعاملة</label>
                        <select class="form-select" id="transaction_id" name="transaction_id" required>
                            <option value="">اختر معاملة</option>
                            <?php foreach ($transactions as $transaction): ?>
                                <option value="<?php echo $transaction["transaction_id"]; ?>">
                                    <?php echo htmlspecialchars($transaction["transaction_number"]); ?> - 
                                    <?php echo htmlspecialchars($transaction["transaction_type"]); ?> - 
                                    <?php echo htmlspecialchars($transaction["client_name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="document_type" class="form-label">نوع الوثيقة</label>
                        <select class="form-select" id="document_type" name="document_type" required>
                            <option value="">اختر نوع الوثيقة</option>
                            <option value="هوية شخصية">هوية شخصية</option>
                            <option value="عقد">عقد</option>
                            <option value="شهادة">شهادة</option>
                            <option value="وكالة">وكالة</option>
                            <option value="إقرار">إقرار</option>
                            <option value="مستند داعم">مستند داعم</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="document_description" class="form-label">وصف الوثيقة</label>
                        <textarea class="form-control" id="document_description" name="document_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="document_file" class="form-label">الملف</label>
                        <input type="file" class="form-control" id="document_file" name="document_file" 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <div class="form-text">الأنواع المسموحة: PDF, DOC, DOCX, JPG, PNG (الحد الأقصى: 10MB)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">رفع الوثيقة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// JavaScript مخصص للصفحة
$pageJS = '
$(document).ready(function() {
    $("#documentsTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 5, "desc" ]],
        "pageLength": 25
    });
});

function deleteDocument(documentId) {
    if (confirm("هل أنت متأكد من حذف هذه الوثيقة؟")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            ' . generateCsrfField() . '
            <input type="hidden" name="action" value="delete_document">
            <input type="hidden" name="document_id" value="${documentId}">
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

