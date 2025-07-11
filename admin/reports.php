<?php
/**
 * صفحة التقارير والإحصائيات
 * Reports and Statistics Page
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
$pageTitle = "التقارير والإحصائيات";
$pageDescription = "عرض التقارير والإحصائيات الشاملة للنظام.";
$breadcrumbs = [
    ["title" => "إدارة النظام"],
    ["title" => "التقارير والإحصائيات"]
];

// جلب الإحصائيات
$stats = [
    "total_users" => $database->count("SELECT COUNT(*) FROM users"),
    "total_notaries" => $database->count("SELECT COUNT(*) FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = ?)", ["Notary"]),
    "total_supervisors" => $database->count("SELECT COUNT(*) FROM users WHERE role_id = (SELECT role_id FROM roles WHERE role_name = ?)", ["Supervisor"]),
    "total_transactions" => $database->count("SELECT COUNT(*) FROM transactions"),
    "active_licenses" => $database->count("SELECT COUNT(*) FROM licenses WHERE status = ?", ["active"]),
    "expired_licenses" => $database->count("SELECT COUNT(*) FROM licenses WHERE status = ?", ["expired"]),
];

// جلب بيانات الرسوم البيانية
$transactionsByMonth = $database->select("SELECT DATE_FORMAT(transaction_date, 
                                                    \"%Y-%m\") as month, 
                                                    COUNT(*) as count 
                                             FROM transactions 
                                             GROUP BY month 
                                             ORDER BY month ASC");

$usersByRole = $database->select("SELECT r.role_name, COUNT(u.user_id) as count 
                                   FROM users u 
                                   JOIN roles r ON u.role_id = r.role_id 
                                   GROUP BY r.role_name");

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
                        <h6 class="card-title text-primary">إجمالي المستخدمين</h6>
                        <h3 class="mb-0 fw-bold"><?php echo $stats["total_users"]; ?></h3>
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
    <!-- الرسم البياني للمعاملات الشهرية -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">المعاملات الشهرية</h5>
            </div>
            <div class="card-body">
                <canvas id="transactionsChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- الرسم البياني للمستخدمين حسب الدور -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">المستخدمون حسب الدور</h5>
            </div>
            <div class="card-body">
                <canvas id="usersByRoleChart"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
// JavaScript مخصص للصفحة
$pageJS = 
"// بيانات الرسوم البيانية
const transactionsData = {
    labels: [" . implode(",", array_map(function($item) { return 
                                        "\"" . $item["month"] . "\""; }, $transactionsByMonth)) . "],
    datasets: [{
        label: \"عدد المعاملات\",
        data: [" . implode(",", array_map(function($item) { return $item["count"]; }, $transactionsByMonth)) . "],
        backgroundColor: "rgba(44, 90, 160, 0.2)",
        borderColor: "rgba(44, 90, 160, 1)",
        borderWidth: 1,
        fill: true
    }]
};

const usersByRoleData = {
    labels: [" . implode(",", array_map(function($item) { return 
                                        "\"" . $item["role_name"] . "\""; }, $usersByRole)) . "],
    datasets: [{
        label: \"عدد المستخدمين\",
        data: [" . implode(",", array_map(function($item) { return $item["count"]; }, $usersByRole)) . "],
        backgroundColor: [
            "rgba(44, 90, 160, 0.7)",
            "rgba(243, 156, 18, 0.7)",
            "rgba(39, 174, 96, 0.7)",
            "rgba(231, 76, 60, 0.7)"
        ],
        borderColor: [
            "rgba(44, 90, 160, 1)",
            "rgba(243, 156, 18, 1)",
            "rgba(39, 174, 96, 1)",
            "rgba(231, 76, 60, 1)"
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
const transactionsChart = new Chart(document.getElementById("transactionsChart"), {
    type: "line",
    data: transactionsData,
    options: chartOptions
});

const usersByRoleChart = new Chart(document.getElementById("usersByRoleChart"), {
    type: "doughnut",
    data: usersByRoleData,
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

