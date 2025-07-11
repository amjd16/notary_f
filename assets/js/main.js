/**
 * ملف JavaScript الرئيسي لنظام إدارة الأمناء الشرعيين
 * Main JavaScript file for Notary Management System
 * 
 * يحتوي على الوظائف العامة والتفاعلات الأساسية
 * Contains general functions and basic interactions
 */

// المتغيرات العامة
const NotarySystem = {
    // إعدادات النظام
    config: {
        sessionTimeout: 30 * 60 * 1000, // 30 دقيقة
        sessionWarning: 5 * 60 * 1000,  // 5 دقائق قبل انتهاء الجلسة
        ajaxTimeout: 30000,             // 30 ثانية
        maxFileSize: 5 * 1024 * 1024,   // 5 ميجابايت
        allowedFileTypes: ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
        dateFormat: 'YYYY-MM-DD',
        timeFormat: 'HH:mm:ss'
    },
    
    // حالة النظام
    state: {
        isLoggedIn: false,
        currentUser: null,
        sessionTimer: null,
        warningTimer: null,
        notifications: [],
        activeModals: []
    },
    
    // عناصر DOM المهمة
    elements: {
        body: document.body,
        sidebar: null,
        navbar: null,
        mainContent: null,
        loadingOverlay: null
    }
};

// تهيئة النظام عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    NotarySystem.init();
});

// تهيئة النظام
NotarySystem.init = function() {
    console.log('تهيئة نظام إدارة الأمناء الشرعيين...');
    
    // تهيئة العناصر
    this.initElements();
    
    // تهيئة الأحداث
    this.initEvents();
    
    // تهيئة الجلسة
    this.initSession();
    
    // تهيئة الإشعارات
    this.initNotifications();
    
    // تهيئة النماذج
    this.initForms();
    
    // تهيئة الجداول
    this.initTables();
    
    // تهيئة التحميل التلقائي
    this.initAutoLoad();
    
    console.log('تم تهيئة النظام بنجاح');
};

// تهيئة العناصر
NotarySystem.initElements = function() {
    this.elements.sidebar = document.querySelector('.sidebar');
    this.elements.navbar = document.querySelector('.navbar');
    this.elements.mainContent = document.querySelector('.main-content');
    
    // إنشاء طبقة التحميل
    this.createLoadingOverlay();
};

// تهيئة الأحداث
NotarySystem.initEvents = function() {
    // أحداث الشريط الجانبي
    this.initSidebarEvents();
    
    // أحداث الأزرار
    this.initButtonEvents();
    
    // أحداث النوافذ المنبثقة
    this.initModalEvents();
    
    // أحداث لوحة المفاتيح
    this.initKeyboardEvents();
    
    // أحداث النافذة
    this.initWindowEvents();
};

// تهيئة أحداث الشريط الجانبي
NotarySystem.initSidebarEvents = function() {
    const sidebarToggle = document.querySelector('[data-toggle="sidebar"]');
    const sidebarClose = document.querySelector('[data-close="sidebar"]');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleSidebar();
        });
    }
    
    if (sidebarClose) {
        sidebarClose.addEventListener('click', (e) => {
            e.preventDefault();
            this.closeSidebar();
        });
    }
    
    // إغلاق الشريط الجانبي عند النقر خارجه
    document.addEventListener('click', (e) => {
        if (this.elements.sidebar && this.elements.sidebar.classList.contains('show')) {
            if (!this.elements.sidebar.contains(e.target) && !e.target.closest('[data-toggle="sidebar"]')) {
                this.closeSidebar();
            }
        }
    });
};

// تبديل الشريط الجانبي
NotarySystem.toggleSidebar = function() {
    if (this.elements.sidebar) {
        this.elements.sidebar.classList.toggle('show');
        if (this.elements.mainContent) {
            this.elements.mainContent.classList.toggle('sidebar-open');
        }
    }
};

// إغلاق الشريط الجانبي
NotarySystem.closeSidebar = function() {
    if (this.elements.sidebar) {
        this.elements.sidebar.classList.remove('show');
        if (this.elements.mainContent) {
            this.elements.mainContent.classList.remove('sidebar-open');
        }
    }
};

