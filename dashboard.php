<?php
/**
 * لوحة التحكم الرئيسية
 * Main Dashboard Page
 */

// تعريف الوصول للنظام
define("SYSTEM_ACCESS", true);

// تضمين الملفات المطلوبة
require_once "config/config.php";
require_once "config/database.php";
require_once "includes/helpers.php";
require_once "includes/session.php";
require_once "includes/auth.php";
require_once "includes/user_manager.php";
require_once "includes/license_manager.php";
require_once "includes/page_guard.php";

// حماية الصفحة: تتطلب تسجيل الدخول
requireLogin();

// إعدادات الصفحة
$pageTitle = "لوحة التحكم";
$pageDescription = "نظرة عامة على النظام والأنشطة الرئيسية.";

// بيانات لوحة التحكم (مثال)
$currentUser = getCurrentUser();
$totalUsers = $userManager->countUsers();
$activeLicenses = $licenseManager->countLicenses(["status" => "active"]);
$expiringLicenses = $licenseManager->countLicenses(["expiring_soon" => true]);

// تضمين رأس الصفحة
include "templates/header.php";
?>

<div class="row">
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-white bg-primary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title text-white">إجمالي المستخدمين</h5>
                        <h1 class="display-4 text-white"><?php echo $totalUsers; ?></h1>
                    </div>
                    <i class="fas fa-users fa-4x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer bg-primary-dark border-0">
                <a href="admin/users.php" class="text-white text-decoration-none d-flex justify-content-between align-items-center">
                    <span>عرض التفاصيل</span>
                    <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-white bg-success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title text-white">تراخيص نشطة</h5>
                        <h1 class="display-4 text-white"><?php echo $activeLicenses; ?></h1>
                    </div>
                    <i class="fas fa-certificate fa-4x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer bg-success-dark border-0">
                <a href="admin/licenses.php" class="text-white text-decoration-none d-flex justify-content-between align-items-center">
                    <span>عرض التفاصيل</span>
                    <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-white bg-warning h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title text-white">تراخيص على وشك الانتهاء</h5>
                        <h1 class="display-4 text-white"><?php echo $expiringLicenses; ?></h1>
                    </div>
                    <i class="fas fa-exclamation-triangle fa-4x opacity-50"></i>
                </div>
            </div>
            <div class="card-footer bg-warning-dark border-0">
                <a href="admin/licenses.php?status=expiring_soon" class="text-white text-decoration-none d-flex justify-content-between align-items-center">
                    <span>عرض التفاصيل</span>
                    <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">آخر الأنشطة</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php 
                    // مثال على جلب آخر 5 أنشطة
                    $latestActivities = $database->select("SELECT * FROM log_access ORDER BY timestamp DESC LIMIT 5");
                    if (!empty($latestActivities)):
                        foreach ($latestActivities as $activity):
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-info-circle me-2 text-info"></i>
                                <?php echo htmlspecialchars($activity["details"]); ?>
                            </div>
                            <small class="text-muted"><?php echo formatTimeAgo($activity["timestamp"]); ?></small>
                        </li>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <li class="list-group-item text-center text-muted">لا توجد أنشطة حديثة.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">إحصائيات النظام</h5>
            </div>
            <div class="card-body">
                <canvas id="systemStatsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
// JavaScript مخصص للصفحة
$pageJS = 
'// بيانات الرسم البياني (مثال)
const systemStatsData = {
    labels: ["مستخدمون", "تراخيص نشطة", "تراخيص منتهية"],
    datasets: [{
        label: "العدد",
        data: [' . $totalUsers . ', ' . $activeLicenses . ', ' . $expiringLicenses . '],
        backgroundColor: [
            "rgba(44, 90, 160, 0.7)",
            "rgba(39, 174, 96, 0.7)",
            "rgba(231, 76, 60, 0.7)"
        ],
        borderColor: [
            "rgba(44, 90, 160, 1)",
            "rgba(39, 174, 96, 1)",
            "rgba(231, 76, 60, 1)"
        ],
        borderWidth: 1
    }]
};

// إعدادات الرسم البياني
const systemStatsOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0
            }
        }
    },
    plugins: {
        legend: {
            display: false
        },
        title: {
            display: true,
            text: "إحصائيات النظام العامة"
        }
    }
};

// إنشاء الرسم البياني
const systemStatsChartCtx = document.getElementById("systemStatsChart").getContext("2d");
new Chart(systemStatsChartCtx, {
    type: "bar",
    data: systemStatsData,
    options: systemStatsOptions
});
';

// تضمين ملف Chart.js
$additionalJS = ["https://cdn.jsdelivr.net/npm/chart.js"];

// تضمين تذييل الصفحة
include "templates/footer.php";
?>

