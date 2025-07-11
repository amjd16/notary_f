<?php
/**
 * لوحة تحكم رئيس قلم التوثيق
 * Supervisor Dashboard
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
$pageTitle = "لوحة تحكم رئيس قلم التوثيق";
$pageDescription = "نظرة عامة على أنشطة الأمناء والمعاملات في المديرية.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"]
];

// جلب معرف المديرية للمستخدم الحالي
$user_district_id = getCurrentUser()["district_id"];

// جلب الإحصائيات الخاصة بالمديرية
$stats = [
    "total_notaries" => $database->count("SELECT COUNT(*) FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = ?) AND district_id = ?", ["Notary", $user_district_id]),
    "active_notaries" => $database->count("SELECT COUNT(*) FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = ?) AND district_id = ? AND is_active = 1", ["Notary", $user_district_id]),
    "total_transactions" => $database->count("SELECT COUNT(*) FROM transactions t JOIN users u ON t.notary_id = u.user_id WHERE u.district_id = ?", [$user_district_id]),
    "pending_transactions" => $database->count("SELECT COUNT(*) FROM transactions t JOIN users u ON t.notary_id = u.user_id WHERE u.district_id = ? AND t.status = 'pending'", [$user_district_id]),
    "active_licenses" => $database->count("SELECT COUNT(*) FROM licenses l JOIN users u ON l.notary_id = u.user_id WHERE u.district_id = ? AND l.status = 'active'", [$user_district_id]),
    "expired_licenses" => $database->count("SELECT COUNT(*) FROM licenses l JOIN users u ON l.notary_id = u.user_id WHERE u.district_id = ? AND l.status = 'expired'", [$user_district_id])
];

// جلب آخر المعاملات
$recent_transactions = $database->select("SELECT t.*, u.full_name as notary_name 
                                           FROM transactions t 
                                           JOIN users u ON t.notary_id = u.user_id 
                                           WHERE u.district_id = ? 
                                           ORDER BY t.transaction_date DESC 
                                           LIMIT 10", [$user_district_id]);

// جلب التراخيص المنتهية قريباً
$expiring_licenses = $database->select("SELECT l.*, u.full_name as notary_name 
                                         FROM licenses l 
                                         JOIN users u ON l.notary_id = u.user_id 
                                         WHERE u.district_id = ? 
                                         AND l.status = 'active' 
                                         AND l.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
                                         ORDER BY l.expiry_date ASC", [$user_district_id]);

// تضمين رأس الصفحة
include "../templates/header.php";
?>

<div class="row">
    <!-- إحصائيات سريعة -->
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-start border-primary border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-primary">إجمالي الأمناء</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $stats["total_notaries"]; ?></h3>
                        <small class="text-muted">نشط: <?php echo $stats["active_notaries"]; ?></small>
                    </div>
                    <i class="fas fa-users fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-success">إجمالي المعاملات</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $stats["total_transactions"]; ?></h3>
                        <small class="text-muted">معلقة: <?php echo $stats["pending_transactions"]; ?></small>
                    </div>
                    <i class="fas fa-file-alt fa-2x text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-info">التراخيص النشطة</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $stats["active_licenses"]; ?></h3>
                    </div>
                    <i class="fas fa-id-card fa-2x text-info"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-start border-danger border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-danger">التراخيص المنتهية</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $stats["expired_licenses"]; ?></h3>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- آخر المعاملات -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">آخر المعاملات</h5>
                <a href="transactions.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>رقم المعاملة</th>
                                <th>الأمين</th>
                                <th>النوع</th>
                                <th>التاريخ</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction["transaction_number"]); ?></td>
                                <td><?php echo htmlspecialchars($transaction["notary_name"]); ?></td>
                                <td><?php echo htmlspecialchars($transaction["transaction_type"]); ?></td>
                                <td><?php echo formatDate($transaction["transaction_date"]); ?></td>
                                <td>
                                    <?php if ($transaction["status"] === "completed"): ?>
                                        <span class="badge bg-success">مكتملة</span>
                                    <?php elseif ($transaction["status"] === "pending"): ?>
                                        <span class="badge bg-warning">معلقة</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">ملغاة</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- التراخيص المنتهية قريباً -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">تراخيص تنتهي قريباً</h5>
                <a href="licenses.php" class="btn btn-sm btn-outline-warning">عرض الكل</a>
            </div>
            <div class="card-body">
                <?php if (!empty($expiring_licenses)): ?>
                    <?php foreach ($expiring_licenses as $license): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong><?php echo htmlspecialchars($license["notary_name"]); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($license["license_number"]); ?></small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-warning"><?php echo formatDate($license["expiry_date"]); ?></span>
                            </div>
                        </div>
                        <hr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">لا توجد تراخيص تنتهي قريباً.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين تذييل الصفحة
include "../templates/footer.php";
?>

