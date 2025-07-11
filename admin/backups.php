<?php
/**
 * صفحة إدارة النسخ الاحتياطية
 * Backup Management Page
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
$pageTitle = "إدارة النسخ الاحتياطية";
$pageDescription = "إنشاء واستعادة وإدارة النسخ الاحتياطية لقاعدة البيانات.";
$breadcrumbs = [
    ["title" => "إدارة النظام"],
    ["title" => "النسخ الاحتياطية"]
];

// مسار حفظ النسخ الاحتياطية
$backupDir = __DIR__ . "/../backups/";
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "create_backup":
            $filename = "db_backup_" . date("Ymd_His") . ".sql";
            $filepath = $backupDir . $filename;
            $command = sprintf("mysqldump -u%s -p%s %s > %s", DB_USER, DB_PASS, DB_NAME, $filepath);
            
            exec($command, $output, $return_var);
            
            if ($return_var === 0) {
                setFlashMessage("success", "تم إنشاء نسخة احتياطية بنجاح: " . $filename);
            } else {
                setFlashMessage("error", "فشل إنشاء النسخة الاحتياطية. " . implode("\n", $output));
            }
            break;
            
        case "restore_backup":
            $filename = sanitizeInput($_POST["filename"]);
            $filepath = $backupDir . $filename;
            
            if (file_exists($filepath)) {
                $command = sprintf("mysql -u%s -p%s %s < %s", DB_USER, DB_PASS, DB_NAME, $filepath);
                exec($command, $output, $return_var);
                
                if ($return_var === 0) {
                    setFlashMessage("success", "تم استعادة النسخة الاحتياطية بنجاح: " . $filename);
                } else {
                    setFlashMessage("error", "فشل استعادة النسخة الاحتياطية. " . implode("\n", $output));
                }
            } else {
                setFlashMessage("error", "ملف النسخة الاحتياطية غير موجود.");
            }
            break;
            
        case "delete_backup":
            $filename = sanitizeInput($_POST["filename"]);
            $filepath = $backupDir . $filename;
            
            if (file_exists($filepath)) {
                if (unlink($filepath)) {
                    setFlashMessage("success", "تم حذف النسخة الاحتياطية بنجاح: " . $filename);
                } else {
                    setFlashMessage("error", "فشل حذف النسخة الاحتياطية.");
                }
            } else {
                setFlashMessage("error", "ملف النسخة الاحتياطية غير موجود.");
            }
            break;
    }
    
    redirect("backups.php");
}

// جلب قائمة النسخ الاحتياطية الموجودة
$backups = [];
$files = scandir($backupDir);
foreach ($files as $file) {
    if (preg_match("/.sql$/", $file)) {
        $backups[] = [
            "name" => $file,
            "size" => formatBytes(filesize($backupDir . $file)),
            "date" => date("Y-m-d H:i:s", filemtime($backupDir . $file))
        ];
    }
}

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">النسخ الاحتياطية لقاعدة البيانات</h5>
                <form method="POST" style="display:inline;">
                    <?php echo generateCsrfField(); ?>
                    <input type="hidden" name="action" value="create_backup">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>إنشاء نسخة احتياطية جديدة
                    </button>
                </form>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="backupsTable">
                        <thead>
                            <tr>
                                <th>اسم الملف</th>
                                <th>الحجم</th>
                                <th>تاريخ الإنشاء</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($backup["name"]); ?></td>
                                <td><?php echo htmlspecialchars($backup["size"]); ?></td>
                                <td><?php echo htmlspecialchars($backup["date"]); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <form method="POST" style="display:inline;" onsubmit="return confirm(\'هل أنت متأكد من استعادة هذه النسخة الاحتياطية؟ هذا الإجراء لا يمكن التراجع عنه وقد يؤدي إلى فقدان البيانات الحالية.\');">
                                            <?php echo generateCsrfField(); ?>
                                            <input type="hidden" name="action" value="restore_backup">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup["name"]); ?>">
                                            <button type="submit" class="btn btn-outline-success">
                                                <i class="fas fa-redo"></i> استعادة
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm(\'هل أنت متأكد من حذف هذه النسخة الاحتياطية؟\');">
                                            <?php echo generateCsrfField(); ?>
                                            <input type="hidden" name="action" value="delete_backup">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup["name"]); ?>">
                                            <button type="submit" class="btn btn-outline-danger">
                                                <i class="fas fa-trash"></i> حذف
                                            </button>
                                        </form>
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

<?php
// JavaScript مخصص للصفحة
$pageJS = 
'$(document).ready(function() {
    $("#backupsTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 2, "desc" ]]
    });
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