// تهيئة أحداث الأزرار
NotarySystem.initButtonEvents = function() {
    // أزرار التأكيد
    document.addEventListener('click', (e) => {
        if (e.target.matches('[data-confirm]')) {
            e.preventDefault();
            const message = e.target.getAttribute('data-confirm');
            this.confirmAction(message, () => {
                if (e.target.href) {
                    window.location.href = e.target.href;
                } else if (e.target.form) {
                    e.target.form.submit();
                }
            });
        }
    });
    
    // أزرار التحميل
    document.addEventListener('click', (e) => {
        if (e.target.matches('[data-loading]')) {
            this.showButtonLoading(e.target);
        }
    });
    
    // أزرار النسخ
    document.addEventListener('click', (e) => {
        if (e.target.matches('[data-copy]')) {
            e.preventDefault();
            const text = e.target.getAttribute('data-copy');
            this.copyToClipboard(text);
        }
    });
};

// تهيئة أحداث النوافذ المنبثقة
NotarySystem.initModalEvents = function() {
    // فتح النوافذ المنبثقة
    document.addEventListener('click', (e) => {
        if (e.target.matches('[data-modal]')) {
            e.preventDefault();
            const modalId = e.target.getAttribute('data-modal');
            this.openModal(modalId);
        }
    });
    
    // إغلاق النوافذ المنبثقة
    document.addEventListener('click', (e) => {
        if (e.target.matches('[data-dismiss="modal"]')) {
            e.preventDefault();
            this.closeModal(e.target.closest('.modal'));
        }
        
        // إغلاق عند النقر على الخلفية
        if (e.target.classList.contains('modal')) {
            this.closeModal(e.target);
        }
    });
    
    // إغلاق بمفتاح Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.state.activeModals.length > 0) {
            this.closeModal(this.state.activeModals[this.state.activeModals.length - 1]);
        }
    });
};

// تهيئة أحداث لوحة المفاتيح
NotarySystem.initKeyboardEvents = function() {
    document.addEventListener('keydown', (e) => {
        // Ctrl+S للحفظ
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            const saveButton = document.querySelector('[data-action="save"]');
            if (saveButton) {
                saveButton.click();
            }
        }
        
        // Ctrl+Enter لإرسال النماذج
        if (e.ctrlKey && e.key === 'Enter') {
            const activeElement = document.activeElement;
            if (activeElement && activeElement.form) {
                const submitButton = activeElement.form.querySelector('[type="submit"]');
                if (submitButton) {
                    submitButton.click();
                }
            }
        }
    });
};

// تهيئة أحداث النافذة
NotarySystem.initWindowEvents = function() {
    // تحذير عند مغادرة الصفحة مع تغييرات غير محفوظة
    window.addEventListener('beforeunload', (e) => {
        const unsavedForms = document.querySelectorAll('form[data-unsaved="true"]');
        if (unsavedForms.length > 0) {
            e.preventDefault();
            e.returnValue = 'لديك تغييرات غير محفوظة. هل تريد المغادرة؟';
            return e.returnValue;
        }
    });
    
    // تحديث حجم النافذة
    window.addEventListener('resize', () => {
        this.handleResize();
    });
};

// تهيئة الجلسة
NotarySystem.initSession = function() {
    // التحقق من حالة الجلسة
    this.checkSessionStatus();
    
    // بدء مؤقت الجلسة
    this.startSessionTimer();
    
    // تحديث آخر نشاط
    this.updateLastActivity();
    
    // مراقبة النشاط
    this.monitorActivity();
};

// التحقق من حالة الجلسة
NotarySystem.checkSessionStatus = function() {
    this.ajax({
        url: 'includes/login_handler.php',
        method: 'GET',
        data: { action: 'session_status' },
        success: (response) => {
            if (response.logged_in) {
                this.state.isLoggedIn = true;
                this.state.currentUser = response.user;
                
                if (response.about_to_expire) {
                    this.showSessionWarning();
                }
            } else {
                this.state.isLoggedIn = false;
                this.redirectToLogin();
            }
        },
        error: () => {
            console.error('فشل في التحقق من حالة الجلسة');
        }
    });
};

// بدء مؤقت الجلسة
NotarySystem.startSessionTimer = function() {
    // مسح المؤقتات السابقة
    if (this.state.sessionTimer) {
        clearTimeout(this.state.sessionTimer);
    }
    if (this.state.warningTimer) {
        clearTimeout(this.state.warningTimer);
    }
    
    // مؤقت التحذير
    this.state.warningTimer = setTimeout(() => {
        this.showSessionWarning();
    }, this.config.sessionTimeout - this.config.sessionWarning);
    
    // مؤقت انتهاء الجلسة
    this.state.sessionTimer = setTimeout(() => {
        this.handleSessionExpiry();
    }, this.config.sessionTimeout);
};

