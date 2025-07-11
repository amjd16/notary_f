<?php
/**
 * صفحة مراقبة النظام والأنشطة
 * System Monitoring and Activities Page
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

// حماية الصفحة: تتطلب صلاحية مدير النظام
requireRole("Administrator");

// إعدادات الصفحة
$pageTitle = "مراقبة النظام والأنشطة";
$pageDescription = "مراقبة سجلات النظام والأنشطة الأخيرة للمستخدمين.";
$breadcrumbs = [
    ["title" => "إدارة النظام"],
    ["title" => "مراقبة النظام"]
];

// جلب سجلات الأنشطة
$activities = $database->select("SELECT l.*, u.full_name, r.role_name 
                                   FROM logs l 
                                   LEFT JOIN users u ON l.user_id = u.user_id 
                                   LEFT JOIN roles r ON u.role_id = r.role_id 
                                   ORDER BY l.timestamp DESC LIMIT 500");

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">سجل الأنشطة</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="activitiesTable">
                        <thead>
                            <tr>
                                <th>التاريخ والوقت</th>
                                <th>المستخدم</th>
                                <th>الدور</th>
                                <th>النوع</th>
                                <th>العملية</th>
                                <th>الوصف</th>
                                <th>عنوان IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td><?php echo formatDate($activity["timestamp"], true); ?></td>
                                <td><?php echo htmlspecialchars($activity["full_name"] ?? "غير معروف"); ?></td>
                                <td><?php echo htmlspecialchars($activity["role_name"] ?? "غير معروف"); ?></td>
                                <td><span class="badge bg-<?php echo getLogTypeBadge($activity["log_type"]); ?>"><?php echo htmlspecialchars($activity["log_type"]); ?></span></td>
                                <td><?php echo htmlspecialchars($activity["action"]); ?></td>
                                <td><?php echo htmlspecialchars($activity["description"]); ?></td>
                                <td><?php echo htmlspecialchars($activity["ip_address"]); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// JavaScript مخصص للصفحة
$pageJS = 
'$(document).ready(function() {
    $("#activitiesTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 0, "desc" ]],
        "pageLength": 50
    });
});

function getLogTypeBadge(logType) {
    switch(logType) {
        case "Login": return "success";
        case "Logout": return "info";
        case "Error": return "danger";
        case "Warning": return "warning";
        case "Activity": return "primary";
        default: return "secondary";
    }
}
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

