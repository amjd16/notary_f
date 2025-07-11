<?php
/**
 * صفحة الرسائل والإشعارات للأمين الشرعي
 * Messages and Notifications Page for Notary
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
$pageTitle = "الرسائل والإشعارات";
$pageDescription = "عرض الرسائل والإشعارات الواردة للأمين الشرعي.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "الرسائل والإشعارات"]
];

// جلب معرف المستخدم الحالي
$current_user = getCurrentUser();
$notary_id = $current_user["user_id"];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "mark_as_read":
            $message_id = (int)$_POST["message_id"];
            $database->update("UPDATE messages SET is_read = 1 WHERE message_id = ? AND recipient_id = ?", [$message_id, $notary_id]);
            break;
            
        case "delete_message":
            $message_id = (int)$_POST["message_id"];
            $database->delete("DELETE FROM messages WHERE message_id = ? AND recipient_id = ?", [$message_id, $notary_id]);
            break;
    }
    
    redirect("messages.php");
}

// جلب الرسائل الواردة
$messages = $database->select("SELECT m.*, u.full_name as sender_name 
                                FROM messages m 
                                JOIN users u ON m.sender_id = u.user_id 
                                WHERE m.recipient_id = ? 
                                ORDER BY m.sent_at DESC", [$notary_id]);

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">صندوق الوارد</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="messagesTable">
                        <thead>
                            <tr>
                                <th>المرسل</th>
                                <th>الموضوع</th>
                                <th>تاريخ الإرسال</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $message): ?>
                            <tr class="<?php echo $message["is_read"] ? '' : 'fw-bold'; ?>">
                                <td><?php echo htmlspecialchars($message["sender_name"]); ?></td>
                                <td><?php echo htmlspecialchars($message["subject"]); ?></td>
                                <td><?php echo formatDate($message["sent_at"], true); ?></td>
                                <td>
                                    <?php if ($message["is_read"]): ?>
                                        <span class="badge bg-secondary">مقروءة</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">جديدة</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="viewMessage(<?php echo $message["message_id"]; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteMessage(<?php echo $message["message_id"]; ?>)">
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

<!-- Modal for Message Details -->
<div class="modal fade" id="messageDetailsModal" tabindex="-1" aria-labelledby="messageDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageDetailsModalLabel">تفاصيل الرسالة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body" id="messageDetailsContent">
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

// تهيئة جدول الرسائل
$(document).ready(function() {
    $("#messagesTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 2, "desc" ]]
    });
});

// عرض تفاصيل الرسالة
function viewMessage(messageId) {
    fetch("../api/get_message_details.php?id=" + messageId)
        .then(response => response.text())
        .then(html => {
            document.getElementById("messageDetailsContent").innerHTML = html;
            var modal = new bootstrap.Modal(document.getElementById("messageDetailsModal"));
            modal.show();
            
            // تحديث حالة الرسالة إلى مقروءة
            const form = document.createElement("form");
            form.method = "POST";
            form.innerHTML = `
                <input type="hidden" name="_csrf_token" value="<?php echo $_SESSION[\"csrf_token\"]; ?>">
                <input type="hidden" name="action" value="mark_as_read">
                <input type="hidden" name="message_id" value="${messageId}">
            `;
            document.body.appendChild(form);
            form.submit();
        })
        .catch(error => {
            console.error("Error:", error);
            alert("حدث خطأ أثناء جلب تفاصيل الرسالة.");
        });
}

// حذف الرسالة
function deleteMessage(messageId) {
    if (confirm("هل أنت متأكد من حذف هذه الرسالة؟")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            <input type="hidden" name="_csrf_token" value="<?php echo $_SESSION[\"csrf_token\"]; ?>">
            <input type="hidden" name="action" value="delete_message">
            <input type="hidden" name="message_id" value="${messageId}">
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

