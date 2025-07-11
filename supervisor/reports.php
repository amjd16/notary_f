<?php
/**
 * صفحة التقارير الإشرافية
 * Supervisory Reports Page for Supervisor
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
$pageTitle = "التقارير الإشرافية";
$pageDescription = "عرض التقارير الإشرافية الخاصة بالمديرية.";
$breadcrumbs = [
    ["title" => "لوحة التحكم"],
    ["title" => "التقارير الإشرافية"]
];

// جلب معرف المديرية للمستخدم الحالي
$user_district_id = getCurrentUser()["district_id"];

// جلب الإحصائيات
$stats = [
    "total_notaries" => $database->count("SELECT COUNT(*) FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = ?) AND district_id = ?", ["Notary", $user_district_id]),
    "total_transactions" => $database->count("SELECT COUNT(*) FROM transactions t JOIN users u ON t.notary_id = u.user_id WHERE u.district_id = ?", [$user_district_id]),
    "pending_transactions" => $database->count("SELECT COUNT(*) FROM transactions t JOIN users u ON t.notary_id = u.user_id WHERE u.district_id = ? AND t.status = 'pending'", [$user_district_id]),
    "completed_transactions" => $database->count("SELECT COUNT(*) FROM transactions t JOIN users u ON t.notary_id = u.user_id WHERE u.district_id = ? AND t.status = 'completed'", [$user_district_id]),
    "active_licenses" => $database->count("SELECT COUNT(*) FROM licenses l JOIN users u ON l.notary_id = u.user_id WHERE u.district_id = ? AND l.status = 'active'", [$user_district_id]),
    "expired_licenses" => $database->count("SELECT COUNT(*) FROM licenses l JOIN users u ON l.notary_id = u.user_id WHERE u.district_id = ? AND l.status = 'expired'", [$user_district_id]),
];

// جلب بيانات الرسوم البيانية (مثال: المعاملات حسب الأمين)
$transactionsByNotary = $database->select("SELECT u.full_name as notary_name, COUNT(t.transaction_id) as count 
                                           FROM transactions t 
                                           JOIN users u ON t.notary_id = u.user_id 
                                           WHERE u.district_id = ? 
                                           GROUP BY u.full_name 
                                           ORDER BY count DESC LIMIT 10", [$user_district_id]);

// جلب بيانات الرسوم البيانية (مثال: المعاملات حسب النوع)
$transactionsByType = $database->select("SELECT transaction_type, COUNT(transaction_id) as count 
                                         FROM transactions t 
                                         JOIN users u ON t.notary_id = u.user_id 
                                         WHERE u.district_id = ? 
                                         GROUP BY transaction_type 
                                         ORDER BY count DESC", [$user_district_id]);

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
                        <h6 class="card-title text-info">المعاملات المكتملة</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $stats["completed_transactions"]; ?></h3>
                    </div>
                    <i class="fas fa-check-circle fa-2x text-info"></i>
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
</div>

<div class="row">
    <!-- الرسم البياني للمعاملات حسب الأمين -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">المعاملات حسب الأمين (أعلى 10)</h5>
            </div>
            <div class="card-body">
                <canvas id="transactionsByNotaryChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- الرسم البياني للمعاملات حسب النوع -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">المعاملات حسب النوع</h5>
            </div>
            <div class="card-body">
                <canvas id="transactionsByTypeChart"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
// JavaScript مخصص للصفحة
$pageJS = 
"// بيانات الرسوم البيانية
const transactionsByNotaryData = {
    labels: [" . implode(",", array_map(function($item) { return 
                                        "\"" . $item["notary_name"] . "\""; }, $transactionsByNotary)) . "],
    datasets: [{
        label: \"عدد المعاملات\",
        data: [" . implode(",", array_map(function($item) { return $item["count"]; }, $transactionsByNotary)) . "],
        backgroundColor: "rgba(44, 90, 160, 0.7)",
        borderColor: "rgba(44, 90, 160, 1)",
        borderWidth: 1
    }]
};

const transactionsByTypeData = {
    labels: [" . implode(",", array_map(function($item) { return 
                                        "\"" . $item["transaction_type"] . "\""; }, $transactionsByType)) . "],
    datasets: [{
        label: \"عدد المعاملات\",
        data: [" . implode(",", array_map(function($item) { return $item["count"]; }, $transactionsByType)) . "],
        backgroundColor: [
            "rgba(44, 90, 160, 0.7)",
            "rgba(243, 156, 18, 0.7)",
            "rgba(39, 174, 96, 0.7)",
            "rgba(231, 76, 60, 0.7)",
            "rgba(142, 68, 173, 0.7)"
        ],
        borderColor: [
            "rgba(44, 90, 160, 1)",
            "rgba(243, 156, 18, 1)",
            "rgba(39, 174, 96, 1)",
            "rgba(231, 76, 60, 1)",
            "rgba(142, 68, 173, 1)"
        ],
        borderWidth: 1
    }]
};

// إعدادات الرسوم البيانية
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            beginAtZero: true
        }
    }
};

// إنشاء الرسوم البيانية
const transactionsByNotaryChart = new Chart(document.getElementById("transactionsByNotaryChart"), {
    type: "bar",
    data: transactionsByNotaryData,
    options: chartOptions
});

const transactionsByTypeChart = new Chart(document.getElementById("transactionsByTypeChart"), {
    type: "doughnut",
    data: transactionsByTypeData,
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
";

// ملفات JavaScript إضافية (لـ Chart.js)
$additionalJS = [
    "https://cdn.jsdelivr.net/npm/chart.js"
];

// تضمين تذييل الصفحة
include "../templates/footer.php";
?>