// عرض تحذير انتهاء الجلسة
NotarySystem.showSessionWarning = function() {
    const remainingTime = Math.ceil(this.config.sessionWarning / 1000 / 60);
    
    this.showAlert({
        type: 'warning',
        title: 'تحذير انتهاء الجلسة',
        message: `ستنتهي جلستك خلال ${remainingTime} دقائق. هل تريد تمديد الجلسة؟`,
        buttons: [
            {
                text: 'تمديد الجلسة',
                class: 'btn-primary',
                action: () => this.extendSession()
            },
            {
                text: 'تسجيل الخروج',
                class: 'btn-secondary',
                action: () => this.logout()
            }
        ]
    });
};

// معالجة انتهاء الجلسة
NotarySystem.handleSessionExpiry = function() {
    this.showAlert({
        type: 'danger',
        title: 'انتهت الجلسة',
        message: 'انتهت صلاحية جلستك. سيتم إعادة توجيهك لصفحة تسجيل الدخول.',
        buttons: [
            {
                text: 'موافق',
                class: 'btn-primary',
                action: () => this.redirectToLogin()
            }
        ]
    });
};

// تمديد الجلسة
NotarySystem.extendSession = function() {
    this.ajax({
        url: 'includes/login_handler.php',
        method: 'POST',
        data: { action: 'extend_session' },
        success: (response) => {
            if (response.success) {
                this.startSessionTimer();
                this.showToast('تم تمديد الجلسة بنجاح', 'success');
            } else {
                this.showToast('فشل في تمديد الجلسة', 'error');
                this.redirectToLogin();
            }
        },
        error: () => {
            this.showToast('حدث خطأ أثناء تمديد الجلسة', 'error');
        }
    });
};

// تحديث آخر نشاط
NotarySystem.updateLastActivity = function() {
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
    
    events.forEach(event => {
        document.addEventListener(event, () => {
            this.lastActivity = Date.now();
        }, { passive: true });
    });
};

// مراقبة النشاط
NotarySystem.monitorActivity = function() {
    this.lastActivity = Date.now();
    
    setInterval(() => {
        const inactiveTime = Date.now() - this.lastActivity;
        
        // إذا لم يكن هناك نشاط لمدة 25 دقيقة، عرض تحذير
        if (inactiveTime > (25 * 60 * 1000) && this.state.isLoggedIn) {
            this.showSessionWarning();
        }
    }, 60000); // فحص كل دقيقة
};

// تهيئة الإشعارات
NotarySystem.initNotifications = function() {
    // إنشاء حاوية الإشعارات
    this.createNotificationContainer();
    
    // جلب الإشعارات
    this.loadNotifications();
    
    // بدء مراقبة الإشعارات الجديدة
    this.startNotificationPolling();
};

// إنشاء حاوية الإشعارات
NotarySystem.createNotificationContainer = function() {
    if (!document.querySelector('.toast-container')) {
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
};

// جلب الإشعارات
NotarySystem.loadNotifications = function() {
    if (!this.state.isLoggedIn) return;
    
    this.ajax({
        url: 'api/notifications.php',
        method: 'GET',
        success: (response) => {
            if (response.success) {
                this.state.notifications = response.notifications;
                this.updateNotificationBadge();
            }
        },
        error: () => {
            console.error('فشل في جلب الإشعارات');
        }
    });
};

// بدء مراقبة الإشعارات الجديدة
NotarySystem.startNotificationPolling = function() {
    if (!this.state.isLoggedIn) return;
    
    setInterval(() => {
        this.loadNotifications();
    }, 30000); // فحص كل 30 ثانية
};

// تحديث شارة الإشعارات
NotarySystem.updateNotificationBadge = function() {
    const badge = document.querySelector('.notification-badge');
    const unreadCount = this.state.notifications.filter(n => !n.is_read).length;
    
    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
};

// تهيئة النماذج
NotarySystem.initForms = function() {
    // تهيئة التحقق من صحة النماذج
    this.initFormValidation();
    
    // تهيئة رفع الملفات
    this.initFileUpload();
    
    // تهيئة التحديث التلقائي
    this.initAutoSave();
    
    // تهيئة التنسيق التلقائي
    this.initAutoFormat();
};

// تهيئة التحقق من صحة النماذج
NotarySystem.initFormValidation = function() {
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.hasAttribute('data-validate')) {
            e.preventDefault();
            this.validateForm(form);
        }
    });
    
    // التحقق الفوري
    document.addEventListener('blur', (e) => {
        if (e.target.matches('input, select, textarea')) {
            this.validateField(e.target);
        }
    }, true);
};

