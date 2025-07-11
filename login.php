<?php
/**
 * صفحة تسجيل الدخول
 * Login Page
 */

// تعريف الوصول للنظام
define('SYSTEM_ACCESS', true);

// تضمين الملفات المطلوبة
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/helpers.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';

// إعادة توجيه المستخدم المسجل دخوله
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// معالجة طلب تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/login_handler.php';
    exit;
}

// إعدادات الصفحة
$pageTitle = 'تسجيل الدخول';
$bodyClass = 'login-page';
$hideNavbar = true;
$hideSidebar = true;
$hideFooter = true;

// CSS مخصص للصفحة
$pageCSS = '
.login-page {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-container {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    max-width: 900px;
    width: 100%;
    margin: 20px;
}

.login-header {
    background: linear-gradient(45deg, var(--primary-color), var(--primary-light));
    color: white;
    padding: 2rem;
    text-align: center;
}

.login-logo {
    width: 80px;
    height: 80px;
    margin: 0 auto 1rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.login-form {
    padding: 2rem;
}

.form-floating {
    position: relative;
    margin-bottom: 1.5rem;
}

.form-floating input {
    height: 60px;
    padding: 1rem 0.75rem;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-floating input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(44, 90, 160, 0.25);
}

.form-floating label {
    padding: 1rem 0.75rem;
    color: #6c757d;
    transition: all 0.3s ease;
}

.btn-login {
    height: 60px;
    font-size: 1.1rem;
    font-weight: 600;
    border-radius: 10px;
    background: linear-gradient(45deg, var(--primary-color), var(--primary-light));
    border: none;
    transition: all 0.3s ease;
}

.btn-login:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(44, 90, 160, 0.3);
}

.login-features {
    background: #f8f9fa;
    padding: 2rem;
    border-right: 1px solid #e9ecef;
}

.feature-item {
    display: flex;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease;
}

.feature-item:hover {
    transform: translateX(-5px);
}

.feature-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(45deg, var(--secondary-color), var(--secondary-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin-left: 1rem;
    font-size: 1.2rem;
}

.forgot-password {
    text-align: center;
    margin-top: 1.5rem;
}

.system-info {
    background: rgba(255, 255, 255, 0.1);
    padding: 1rem;
    margin-top: 2rem;
    border-radius: 10px;
    text-align: center;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .login-container {
        margin: 10px;
        border-radius: 15px;
    }
    
    .login-features {
        display: none;
    }
    
    .login-form {
        padding: 1.5rem;
    }
    
    .login-header {
        padding: 1.5rem;
    }
}

.loading-spinner {
    display: none;
    width: 20px;
    height: 20px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
    margin-left: 10px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
';

// JavaScript مخصص للصفحة
$pageJS = '
// معالجة نموذج تسجيل الدخول
document.getElementById("loginForm").addEventListener("submit", function(e) {
    e.preventDefault();
    
    const form = this;
    const submitBtn = form.querySelector("[type=\"submit\"]");
    const spinner = submitBtn.querySelector(".loading-spinner");
    const btnText = submitBtn.querySelector(".btn-text");
    
    // إظهار مؤشر التحميل
    submitBtn.disabled = true;
    spinner.style.display = "inline-block";
    btnText.textContent = "جاري تسجيل الدخول...";
    
    // إرسال البيانات
    const formData = new FormData(form);
    formData.append("action", "login");
    
    fetch("includes/login_handler.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // نجح تسجيل الدخول
            btnText.textContent = "تم بنجاح!";
            
            // إعادة التوجيه
            setTimeout(() => {
                window.location.href = data.redirect || "dashboard.php";
            }, 1000);
        } else {
            // فشل تسجيل الدخول
            showError(data.message);
            resetButton();
        }
    })
    .catch(error => {
        console.error("خطأ:", error);
        showError("حدث خطأ أثناء تسجيل الدخول");
        resetButton();
    });
    
    function resetButton() {
        submitBtn.disabled = false;
        spinner.style.display = "none";
        btnText.textContent = "تسجيل الدخول";
    }
    
    function showError(message) {
        const errorDiv = document.getElementById("errorMessage");
        errorDiv.textContent = message;
        errorDiv.style.display = "block";
        
        // إخفاء الرسالة بعد 5 ثوان
        setTimeout(() => {
            errorDiv.style.display = "none";
        }, 5000);
    }
});

// معالجة نموذج إعادة تعيين كلمة المرور
document.getElementById("resetForm").addEventListener("submit", function(e) {
    e.preventDefault();
    
    const form = this;
    const submitBtn = form.querySelector("[type=\"submit\"]");
    const email = form.querySelector("[name=\"email\"]").value;
    
    if (!email) {
        alert("يرجى إدخال البريد الإلكتروني");
        return;
    }
    
    submitBtn.disabled = true;
    submitBtn.textContent = "جاري الإرسال...";
    
    const formData = new FormData(form);
    formData.append("action", "reset_password");
    
    fetch("includes/login_handler.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        
        if (data.success) {
            // إغلاق النافذة المنبثقة
            const modal = bootstrap.Modal.getInstance(document.getElementById("resetModal"));
            modal.hide();
            form.reset();
        }
        
        submitBtn.disabled = false;
        submitBtn.textContent = "إرسال رابط إعادة التعيين";
    })
    .catch(error => {
        console.error("خطأ:", error);
        alert("حدث خطأ أثناء إرسال الطلب");
        
        submitBtn.disabled = false;
        submitBtn.textContent = "إرسال رابط إعادة التعيين";
    });
});

