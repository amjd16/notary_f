<?php
/**
 * صفحة إدارة المديريات والقرى
 * Districts and Villages Management Page
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
$pageTitle = "إدارة المديريات والقرى";
$pageDescription = "إضافة وتعديل وحذف المديريات والقرى التابعة لها.";
$breadcrumbs = [
    ["title" => "إدارة النظام"],
    ["title" => "إدارة المديريات والقرى"]
];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "add_district":
            $name = sanitizeInput($_POST["name"]);
            $governorate = sanitizeInput($_POST["governorate"]);
            $result = $database->insert("INSERT INTO districts (name, governorate) VALUES (?, ?)", [$name, $governorate]);
            if ($result) {
                setFlashMessage("success", "تم إضافة المديرية بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء إضافة المديرية.");
            }
            break;
            
        case "edit_district":
            $district_id = (int)$_POST["district_id"];
            $name = sanitizeInput($_POST["name"]);
            $governorate = sanitizeInput($_POST["governorate"]);
            $result = $database->update("UPDATE districts SET name = ?, governorate = ? WHERE district_id = ?", [$name, $governorate, $district_id]);
            if ($result) {
                setFlashMessage("success", "تم تحديث بيانات المديرية بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء تحديث بيانات المديرية.");
            }
            break;
            
        case "delete_district":
            $district_id = (int)$_POST["district_id"];
            $result = $database->delete("DELETE FROM districts WHERE district_id = ?", [$district_id]);
            if ($result) {
                setFlashMessage("success", "تم حذف المديرية بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء حذف المديرية.");
            }
            break;
            
        case "add_village":
            $district_id = (int)$_POST["district_id"];
            $name = sanitizeInput($_POST["name"]);
            $result = $database->insert("INSERT INTO villages (district_id, name) VALUES (?, ?)", [$district_id, $name]);
            if ($result) {
                setFlashMessage("success", "تم إضافة القرية بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء إضافة القرية.");
            }
            break;
            
        case "edit_village":
            $village_id = (int)$_POST["village_id"];
            $name = sanitizeInput($_POST["name"]);
            $result = $database->update("UPDATE villages SET name = ? WHERE village_id = ?", [$name, $village_id]);
            if ($result) {
                setFlashMessage("success", "تم تحديث بيانات القرية بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء تحديث بيانات القرية.");
            }
            break;
            
        case "delete_village":
            $village_id = (int)$_POST["village_id"];
            $result = $database->delete("DELETE FROM villages WHERE village_id = ?", [$village_id]);
            if ($result) {
                setFlashMessage("success", "تم حذف القرية بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء حذف القرية.");
            }
            break;
    }
    
    redirect("districts.php");
}

// جلب قائمة المديريات والقرى
$districts = $database->select("SELECT * FROM districts ORDER BY governorate, name");
$villages = $database->select("SELECT v.*, d.name as district_name FROM villages v JOIN districts d ON v.district_id = d.district_id ORDER BY d.name, v.name");

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">إدارة المديريات</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDistrictModal">
                    <i class="fas fa-plus me-2"></i>إضافة مديرية
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="districtsTable">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>المحافظة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($districts as $district): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($district["name"]); ?></td>
                                <td><?php echo htmlspecialchars($district["governorate"]); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editDistrict(<?php echo $district["district_id"]; ?>, 
                                                '<?php echo htmlspecialchars($district["name"]); ?>', 
                                                '<?php echo htmlspecialchars($district["governorate"]); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteDistrict(<?php echo $district["district_id"]; ?>)">
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
    
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">إدارة القرى</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addVillageModal">
                    <i class="fas fa-plus me-2"></i>إضافة قرية
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="villagesTable">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>المديرية</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($villages as $village): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($village["name"]); ?></td>
                                <td><?php echo htmlspecialchars($village["district_name"]); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editVillage(<?php echo $village["village_id"]; ?>, 
                                                '<?php echo htmlspecialchars($village["name"]); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteVillage(<?php echo $village["village_id"]; ?>)">
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

<!-- نافذة إضافة مديرية -->
<div class="modal fade" id="addDistrictModal" tabindex="-1" aria-labelledby="addDistrictModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDistrictModalLabel">إضافة مديرية جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="addDistrictForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="add_district">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="district_name" class="form-label">اسم المديرية</label>
                        <input type="text" class="form-control" id="district_name" name="name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="governorate" class="form-label">المحافظة</label>
                        <input type="text" class="form-control" id="governorate" name="governorate" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تعديل مديرية -->
<div class="modal fade" id="editDistrictModal" tabindex="-1" aria-labelledby="editDistrictModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editDistrictModalLabel">تعديل المديرية</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="editDistrictForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="edit_district">
                <input type="hidden" name="district_id" id="edit_district_id">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="edit_district_name" class="form-label">اسم المديرية</label>
                        <input type="text" class="form-control" id="edit_district_name" name="name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="edit_governorate" class="form-label">المحافظة</label>
                        <input type="text" class="form-control" id="edit_governorate" name="governorate" required>
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

<!-- نافذة إضافة قرية -->
<div class="modal fade" id="addVillageModal" tabindex="-1" aria-labelledby="addVillageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addVillageModalLabel">إضافة قرية جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="addVillageForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="add_village">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="village_district_id" class="form-label">المديرية</label>
                        <select class="form-control" id="village_district_id" name="district_id" required>
                            <option value="">اختر المديرية</option>
                            <?php foreach ($districts as $district): ?>
                                <option value="<?php echo $district["district_id"]; ?>">
                                    <?php echo htmlspecialchars($district["name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label for="village_name" class="form-label">اسم القرية</label>
                        <input type="text" class="form-control" id="village_name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تعديل قرية -->
<div class="modal fade" id="editVillageModal" tabindex="-1" aria-labelledby="editVillageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editVillageModalLabel">تعديل القرية</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="editVillageForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="edit_village">
                <input type="hidden" name="village_id" id="edit_village_id">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="edit_village_name" class="form-label">اسم القرية</label>
                        <input type="text" class="form-control" id="edit_village_name" name="name" required>
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

<?php
// JavaScript مخصص للصفحة
$pageJS = 
'$(document).ready(function() {
    $("#districtsTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 0, "asc" ]]
    });
    $("#villagesTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 1, "asc" ]]
    });
});

function editDistrict(id, name, governorate) {
    $("#edit_district_id").val(id);
    $("#edit_district_name").val(name);
    $("#edit_governorate").val(governorate);
    var editModal = new bootstrap.Modal(document.getElementById("editDistrictModal"));
    editModal.show();
}

function deleteDistrict(id) {
    if (confirm("هل أنت متأكد من حذف هذه المديرية؟ سيتم حذف جميع القرى التابعة لها أيضاً.")) {
        var form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            ' . generateCsrfField() . '
            <input type="hidden" name="action" value="delete_district">
            <input type="hidden" name="district_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function editVillage(id, name) {
    $("#edit_village_id").val(id);
    $("#edit_village_name").val(name);
    var editModal = new bootstrap.Modal(document.getElementById("editVillageModal"));
    editModal.show();
}

function deleteVillage(id) {
    if (confirm("هل أنت متأكد من حذف هذه القرية؟")) {
        var form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            ' . generateCsrfField() . '
            <input type="hidden" name="action" value="delete_village">
            <input type="hidden" name="village_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
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