// التحقق من صحة النموذج
NotarySystem.validateForm = function(form) {
    let isValid = true;
    const fields = form.querySelectorAll('input, select, textarea');
    
    fields.forEach(field => {
        if (!this.validateField(field)) {
            isValid = false;
        }
    });
    
    if (isValid) {
        this.submitForm(form);
    } else {
        this.showToast('يرجى تصحيح الأخطاء في النموذج', 'error');
    }
};

// التحقق من صحة الحقل
NotarySystem.validateField = function(field) {
    const value = field.value.trim();
    const rules = field.getAttribute('data-rules');
    let isValid = true;
    let errorMessage = '';
    
    if (rules) {
        const ruleList = rules.split('|');
        
        for (const rule of ruleList) {
            const [ruleName, ruleValue] = rule.split(':');
            
            switch (ruleName) {
                case 'required':
                    if (!value) {
                        isValid = false;
                        errorMessage = 'هذا الحقل مطلوب';
                    }
                    break;
                    
                case 'email':
                    if (value && !this.isValidEmail(value)) {
                        isValid = false;
                        errorMessage = 'البريد الإلكتروني غير صحيح';
                    }
                    break;
                    
                case 'min':
                    if (value && value.length < parseInt(ruleValue)) {
                        isValid = false;
                        errorMessage = `يجب أن يكون الحد الأدنى ${ruleValue} أحرف`;
                    }
                    break;
                    
                case 'max':
                    if (value && value.length > parseInt(ruleValue)) {
                        isValid = false;
                        errorMessage = `يجب أن يكون الحد الأقصى ${ruleValue} أحرف`;
                    }
                    break;
                    
                case 'phone':
                    if (value && !this.isValidYemeniPhone(value)) {
                        isValid = false;
                        errorMessage = 'رقم الهاتف غير صحيح';
                    }
                    break;
                    
                case 'numeric':
                    if (value && !/^\d+$/.test(value)) {
                        isValid = false;
                        errorMessage = 'يجب أن يحتوي على أرقام فقط';
                    }
                    break;
            }
            
            if (!isValid) break;
        }
    }
    
    this.showFieldError(field, isValid ? '' : errorMessage);
    return isValid;
};

// عرض خطأ الحقل
NotarySystem.showFieldError = function(field, message) {
    // إزالة الأخطاء السابقة
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    field.classList.remove('is-invalid', 'is-valid');
    
    if (message) {
        field.classList.add('is-invalid');
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error text-danger mt-1';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    } else if (field.value.trim()) {
        field.classList.add('is-valid');
    }
};

// تهيئة رفع الملفات
NotarySystem.initFileUpload = function() {
    document.addEventListener('change', (e) => {
        if (e.target.type === 'file') {
            this.handleFileUpload(e.target);
        }
    });
    
    // السحب والإفلات
    document.addEventListener('dragover', (e) => {
        if (e.target.matches('.file-drop-zone')) {
            e.preventDefault();
            e.target.classList.add('dragover');
        }
    });
    
    document.addEventListener('dragleave', (e) => {
        if (e.target.matches('.file-drop-zone')) {
            e.target.classList.remove('dragover');
        }
    });
    
    document.addEventListener('drop', (e) => {
        if (e.target.matches('.file-drop-zone')) {
            e.preventDefault();
            e.target.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            const input = e.target.querySelector('input[type="file"]');
            if (input && files.length > 0) {
                input.files = files;
                this.handleFileUpload(input);
            }
        }
    });
};

// معالجة رفع الملفات
NotarySystem.handleFileUpload = function(input) {
    const files = input.files;
    const maxSize = this.config.maxFileSize;
    const allowedTypes = this.config.allowedFileTypes;
    
    for (const file of files) {
        // فحص الحجم
        if (file.size > maxSize) {
            this.showToast(`حجم الملف ${file.name} كبير جداً. الحد الأقصى ${this.formatFileSize(maxSize)}`, 'error');
            input.value = '';
            return;
        }
        
        // فحص النوع
        const extension = file.name.split('.').pop().toLowerCase();
        if (!allowedTypes.includes(extension)) {
            this.showToast(`نوع الملف ${extension} غير مدعوم`, 'error');
            input.value = '';
            return;
        }
    }
    
    // عرض معاينة الملفات
    this.showFilePreview(input, files);
};

