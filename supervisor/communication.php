<?php
/**
 * صفحة التواصل مع الأمناء
 * Communication Page with Notaries for Supervisor
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
$pageTitle = "التواصل مع الأمناء";
$pageDescription = "إرسال رسائل وتعميمات للأمناء في المديرية.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "التواصل مع الأمناء"]
];

// جلب معرف المديرية للمستخدم الحالي
$user_district_id = getCurrentUser()["district_id"];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "send_message":
            $recipient_type = sanitizeInput($_POST["recipient_type"]);
            $notary_id = (int)$_POST["notary_id"] ?? null;
            $subject = sanitizeInput($_POST["subject"]);
            $message_body = sanitizeInput($_POST["message_body"]);
            
            $recipients = [];
            if ($recipient_type === "all") {
                $recipients = $database->select("SELECT user_id FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = ?) AND district_id = ?", ["Notary", $user_district_id]);
            } elseif ($recipient_type === "specific" && $notary_id) {
                $recipients = [["user_id" => $notary_id]];
            }
            
            $success = true;
            foreach ($recipients as $recipient) {
                $result = $database->insert("INSERT INTO messages (sender_id, recipient_id, subject, message_body) VALUES (?, ?, ?, ?)", 
                                          [getCurrentUser()["user_id"], $recipient["user_id"], $subject, $message_body]);
                if (!$result) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                setFlashMessage("success", "تم إرسال الرسالة بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء إرسال الرسالة.");
            }
            break;
    }
    
    redirect("communication.php");
}

// جلب الأمناء في المديرية الحالية (لإرسال رسائل)
$notaries_in_district = $database->select("SELECT user_id, full_name FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = ?) AND district_id = ?", ["Notary", $user_district_id]);

// جلب الرسائل المرسلة
$sent_messages = $database->select("SELECT m.*, u.full_name as recipient_name 
                                     FROM messages m 
                                     JOIN users u ON m.recipient_id = u.user_id 
                                     WHERE m.sender_id = ? 
                                     ORDER BY m.sent_at DESC", [getCurrentUser()["user_id"]]);

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">إرسال رسالة جديدة</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo generateCsrfField(); ?>
                    <input type="hidden" name="action" value="send_message">
                    
                    <div class="mb-3">
                        <label for="recipient_type" class="form-label">إلى</label>
                        <select class="form-select" id="recipient_type" name="recipient_type" required>
                            <option value="all">جميع الأمناء في المديرية</option>
                            <option value="specific">أمين محدد</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="notary_selector" style="display: none;">
                        <label for="notary_id" class="form-label">الأمين</label>
                        <select class="form-select" id="notary_id" name="notary_id">
                            <option value="">اختر أمين</option>
                            <?php foreach ($notaries_in_district as $notary): ?>
                                <option value="<?php echo $notary["user_id"]; ?>"><?php echo htmlspecialchars($notary["full_name"]); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">الموضوع</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message_body" class="form-label">نص الرسالة</label>
                        <textarea class="form-control" id="message_body" name="message_body" rows="5" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">إرسال</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">الرسائل المرسلة</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="sentMessagesTable">
                        <thead>
                            <tr>
                                <th>المستلم</th>
                                <th>الموضوع</th>
                                <th>تاريخ الإرسال</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sent_messages as $message): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($message["recipient_name"]); ?></td>
                                <td><?php echo htmlspecialchars($message["subject"]); ?></td>
                                <td><?php echo formatDate($message["sent_at"], true); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                            onclick="viewMessage(<?php echo $message["message_id"]; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
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

// تهيئة جدول الرسائل المرسلة
$(document).ready(function() {
    $("#sentMessagesTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 2, "desc" ]]
    });
    
    // إظهار/إخفاء حقل اختيار الأمين
    $("#recipient_type").change(function() {
        if ($(this).val() === "specific") {
            $("#notary_selector").show();
            $("#notary_id").prop("required", true);
        } else {
            $("#notary_selector").hide();
            $("#notary_id").prop("required", false);
        }
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
        })
        .catch(error => {
            console.error("Error:", error);
            alert("حدث خطأ أثناء جلب تفاصيل الرسالة.");
        });
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