// تأثيرات بصرية
document.addEventListener("DOMContentLoaded", function() {
    // تحريك العناصر عند التحميل
    const container = document.querySelector(".login-container");
    container.style.opacity = "0";
    container.style.transform = "translateY(50px)";
    
    setTimeout(() => {
        container.style.transition = "all 0.6s ease";
        container.style.opacity = "1";
        container.style.transform = "translateY(0)";
    }, 100);
    
    // تحريك العناصر الفرعية
    const features = document.querySelectorAll(".feature-item");
    features.forEach((feature, index) => {
        feature.style.opacity = "0";
        feature.style.transform = "translateX(30px)";
        
        setTimeout(() => {
            feature.style.transition = "all 0.5s ease";
            feature.style.opacity = "1";
            feature.style.transform = "translateX(0)";
        }, 200 + (index * 100));
    });
});
';
?>

<?php include 'templates/header.php'; ?>

<div class="login-container">
    <div class="row g-0">
        <!-- قسم المميزات -->
        <div class="col-lg-5 login-features d-none d-lg-block">
            <div class="h-100 d-flex flex-column justify-content-center">
                <h3 class="text-center mb-4 text-primary">مميزات النظام</h3>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">أمان عالي</h5>
                        <p class="mb-0 text-muted">حماية متقدمة للبيانات والمعلومات الحساسة</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">إدارة العقود</h5>
                        <p class="mb-0 text-muted">نظام شامل لإدارة وتوثيق العقود الشرعية</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">تقارير تفصيلية</h5>
                        <p class="mb-0 text-muted">إحصائيات وتقارير شاملة لجميع العمليات</p>
                    </div>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">متوافق مع الجوال</h5>
                        <p class="mb-0 text-muted">يعمل بكفاءة على جميع الأجهزة والشاشات</p>
                    </div>
                </div>
                
                <div class="system-info">
                    <i class="fas fa-info-circle me-2"></i>
                    نظام إدارة الأمناء الشرعيين - وزارة العدل اليمنية
                </div>
            </div>
        </div>
        
        <!-- قسم تسجيل الدخول -->
        <div class="col-lg-7">
            <!-- رأس النموذج -->
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <h2 class="mb-2">مرحباً بك</h2>
                <p class="mb-0">نظام إدارة الأمناء الشرعيين</p>
            </div>
            
            <!-- نموذج تسجيل الدخول -->
            <div class="login-form">
                <!-- رسالة الخطأ -->
                <div id="errorMessage" class="alert alert-danger" style="display: none;" role="alert"></div>
                
                <form id="loginForm" method="POST">
                    <?php echo generateCsrfField(); ?>
                    
                    <div class="form-floating">
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="اسم المستخدم" required autocomplete="username">
                        <label for="username">
                            <i class="fas fa-user me-2"></i>اسم المستخدم
                        </label>
                    </div>
                    
                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="كلمة المرور" required autocomplete="current-password">
                        <label for="password">
                            <i class="fas fa-lock me-2"></i>كلمة المرور
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                        <label class="form-check-label" for="remember_me">
                            تذكرني لمدة 30 يوماً
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-login btn-primary w-100">
                        <span class="btn-text">تسجيل الدخول</span>
                        <span class="loading-spinner"></span>
                    </button>
                </form>
                
                <!-- روابط إضافية -->
                <div class="forgot-password">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#resetModal" class="text-decoration-none">
                        نسيت كلمة المرور؟
                    </a>
                </div>
                
                <!-- معلومات الدعم -->
                <div class="text-center mt-4 pt-3 border-top">
                    <small class="text-muted">
                        للدعم الفني: 
                        <a href="tel:+967-1-123456" class="text-decoration-none">967-1-123456</a>
                        أو 
                        <a href="mailto:support@moj.gov.ye" class="text-decoration-none">support@moj.gov.ye</a>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- نافذة إعادة تعيين كلمة المرور -->
<div class="modal fade" id="resetModal" tabindex="-1" aria-labelledby="resetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetModalLabel">إعادة تعيين كلمة المرور</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">أدخل بريدك الإلكتروني وسنرسل لك رابط إعادة تعيين كلمة المرور.</p>
                
                <form id="resetForm">
                    <?php echo generateCsrfField(); ?>
                    
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="reset_email" name="email" 
                               placeholder="البريد الإلكتروني" required>
                        <label for="reset_email">
                            <i class="fas fa-envelope me-2"></i>البريد الإلكتروني
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        إرسال رابط إعادة التعيين
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'templates/footer.php'; ?>