// عرض معاينة الملفات
NotarySystem.showFilePreview = function(input, files) {
    const previewContainer = input.parentNode.querySelector('.file-preview');
    if (!previewContainer) return;
    
    previewContainer.innerHTML = '';
    
    for (const file of files) {
        const previewItem = document.createElement('div');
        previewItem.className = 'file-preview-item d-flex align-items-center mb-2';
        
        const icon = this.getFileIcon(file.name);
        const size = this.formatFileSize(file.size);
        
        previewItem.innerHTML = `
            <i class="${icon} me-2"></i>
            <div class="flex-grow-1">
                <div class="fw-medium">${file.name}</div>
                <small class="text-muted">${size}</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentNode.remove(); document.querySelector('#${input.id}').value = '';">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        previewContainer.appendChild(previewItem);
    }
};

// الحصول على أيقونة الملف
NotarySystem.getFileIcon = function(filename) {
    const extension = filename.split('.').pop().toLowerCase();
    
    const icons = {
        'pdf': 'fas fa-file-pdf text-danger',
        'doc': 'fas fa-file-word text-primary',
        'docx': 'fas fa-file-word text-primary',
        'jpg': 'fas fa-file-image text-success',
        'jpeg': 'fas fa-file-image text-success',
        'png': 'fas fa-file-image text-success',
        'gif': 'fas fa-file-image text-success'
    };
    
    return icons[extension] || 'fas fa-file text-secondary';
};

// تنسيق حجم الملف
NotarySystem.formatFileSize = function(bytes) {
    if (bytes === 0) return '0 بايت';
    
    const k = 1024;
    const sizes = ['بايت', 'كيلوبايت', 'ميجابايت', 'جيجابايت'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// تهيئة الجداول
NotarySystem.initTables = function() {
    // تهيئة الترتيب
    this.initTableSorting();
    
    // تهيئة البحث
    this.initTableSearch();
    
    // تهيئة التصفح
    this.initTablePagination();
    
    // تهيئة التحديد المتعدد
    this.initTableSelection();
};

// تهيئة ترتيب الجداول
NotarySystem.initTableSorting = function() {
    document.addEventListener('click', (e) => {
        if (e.target.matches('th[data-sort]')) {
            const column = e.target.getAttribute('data-sort');
            const table = e.target.closest('table');
            this.sortTable(table, column);
        }
    });
};

// ترتيب الجدول
NotarySystem.sortTable = function(table, column) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const header = table.querySelector(`th[data-sort="${column}"]`);
    
    // تحديد اتجاه الترتيب
    const currentDirection = header.getAttribute('data-direction') || 'asc';
    const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
    
    // إزالة الترتيب من الأعمدة الأخرى
    table.querySelectorAll('th[data-sort]').forEach(th => {
        th.removeAttribute('data-direction');
        th.classList.remove('sorted-asc', 'sorted-desc');
    });
    
    // تطبيق الترتيب الجديد
    header.setAttribute('data-direction', newDirection);
    header.classList.add(`sorted-${newDirection}`);
    
    // ترتيب الصفوف
    rows.sort((a, b) => {
        const aValue = a.querySelector(`td:nth-child(${this.getColumnIndex(table, column)})`).textContent.trim();
        const bValue = b.querySelector(`td:nth-child(${this.getColumnIndex(table, column)})`).textContent.trim();
        
        let comparison = 0;
        if (this.isNumeric(aValue) && this.isNumeric(bValue)) {
            comparison = parseFloat(aValue) - parseFloat(bValue);
        } else {
            comparison = aValue.localeCompare(bValue, 'ar');
        }
        
        return newDirection === 'asc' ? comparison : -comparison;
    });
    
    // إعادة ترتيب الصفوف في DOM
    rows.forEach(row => tbody.appendChild(row));
};

// الحصول على فهرس العمود
NotarySystem.getColumnIndex = function(table, column) {
    const headers = table.querySelectorAll('th');
    for (let i = 0; i < headers.length; i++) {
        if (headers[i].getAttribute('data-sort') === column) {
            return i + 1;
        }
    }
    return 1;
};

// التحقق من كون القيمة رقمية
NotarySystem.isNumeric = function(value) {
    return !isNaN(parseFloat(value)) && isFinite(value);
};

// وظائف مساعدة للتحقق من صحة البيانات
NotarySystem.isValidEmail = function(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

NotarySystem.isValidYemeniPhone = function(phone) {
    // أرقام الهاتف اليمنية تبدأ بـ 7 وتتكون من 9 أرقام
    const phoneRegex = /^7[0-9]{8}$/;
    return phoneRegex.test(phone.replace(/\s+/g, ''));
};

// وظائف AJAX
NotarySystem.ajax = function(options) {
    const defaults = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        timeout: this.config.ajaxTimeout
    };
    
    const config = Object.assign({}, defaults, options);
    
    // إضافة رمز CSRF للطلبات POST
    if (config.method === 'POST' && config.data) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            config.data.csrf_token = csrfToken.getAttribute('content');
        }
    }
    
    // تحويل البيانات إلى FormData أو JSON
    let body = null;
    if (config.data) {
        if (config.method === 'GET') {
            const params = new URLSearchParams(config.data);
            config.url += (config.url.includes('?') ? '&' : '?') + params.toString();
        } else {
            if (config.data instanceof FormData) {
                body = config.data;
                delete config.headers['Content-Type']; // دع المتصفح يحدد نوع المحتوى
            } else {
                body = JSON.stringify(config.data);
            }
        }
    }
    
    // إظهار مؤشر التحميل
    if (config.showLoading !== false) {
        this.showLoading();
    }
    
    // إرسال الطلب
    fetch(config.url, {
        method: config.method,
        headers: config.headers,
        body: body,
        signal: AbortSignal.timeout(config.timeout)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (config.showLoading !== false) {
            this.hideLoading();
        }
        
        if (config.success) {
            config.success(data);
        }
    })
    .catch(error => {
        if (config.showLoading !== false) {
            this.hideLoading();
        }
        
        console.error('AJAX Error:', error);
        
        if (config.error) {
            config.error(error);
        } else {
            this.showToast('حدث خطأ في الاتصال', 'error');
        }
    });
};

// إرسال النموذج
NotarySystem.submitForm = function(form) {
    const formData = new FormData(form);
    const action = form.getAttribute('action') || window.location.href;
    const method = form.getAttribute('method') || 'POST';
    
    // إضافة مؤشر التحميل للزر
    const submitButton = form.querySelector('[type="submit"]');
    if (submitButton) {
        this.showButtonLoading(submitButton);
    }
    
    this.ajax({
        url: action,
        method: method,
        data: formData,
        success: (response) => {
            if (submitButton) {
                this.hideButtonLoading(submitButton);
            }
            
            if (response.success) {
                this.showToast(response.message || 'تم الحفظ بنجاح', 'success');
                
                // إعادة التوجيه إذا كان مطلوباً
                if (response.redirect) {
                    setTimeout(() => {
                        window.location.href = response.redirect;
                    }, 1500);
                }
                
                // إعادة تعيين النموذج
                if (response.reset_form !== false) {
                    form.reset();
                    form.removeAttribute('data-unsaved');
                }
            } else {
                this.showToast(response.message || 'حدث خطأ', 'error');
            }
        },
        error: () => {
            if (submitButton) {
                this.hideButtonLoading(submitButton);
            }
            this.showToast('حدث خطأ أثناء الإرسال', 'error');
        }
    });
};

// عرض مؤشر التحميل
NotarySystem.showLoading = function() {
    if (!this.elements.loadingOverlay) {
        this.createLoadingOverlay();
    }
    this.elements.loadingOverlay.style.display = 'flex';
};

// إخفاء مؤشر التحميل
NotarySystem.hideLoading = function() {
    if (this.elements.loadingOverlay) {
        this.elements.loadingOverlay.style.display = 'none';
    }
};

// إنشاء طبقة التحميل
NotarySystem.createLoadingOverlay = function() {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    `;
    
    overlay.innerHTML = `
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">جاري التحميل...</span>
        </div>
    `;
    
    document.body.appendChild(overlay);
    this.elements.loadingOverlay = overlay;
};

// عرض مؤشر التحميل للزر
NotarySystem.showButtonLoading = function(button) {
    if (button.hasAttribute('data-loading-text')) {
        button.setAttribute('data-original-text', button.textContent);
        button.textContent = button.getAttribute('data-loading-text');
    }
    
    button.disabled = true;
    button.classList.add('loading');
};

// إخفاء مؤشر التحميل للزر
NotarySystem.hideButtonLoading = function(button) {
    if (button.hasAttribute('data-original-text')) {
        button.textContent = button.getAttribute('data-original-text');
        button.removeAttribute('data-original-text');
    }
    
    button.disabled = false;
    button.classList.remove('loading');
};

// عرض رسالة منبثقة
NotarySystem.showToast = function(message, type = 'info', duration = 5000) {
    const container = document.querySelector('.toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    container.appendChild(toast);
    
    // إظهار الرسالة
    toast.classList.add('show');
    
    // إخفاء الرسالة تلقائياً
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, duration);
    
    // إضافة حدث الإغلاق اليدوي
    const closeButton = toast.querySelector('[data-bs-dismiss="toast"]');
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        });
    }
};

