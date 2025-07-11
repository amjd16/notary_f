<?php
/**
 * صفحة إدارة الأمناء في المديرية
 * Notaries Management Page for Supervisor
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

// حماية الصفحة: تتطلب صلاحية رئيس قلم التوثيق
requireRole("Supervisor");

// إعدادات الصفحة
$pageTitle = "إدارة الأمناء";
$pageDescription = "إدارة الأمناء الشرعيين التابعين للمديرية.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "إدارة الأمناء"]
];

// جلب معرف المديرية للمستخدم الحالي
$user_district_id = getCurrentUser()["district_id"];

// معالجة العمليات
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    
    switch ($action) {
        case "add_notary":
            // إضافة الأمين الجديد وتعيين دوره كـ Notary ومعرف المديرية
            $_POST["role_id"] = $userManager->getRoleIdByName("Notary");
            $_POST["district_id"] = $user_district_id;
            $result = $userManager->createUser($_POST);
            if ($result) {
                setFlashMessage("success", "تم إضافة الأمين بنجاح.");
            } else {
                setFlashMessage("error", "حدث خطأ أثناء إضافة الأمين.");
            }
            break;
            
        case "edit_notary":
            $user_id = $_POST["user_id"];
            // التأكد من أن الأمين تابع لنفس المديرية قبل التعديل
            $notary_data = $userManager->getUserById($user_id);
            if ($notary_data && $notary_data["district_id"] == $user_district_id) {
                $result = $userManager->updateUser($user_id, $_POST);
                if ($result) {
                    setFlashMessage("success", "تم تحديث بيانات الأمين بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء تحديث بيانات الأمين.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لتعديل هذا الأمين.");
            }
            break;
            
        case "toggle_status":
            $user_id = $_POST["user_id"];
            // التأكد من أن الأمين تابع لنفس المديرية قبل تغيير الحالة
            $notary_data = $userManager->getUserById($user_id);
            if ($notary_data && $notary_data["district_id"] == $user_district_id) {
                $result = $userManager->toggleUserStatus($user_id);
                if ($result) {
                    setFlashMessage("success", "تم تغيير حالة الأمين بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء تغيير حالة الأمين.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لتغيير حالة هذا الأمين.");
            }
            break;
            
        case "delete_notary":
            $user_id = $_POST["user_id"];
            // التأكد من أن الأمين تابع لنفس المديرية قبل الحذف
            $notary_data = $userManager->getUserById($user_id);
            if ($notary_data && $notary_data["district_id"] == $user_district_id) {
                $result = $userManager->deleteUser($user_id);
                if ($result) {
                    setFlashMessage("success", "تم حذف الأمين بنجاح.");
                } else {
                    setFlashMessage("error", "حدث خطأ أثناء حذف الأمين.");
                }
            } else {
                setFlashMessage("error", "لا تملك صلاحية لحذف هذا الأمين.");
            }
            break;
    }
    
    redirect("notaries.php");
}

// جلب الأمناء التابعين للمديرية الحالية فقط
$notaries = $userManager->getUsersByRoleAndDistrict("Notary", $user_district_id);

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">قائمة الأمناء في المديرية</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNotaryModal">
                    <i class="fas fa-plus me-2"></i>إضافة أمين جديد
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="notariesTable">
                        <thead>
                            <tr>
                                <th>الاسم الكامل</th>
                                <th>اسم المستخدم</th>
                                <th>البريد الإلكتروني</th>
                                <th>رقم الهاتف</th>
                                <th>الحالة</th>
                                <th>تاريخ الإنشاء</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notaries as $notary): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo $notary["profile_image_url"] ?? "../assets/images/default-avatar.png"; ?>" 
                                             alt="الصورة الشخصية" class="rounded-circle me-2" width="32" height="32">
                                        <span><?php echo htmlspecialchars($notary["full_name"]); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($notary["username"]); ?></td>
                                <td><?php echo htmlspecialchars($notary["email"]); ?></td>
                                <td><?php echo htmlspecialchars($notary["phone"] ?? "لا يوجد"); ?></td>
                                <td>
                                    <?php if ($notary["is_active"]): ?>
                                        <span class="badge bg-success">نشط</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">معطل</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($notary["created_at"]); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editNotary(<?php echo $notary["user_id"]; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-warning" 
                                                onclick="toggleNotaryStatus(<?php echo $notary["user_id"]; ?>)">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteNotary(<?php echo $notary["user_id"]; ?>)">
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

<!-- نافذة إضافة أمين جديد -->
<div class="modal fade" id="addNotaryModal" tabindex="-1" aria-labelledby="addNotaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNotaryModalLabel">إضافة أمين جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="addNotaryForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="add_notary">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="first_name" class="form-label">الاسم الأول</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="last_name" class="form-label">الاسم الأخير</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="username" class="form-label">اسم المستخدم</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="password" class="form-label">كلمة المرور</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="phone" class="form-label">رقم الهاتف</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة الأمين</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تعديل الأمين -->
<div class="modal fade" id="editNotaryModal" tabindex="-1" aria-labelledby="editNotaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editNotaryModalLabel">تعديل بيانات الأمين</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <form id="editNotaryForm" method="POST">
                <?php echo generateCsrfField(); ?>
                <input type="hidden" name="action" value="edit_notary">
                <input type="hidden" name="user_id" id="edit_notary_user_id">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_first_name" class="form-label">الاسم الأول</label>
                                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_last_name" class="form-label">الاسم الأخير</label>
                                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_username" class="form-label">اسم المستخدم</label>
                                <input type="text" class="form-control" id="edit_username" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_email" class="form-label">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="edit_phone" class="form-label">رقم الهاتف</label>
                        <input type="tel" class="form-control" id="edit_phone" name="phone">
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
'// تهيئة جدول الأمناء
$(document).ready(function() {
    $("#notariesTable").DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "order": [[ 5, "desc" ]],
        "pageLength": 25
    });
});

// دالة تعديل الأمين
function editNotary(userId) {
    // جلب بيانات الأمين عبر AJAX
    fetch("../api/get_user.php?id=" + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notary = data.user;
                document.getElementById("edit_notary_user_id").value = notary.user_id;
                document.getElementById("edit_first_name").value = notary.first_name;
                document.getElementById("edit_last_name").value = notary.last_name;
                document.getElementById("edit_username").value = notary.username;
                document.getElementById("edit_email").value = notary.email;
                document.getElementById("edit_phone").value = notary.phone || "";
                
                // إظهار النافذة
                const modal = new bootstrap.Modal(document.getElementById("editNotaryModal"));
                modal.show();
            } else {
                alert("حدث خطأ أثناء جلب بيانات الأمين");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("حدث خطأ أثناء جلب بيانات الأمين");
        });
}

// دالة تغيير حالة الأمين
function toggleNotaryStatus(userId) {
    if (confirm("هل أنت متأكد من تغيير حالة هذا الأمين؟")) {
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

// دالة حذف الأمين
function deleteNotary(userId) {
    if (confirm("هل أنت متأكد من حذف هذا الأمين؟ هذا الإجراء لا يمكن التراجع عنه.")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            ' . generateCsrfField() . '
            <input type="hidden" name="action" value="delete_notary">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// التحقق من تطابق كلمة المرور عند إضافة أمين جديد
document.getElementById("addNotaryForm").addEventListener("submit", function(e) {
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

