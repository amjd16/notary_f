<?php
/**
 * صفحة إدارة المستخدمين
 * Users Management Page
 */

// تعريف الوصول للنظام
define("SYSTEM_ACCESS", true);

// تضمين الملفات المطلوبة
require_once "../config/config.php";
require_once "../config/database.php";
require_once "../includes/helpers.php";
require_once "../includes/session.php";
require_once "../includes/auth.php";
require_once "../includes/user_manager.php";
require_once "../includes/page_guard.php";

// حماية الصفحة: تتطلب صلاحية مدير النظام
requireRole("Administrator");

// إعدادات الصفحة
$pageTitle = "إدارة المستخدمين";
$pageDescription = "إضافة وتعديل وحذف المستخدمين وإدارة صلاحياتهم.";
$breadcrumbs = [
    ["title" => "إدارة النظام"],
    ["title" => "إدارة المستخدمين"]
];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "add_user":
            $result = $userManager->createUser($_POST);
            if ($result) {
                setFlashMessage("success", "تم إضافة المستخدم بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء إضافة المستخدم.");
            }
            break;
            
        case "edit_user":
            $result = $userManager->updateUser($_POST["user_id"], $_POST);
            if ($result) {
                setFlashMessage("success", "تم تحديث بيانات المستخدم بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء تحديث بيانات المستخدم.");
            }
            break;
            
        case "delete_user":
            $result = $userManager->deleteUser($_POST["user_id"]);
            if ($result) {
                setFlashMessage("success", "تم حذف المستخدم بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء حذف المستخدم.");
            }
            break;
            
        case "toggle_status":
            $result = $userManager->toggleUserStatus($_POST["user_id"]);
            if ($result) {
                setFlashMessage("success", "تم تغيير حالة المستخدم بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء تغيير حالة المستخدم.");
            }
            break;
    }
    
    redirect("users.php");
}

// جلب قائمة المستخدمين
$users = $userManager->getAllUsers();
$roles = $userManager->getAllRoles();

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة المستخدمين</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-2"></i>إضافة مستخدم جديد
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="usersTable">
                        <thead>
                            <tr>
                                <th>الاسم الكامل</th>
                                <th>اسم المستخدم</th>
                                <th>البريد الإلكتروني</th>
                                <th>الدور</th>
                                <th>المديرية</th>
                                <th>الحالة</th>
                                <th>تاريخ الإنشاء</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo $user['profile_image_url'] ?? '../assets/images/default-avatar.png'; ?>" 
                                             alt="الصورة الشخصية" class="rounded-circle me-2" width="32" height="32">
                                        <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($user['role_name']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($user['district_name'] ?? 'غير محدد'); ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">معطل</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editUser(<?php echo $user['user_id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" 
                                                onclick="toggleUserStatus(<?php echo $user['user_id']; ?>)">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteUser(<?php echo $user['user_id']; ?>)">
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

<!-- نافذة إضافة مستخدم جديد -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">إضافة مستخدم جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="addUserForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="add_user">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="first_name" class="form-label">الاسم الأول</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="last_name" class="form-label">الاسم الأخير</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="username" class="form-label">اسم المستخدم</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password" class="form-label">كلمة المرور</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="role_id" class="form-label">الدور</label>
                                <select class="form-control" id="role_id" name="role_id" required>
                                    <option value="">اختر الدور</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['role_id']; ?>">
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone" class="form-label">رقم الهاتف</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة المستخدم</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تعديل المستخدم -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">تعديل بيانات المستخدم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="editUserForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="modal-body">
                    <!-- نفس الحقول مع بادئة edit_ -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_first_name" class="form-label">الاسم الأول</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_last_name" class="form-label">الاسم الأخير</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_username" class="form-label">اسم المستخدم</label>
                                <input type="text" class="form-control" id="edit_username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_role_id" class="form-label">الدور</label>
                                <select class="form-control" id="edit_role_id" name="role_id" required>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['role_id']; ?>">
                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_phone" class="form-label">رقم الهاتف</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone">
                            </div>
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
$pageJS = '
// تهيئة جدول المستخدمين
$(document).ready(function() {
    $("#usersTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 6, "desc" ]],
        "pageLength": 25
    });
});

// دالة تعديل المستخدم
function editUser(userId) {
    // جلب بيانات المستخدم عبر AJAX
    fetch("../api/get_user.php?id=" + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById("edit_user_id").value = user.user_id;
                document.getElementById("edit_first_name").value = user.first_name;
                document.getElementById("edit_last_name").value = user.last_name;
                document.getElementById("edit_username").value = user.username;
                document.getElementById("edit_email").value = user.email;
                document.getElementById("edit_role_id").value = user.role_id;
                document.getElementById("edit_phone").value = user.phone || "";
                
                // إظهار النافذة
                const modal = new bootstrap.Modal(document.getElementById("editUserModal"));
                modal.show();
            } else {
                alert("حدث خطأ أثناء جلب بيانات المستخدم");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("حدث خطأ أثناء جلب بيانات المستخدم");
        });
}

// دالة تغيير حالة المستخدم
function toggleUserStatus(userId) {
    if (confirm("هل أنت متأكد من تغيير حالة هذا المستخدم؟")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            ' . generateCsrfField() . '
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// دالة حذف المستخدم
function deleteUser(userId) {
    if (confirm("هل أنت متأكد من حذف هذا المستخدم؟ هذا الإجراء لا يمكن التراجع عنه.")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            ' . generateCsrfField() . '
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// التحقق من تطابق كلمة المرور
document.getElementById("addUserForm").addEventListener("submit", function(e) {
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirm_password").value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert("كلمة المرور وتأكيد كلمة المرور غير متطابقتين");
        return false;
    }
});
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