// عرض تأكيد
NotarySystem.confirmAction = function(message, callback) {
    this.showAlert({
        type: 'warning',
        title: 'تأكيد العملية',
        message: message,
        buttons: [
            {
                text: 'تأكيد',
                class: 'btn-primary',
                action: callback
            },
            {
                text: 'إلغاء',
                class: 'btn-secondary',
                action: () => {}
            }
        ]
    });
};

// عرض تنبيه مخصص
NotarySystem.showAlert = function(options) {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.setAttribute('tabindex', '-1');
    
    const typeClass = options.type ? `alert-${options.type}` : '';
    
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header ${typeClass}">
                    <h5 class="modal-title">${options.title || 'تنبيه'}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>${options.message}</p>
                </div>
                <div class="modal-footer">
                    ${options.buttons ? options.buttons.map(btn => 
                        `<button type="button" class="btn ${btn.class}" data-action="${btn.text}">${btn.text}</button>`
                    ).join('') : '<button type="button" class="btn btn-primary" data-bs-dismiss="modal">موافق</button>'}
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // إضافة أحداث الأزرار
    if (options.buttons) {
        options.buttons.forEach(btn => {
            const button = modal.querySelector(`[data-action="${btn.text}"]`);
            if (button) {
                button.addEventListener('click', () => {
                    this.closeModal(modal);
                    if (btn.action) {
                        btn.action();
                    }
                });
            }
        });
    }
    
    this.openModal(modal);
};

