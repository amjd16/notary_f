<?php
/**
 * لوحة تحكم الأمين الشرعي
 * Notary Dashboard
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
$pageTitle = "لوحة تحكم الأمين الشرعي";
$pageDescription = "لوحة التحكم الرئيسية للأمين الشرعي.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"]
];

// جلب معرف المستخدم الحالي
$current_user = getCurrentUser();
$notary_id = $current_user["user_id"];

// جلب الإحصائيات الخاصة بالأمين
$stats = [
    "total_transactions" => $database->count("SELECT COUNT(*) FROM transactions WHERE notary_id = ?", [$notary_id]),
    "pending_transactions" => $database->count("SELECT COUNT(*) FROM transactions WHERE notary_id = ? AND status = 'pending'", [$notary_id]),
    "completed_transactions" => $database->count("SELECT COUNT(*) FROM transactions WHERE notary_id = ? AND status = 'completed'", [$notary_id]),
    "rejected_transactions" => $database->count("SELECT COUNT(*) FROM transactions WHERE notary_id = ? AND status = 'rejected'", [$notary_id]),
    "unread_messages" => $database->count("SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0", [$notary_id]),
];

// جلب آخر المعاملات
$recent_transactions = $database->select("SELECT * FROM transactions WHERE notary_id = ? ORDER BY transaction_date DESC LIMIT 5", [$notary_id]);

// جلب الترخيص الحالي
$current_license = $database->selectOne("SELECT * FROM licenses WHERE notary_id = ? AND status = 'active' ORDER BY issue_date DESC LIMIT 1", [$notary_id]);

// جلب آخر الرسائل
$recent_messages = $database->select("SELECT m.*, u.full_name as sender_name 
                                      FROM messages m 
                                      JOIN users u ON m.sender_id = u.user_id 
                                      WHERE m.recipient_id = ? 
                                      ORDER BY m.sent_at DESC LIMIT 5", [$notary_id]);

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
                        <h6 class="card-title text-primary">إجمالي المعاملات</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $stats["total_transactions"]; ?></h3>
                    </div>
                    <i class="fas fa-file-alt fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-start border-warning border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-warning">المعاملات المعلقة</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $stats["pending_transactions"]; ?></h3>
                    </div>
                    <i class="fas fa-hourglass-half fa-2x text-warning"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-success">المعاملات المكتملة</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $stats["completed_transactions"]; ?></h3>
                    </div>
                    <i class="fas fa-check-circle fa-2x text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="card border-start border-info border-4 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title text-info">الرسائل غير المقروءة</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $stats["unread_messages"]; ?></h3>
                    </div>
                    <i class="fas fa-envelope fa-2x text-info"></i>
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
                <?php if (empty($recent_transactions)): ?>
                    <p class="text-muted text-center">لا توجد معاملات حتى الآن.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>رقم المعاملة</th>
                                    <th>النوع</th>
                                    <th>التاريخ</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($transaction["transaction_number"]); ?></td>
                                    <td><?php echo htmlspecialchars($transaction["transaction_type"]); ?></td>
                                    <td><?php echo formatDate($transaction["transaction_date"]); ?></td>
                                    <td>
                                        <?php if ($transaction["status"] === "completed"): ?>
                                            <span class="badge bg-success">مكتملة</span>
                                        <?php elseif ($transaction["status"] === "pending"): ?>
                                            <span class="badge bg-warning">معلقة</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">مرفوضة</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- معلومات الترخيص -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">معلومات الترخيص</h5>
            </div>
            <div class="card-body">
                <?php if ($current_license): ?>
                    <div class="mb-3">
                        <strong>رقم الترخيص:</strong><br>
                        <?php echo htmlspecialchars($current_license["license_number"]); ?>
                    </div>
                    <div class="mb-3">
                        <strong>تاريخ الإصدار:</strong><br>
                        <?php echo formatDate($current_license["issue_date"]); ?>
                    </div>
                    <div class="mb-3">
                        <strong>تاريخ الانتهاء:</strong><br>
                        <?php echo formatDate($current_license["expiry_date"]); ?>
                    </div>
                    <div class="mb-3">
                        <strong>الحالة:</strong><br>
                        <?php if ($current_license["status"] === "active"): ?>
                            <span class="badge bg-success">نشط</span>
                        <?php else: ?>
                            <span class="badge bg-danger">منتهي</span>
                        <?php endif; ?>
                    </div>
                    <a href="license.php" class="btn btn-outline-primary btn-sm">عرض التفاصيل</a>
                <?php else: ?>
                    <p class="text-muted">لا يوجد ترخيص نشط حالياً.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- آخر الرسائل -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">آخر الرسائل</h5>
                <a href="messages.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_messages)): ?>
                    <p class="text-muted text-center">لا توجد رسائل حتى الآن.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>المرسل</th>
                                    <th>الموضوع</th>
                                    <th>تاريخ الإرسال</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_messages as $message): ?>
                                <tr>
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
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// تضمين تذييل الصفحة
include "../templates/footer.php";
?>

