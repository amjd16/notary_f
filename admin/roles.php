<?php
/**
 * صفحة إدارة الصلاحيات والأدوار
 * Roles and Permissions Management Page
 */

// تعريف الوصول للنظام
define("SYSTEM_ACCESS", true);

// تضمين الملفات المطلوبة
require_once "../config/config.php";
require_once "../config/database.php";
require_once "../includes/helpers.php";
require_once "../includes/session.php";
require_once "../includes/auth.php";
require_once "../includes/role_manager.php";
require_once "../includes/page_guard.php";

// حماية الصفحة: تتطلب صلاحية مدير النظام
requireRole("Administrator");

// إعدادات الصفحة
$pageTitle = "إدارة الصلاحيات والأدوار";
$pageDescription = "إدارة الأدوار وتحديد الصلاحيات لكل دور.";
$breadcrumbs = [
    ["title" => "إدارة النظام"],
    ["title" => "الصلاحيات والأدوار"]
];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "add_role":
            $role_name = sanitizeInput($_POST["role_name"]);
            $permissions = $_POST["permissions"] ?? [];
            $result = $roleManager->createRole($role_name, $permissions);
            if ($result) {
                setFlashMessage("success", "تم إضافة الدور بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء إضافة الدور.");
            }
            break;
            
        case "edit_role":
            $role_id = (int)$_POST["role_id"];
            $role_name = sanitizeInput($_POST["role_name"]);
            $permissions = $_POST["permissions"] ?? [];
            $result = $roleManager->updateRole($role_id, $role_name, $permissions);
            if ($result) {
                setFlashMessage("success", "تم تحديث الدور بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء تحديث الدور.");
            }
            break;
            
        case "delete_role":
            $role_id = (int)$_POST["role_id"];
            $result = $roleManager->deleteRole($role_id);
            if ($result) {
                setFlashMessage("success", "تم حذف الدور بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء حذف الدور.");
            }
            break;
    }
    
    redirect("roles.php");
}

// جلب قائمة الأدوار والصلاحيات
$roles = $roleManager->getAllRolesWithPermissions();
$allPermissions = $roleManager->getAllPermissions();

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة الأدوار</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                    <i class="fas fa-plus me-2"></i>إضافة دور جديد
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="rolesTable">
                        <thead>
                            <tr>
                                <th>اسم الدور</th>
                                <th>الصلاحيات</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($role["role_name"]); ?></td>
                                <td>
                                    <?php if (!empty($role["permissions"])): ?>
                                        <?php foreach ($role["permissions"] as $permission): ?>
                                            <span class="badge bg-info me-1 mb-1"><?php echo htmlspecialchars($permission["permission_name"]); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">لا توجد صلاحيات</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editRole(<?php echo $role["role_id"]; ?>, 
                                                '<?php echo htmlspecialchars($role["role_name"]); ?>', 
                                                <?php echo json_encode(array_column($role["permissions"], 'permission_id')); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteRole(<?php echo $role["role_id"]; ?>)">
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

<!-- نافذة إضافة دور جديد -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addRoleModalLabel">إضافة دور جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="addRoleForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="add_role">
                
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="role_name" class="form-label">اسم الدور</label>
                        <input type="text" class="form-control" id="role_name" name="role_name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">الصلاحيات</label>
                        <div class="row">
                            <?php foreach ($allPermissions as $permission): ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="permissions[]" value="<?php echo $permission["permission_id"]; ?>" 
                                               id="permission_<?php echo $permission["permission_id"]; ?>">
                                        <label class="form-check-label" for="permission_<?php echo $permission["permission_id"]; ?>">
                                            <?php echo htmlspecialchars($permission["permission_name"]); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة الدور</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تعديل دور -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRoleModalLabel">تعديل الدور</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="editRoleForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="edit_role">
                <input type="hidden" name="role_id" id="edit_role_id">
                
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="edit_role_name" class="form-label">اسم الدور</label>
                        <input type="text" class="form-control" id="edit_role_name" name="role_name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">الصلاحيات</label>
                        <div class="row">
                            <?php foreach ($allPermissions as $permission): ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input edit-permission-checkbox" type="checkbox" 
                                               name="permissions[]" value="<?php echo $permission["permission_id"]; ?>" 
                                               id="edit_permission_<?php echo $permission["permission_id"]; ?>">
                                        <label class="form-check-label" for="edit_permission_<?php echo $permission["permission_id"]; ?>">
                                            <?php echo htmlspecialchars($permission["permission_name"]); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
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
    $("#rolesTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 0, "asc" ]]
    });
});

function editRole(roleId, roleName, rolePermissions) {
    $("#edit_role_id").val(roleId);
    $("#edit_role_name").val(roleName);
    
    // إلغاء تحديد جميع الصلاحيات أولاً
    $(".edit-permission-checkbox").prop("checked", false);
    
    // تحديد الصلاحيات الخاصة بالدور
    rolePermissions.forEach(function(permissionId) {
        $("#edit_permission_" + permissionId).prop("checked", true);
    });

    var editModal = new bootstrap.Modal(document.getElementById("editRoleModal"));
    editModal.show();
}

function deleteRole(roleId) {
    if (confirm("هل أنت متأكد من حذف هذا الدور؟ سيتم تعيين المستخدمين المرتبطين بهذا الدور إلى الدور الافتراضي.")) {
        var form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            ' . generateCsrfField() . '
            <input type="hidden" name="action" value="delete_role">
            <input type="hidden" name="role_id" value="${roleId}">
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