// فتح نافذة منبثقة
NotarySystem.openModal = function(modal) {
    if (typeof modal === 'string') {
        modal = document.querySelector(modal);
    }
    
    if (modal) {
        modal.style.display = 'block';
        modal.classList.add('show');
        document.body.classList.add('modal-open');
        
        this.state.activeModals.push(modal);
        
        // التركيز على أول عنصر قابل للتفاعل
        const focusable = modal.querySelector('input, button, select, textarea, [tabindex]');
        if (focusable) {
            focusable.focus();
        }
    }
};

// إغلاق نافذة منبثقة
NotarySystem.closeModal = function(modal) {
    if (typeof modal === 'string') {
        modal = document.querySelector(modal);
    }
    
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
        
        // إزالة من قائمة النوافذ النشطة
        const index = this.state.activeModals.indexOf(modal);
        if (index > -1) {
            this.state.activeModals.splice(index, 1);
        }
        
        // إزالة فئة modal-open إذا لم تعد هناك نوافذ مفتوحة
        if (this.state.activeModals.length === 0) {
            document.body.classList.remove('modal-open');
        }
        
        // حذف النافذة إذا كانت مؤقتة
        if (modal.hasAttribute('data-temporary')) {
            setTimeout(() => {
                if (modal.parentNode) {
                    modal.parentNode.removeChild(modal);
                }
            }, 300);
        }
    }
};

// نسخ النص للحافظة
NotarySystem.copyToClipboard = function(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            this.showToast('تم نسخ النص', 'success');
        }).catch(() => {
            this.fallbackCopyToClipboard(text);
        });
    } else {
        this.fallbackCopyToClipboard(text);
    }
};

// نسخ احتياطي للحافظة
NotarySystem.fallbackCopyToClipboard = function(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        this.showToast('تم نسخ النص', 'success');
    } catch (err) {
        this.showToast('فشل في نسخ النص', 'error');
    }
    
    document.body.removeChild(textArea);
};

// تسجيل الخروج
NotarySystem.logout = function() {
    this.ajax({
        url: 'includes/login_handler.php',
        method: 'POST',
        data: { action: 'logout' },
        success: (response) => {
            if (response.success) {
                this.redirectToLogin();
            }
        },
        error: () => {
            this.redirectToLogin();
        }
    });
};

// إعادة التوجيه لصفحة تسجيل الدخول
NotarySystem.redirectToLogin = function() {
    window.location.href = 'index.php';
};

// معالجة تغيير حجم النافذة
NotarySystem.handleResize = function() {
    // إغلاق الشريط الجانبي في الشاشات الصغيرة
    if (window.innerWidth < 768 && this.elements.sidebar) {
        this.closeSidebar();
    }
};

// تهيئة التحميل التلقائي
NotarySystem.initAutoLoad = function() {
    // تحميل المحتوى عند التمرير
    const autoLoadElements = document.querySelectorAll('[data-auto-load]');
    
    if (autoLoadElements.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    const url = element.getAttribute('data-auto-load');
                    this.loadContent(element, url);
                    observer.unobserve(element);
                }
            });
        });
        
        autoLoadElements.forEach(element => {
            observer.observe(element);
        });
    }
};

// تحميل المحتوى
NotarySystem.loadContent = function(element, url) {
    element.innerHTML = '<div class="text-center p-3"><div class="spinner-border"></div></div>';
    
    this.ajax({
        url: url,
        showLoading: false,
        success: (response) => {
            if (response.success) {
                element.innerHTML = response.content;
            } else {
                element.innerHTML = '<div class="alert alert-danger">فشل في تحميل المحتوى</div>';
            }
        },
        error: () => {
            element.innerHTML = '<div class="alert alert-danger">حدث خطأ أثناء التحميل</div>';
        }
    });
};

// تهيئة الحفظ التلقائي
NotarySystem.initAutoSave = function() {
    const autoSaveForms = document.querySelectorAll('form[data-auto-save]');
    
    autoSaveForms.forEach(form => {
        let saveTimeout;
        
        form.addEventListener('input', () => {
            form.setAttribute('data-unsaved', 'true');
            
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                this.autoSaveForm(form);
            }, 2000); // حفظ تلقائي كل ثانيتين
        });
    });
};

// الحفظ التلقائي للنموذج
NotarySystem.autoSaveForm = function(form) {
    const formData = new FormData(form);
    formData.append('auto_save', '1');
    
    this.ajax({
        url: form.getAttribute('action') || window.location.href,
        method: 'POST',
        data: formData,
        showLoading: false,
        success: (response) => {
            if (response.success) {
                form.removeAttribute('data-unsaved');
                this.showToast('تم الحفظ التلقائي', 'info', 2000);
            }
        },
        error: () => {
            // تجاهل أخطاء الحفظ التلقائي
        }
    });
};

// تهيئة التنسيق التلقائي
NotarySystem.initAutoFormat = function() {
    // تنسيق أرقام الهاتف
    document.addEventListener('input', (e) => {
        if (e.target.matches('input[data-format="phone"]')) {
            this.formatPhoneNumber(e.target);
        }
    });
    
    // تنسيق التواريخ
    document.addEventListener('input', (e) => {
        if (e.target.matches('input[data-format="date"]')) {
            this.formatDate(e.target);
        }
    });
};

// تنسيق رقم الهاتف
NotarySystem.formatPhoneNumber = function(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length > 0) {
        if (value.length <= 3) {
            value = value;
        } else if (value.length <= 6) {
            value = value.slice(0, 3) + ' ' + value.slice(3);
        } else {
            value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6, 9);
        }
    }
    
    input.value = value;
};

// تنسيق التاريخ
NotarySystem.formatDate = function(input) {
    let value = input.value.replace(/\D/g, '');
    
    if (value.length >= 2) {
        value = value.slice(0, 2) + '/' + value.slice(2);
    }
    if (value.length >= 5) {
        value = value.slice(0, 5) + '/' + value.slice(5, 9);
    }
    
    input.value = value;
};

// تصدير النظام للنطاق العام
window.NotarySystem = NotarySystem;



// Sidebar Toggle for Mobile
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.querySelector('[data-toggle="sidebar"]');
    const closeButton = document.querySelector('[data-close="sidebar"]');
    const mainContent = document.querySelector('.main-content');
    let overlay = document.querySelector('.overlay');

    if (!overlay) {
        overlay = document.createElement('div');
        overlay.classList.add('overlay');
        document.body.appendChild(overlay);
    }

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        overlay.style.display = sidebar.classList.contains('show') ? 'block' : 'none';
    }

    if (toggleButton) {
        toggleButton.addEventListener('click', toggleSidebar);
    }

    if (closeButton) {
        closeButton.addEventListener('click', toggleSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }

    // Close sidebar on larger screens if it's open
    window.addEventListener('resize', function() {
        if (window.innerWidth > 991.98 && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
            overlay.style.display = 'none';
        }
    });
});


