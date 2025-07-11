<?php
/**
 * ملف رفع الملفات
 * File Upload Handler
 * 
 * يحتوي على وظائف رفع ومعالجة الملفات
 * Contains file upload and handling functions
 */

// منع الوصول المباشر
if (!defined('SYSTEM_ACCESS')) {
    die('Access denied');
}

class FileUpload {
    
    private $uploadPath;
    private $allowedTypes;
    private $maxFileSize;
    private $errors = [];
    
    public function __construct() {
        $this->uploadPath = UPLOAD_PATH;
        $this->allowedTypes = json_decode(ALLOWED_FILE_TYPES, true);
        $this->maxFileSize = $this->parseSize(UPLOAD_MAX_SIZE);
        
        // إنشاء مجلد الرفع إذا لم يكن موجوداً
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
        
        // إنشاء مجلدات فرعية
        $this->createSubDirectories();
    }
    
    /**
     * إنشاء مجلدات فرعية
     * Create subdirectories
     */
    private function createSubDirectories() {
        $subdirs = ['contracts', 'documents', 'images', 'temp'];
        
        foreach ($subdirs as $subdir) {
            $path = $this->uploadPath . $subdir . '/';
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
    
    /**
     * رفع ملف واحد
     * Upload single file
     */
    public function uploadFile($file, $category = 'documents', $customName = null) {
        $this->errors = [];
        
        // التحقق من وجود الملف
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $this->errors[] = 'لم يتم اختيار ملف للرفع';
            return false;
        }
        
        // التحقق من أخطاء الرفع
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return false;
        }
        
        // التحقق من حجم الملف
        if ($file['size'] > $this->maxFileSize) {
            $this->errors[] = 'حجم الملف كبير جداً. الحد الأقصى المسموح: ' . formatFileSize($this->maxFileSize);
            return false;
        }
        
        // التحقق من نوع الملف
        if (!$this->isAllowedType($file['name'])) {
            $this->errors[] = 'نوع الملف غير مسموح. الأنواع المسموحة: ' . implode(', ', $this->allowedTypes);
            return false;
        }
        
        // التحقق من محتوى الملف (فحص أمني)
        if (!$this->isSecureFile($file['tmp_name'], $file['name'])) {
            $this->errors[] = 'الملف يحتوي على محتوى غير آمن';
            return false;
        }
        
        // إنشاء اسم ملف فريد
        $filename = $this->generateUniqueFilename($file['name'], $customName);
        $targetPath = $this->uploadPath . $category . '/' . $filename;
        
        // نقل الملف
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // تسجيل عملية الرفع
            $userId = $_SESSION['user_id'] ?? null;
            Logger::logFile('upload', $filename, $userId, [
                'original_name' => $file['name'],
                'size' => $file['size'],
                'category' => $category
            ]);
            
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $targetPath,
                'url' => $this->getFileUrl($category, $filename),
                'size' => $file['size'],
                'type' => $file['type']
            ];
        } else {
            $this->errors[] = 'فشل في رفع الملف';
            return false;
        }
    }
    
    /**
     * رفع عدة ملفات
     * Upload multiple files
     */
    public function uploadMultipleFiles($files, $category = 'documents') {
        $results = [];
        
        // تحويل تنسيق الملفات المتعددة
        $fileArray = $this->reArrayFiles($files);
        
        foreach ($fileArray as $file) {
            $result = $this->uploadFile($file, $category);
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * حذف ملف
     * Delete file
     */
    public function deleteFile($filename, $category = 'documents') {
        $filePath = $this->uploadPath . $category . '/' . $filename;
        
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                // تسجيل عملية الحذف
                $userId = $_SESSION['user_id'] ?? null;
                Logger::logFile('delete', $filename, $userId, ['category' => $category]);
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * نقل ملف من مجلد إلى آخر
     * Move file between categories
     */
    public function moveFile($filename, $fromCategory, $toCategory) {
        $fromPath = $this->uploadPath . $fromCategory . '/' . $filename;
        $toPath = $this->uploadPath . $toCategory . '/' . $filename;
        
        if (file_exists($fromPath)) {
            if (rename($fromPath, $toPath)) {
                // تسجيل عملية النقل
                $userId = $_SESSION['user_id'] ?? null;
                Logger::logFile('move', $filename, $userId, [
                    'from' => $fromCategory,
                    'to' => $toCategory
                ]);
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * الحصول على معلومات الملف
     * Get file information
     */
    public function getFileInfo($filename, $category = 'documents') {
        $filePath = $this->uploadPath . $category . '/' . $filename;
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        return [
            'filename' => $filename,
            'path' => $filePath,
            'url' => $this->getFileUrl($category, $filename),
            'size' => filesize($filePath),
            'type' => mime_content_type($filePath),
            'created' => filemtime($filePath),
            'extension' => pathinfo($filename, PATHINFO_EXTENSION)
        ];
    }
    
    /**
     * إنشاء صورة مصغرة
     * Generate thumbnail
     */
    public function generateThumbnail($filename, $category = 'images', $width = 150, $height = 150) {
        $filePath = $this->uploadPath . $category . '/' . $filename;
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // التحقق من أن الملف صورة
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            return false;
        }
        
        // إنشاء مجلد الصور المصغرة
        $thumbDir = $this->uploadPath . $category . '/thumbnails/';
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }
        
        $thumbPath = $thumbDir . 'thumb_' . $filename;
        
        // إنشاء الصورة المصغرة
        if ($this->createThumbnail($filePath, $thumbPath, $width, $height)) {
            return $this->getFileUrl($category . '/thumbnails', 'thumb_' . $filename);
        }
        
        return false;
    }
    
    /**
     * إنشاء الصورة المصغرة
     * Create thumbnail image
     */
    private function createThumbnail($source, $destination, $width, $height) {
        $info = getimagesize($source);
        if (!$info) return false;
        
        $sourceWidth = $info[0];
        $sourceHeight = $info[1];
        $sourceType = $info[2];
        
        // إنشاء الصورة المصدر
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($source);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($source);
                break;
            default:
                return false;
        }
        
        // حساب الأبعاد الجديدة
        $ratio = min($width / $sourceWidth, $height / $sourceHeight);
        $newWidth = $sourceWidth * $ratio;
        $newHeight = $sourceHeight * $ratio;
        
        // إنشاء الصورة الجديدة
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        
        // الحفاظ على الشفافية للـ PNG
        if ($sourceType == IMAGETYPE_PNG) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
        }
        
        // تغيير حجم الصورة
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
        
        // حفظ الصورة المصغرة
        $result = false;
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($thumbnail, $destination, 85);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($thumbnail, $destination);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($thumbnail, $destination);
                break;
        }
        
        // تنظيف الذاكرة
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
        
        return $result;
    }
    
    /**
     * التحقق من نوع الملف المسموح
     * Check allowed file type
     */
    private function isAllowedType($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $this->allowedTypes);
    }
    
    /**
     * التحقق من أمان الملف
     * Check file security
     */
    private function isSecureFile($tmpPath, $filename) {
        // فحص امتداد الملف
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // منع الملفات التنفيذية
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'exe', 'bat', 'cmd', 'scr', 'vbs', 'js'];
        if (in_array($extension, $dangerousExtensions)) {
            return false;
        }
        
        // فحص محتوى الملف للبحث عن كود PHP
        $content = file_get_contents($tmpPath, false, null, 0, 1024); // قراءة أول 1KB
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            return false;
        }
        
        // فحص MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain', 'text/csv'
        ];
        
        return in_array($mimeType, $allowedMimes);
    }
    
    /**
     * إنشاء اسم ملف فريد
     * Generate unique filename
     */
    private function generateUniqueFilename($originalName, $customName = null) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        if ($customName) {
            $basename = $this->sanitizeFilename($customName);
        } else {
            $basename = pathinfo($originalName, PATHINFO_FILENAME);
            $basename = $this->sanitizeFilename($basename);
        }
        
        $timestamp = time();
        $random = mt_rand(1000, 9999);
        
        return $basename . '_' . $timestamp . '_' . $random . '.' . $extension;
    }
    
    /**
     * تنظيف اسم الملف
     * Sanitize filename
     */
    private function sanitizeFilename($filename) {
        // إزالة الأحرف الخاصة
        $filename = preg_replace('/[^a-zA-Z0-9\u0600-\u06FF\-_]/', '_', $filename);
        
        // إزالة الشرطات السفلية المتتالية
        $filename = preg_replace('/_+/', '_', $filename);
        
        // إزالة الشرطات من البداية والنهاية
        return trim($filename, '_-');
    }
    
    /**
     * تحويل حجم الملف من نص إلى بايت
     * Parse file size from string to bytes
     */
    private function parseSize($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }
    
    /**
     * الحصول على رابط الملف
     * Get file URL
     */
    private function getFileUrl($category, $filename) {
        return BASE_URL . '/uploads/' . $category . '/' . $filename;
    }
    
    /**
     * إعادة ترتيب مصفوفة الملفات المتعددة
     * Rearrange multiple files array
     */
    private function reArrayFiles($filePost) {
        $fileArray = [];
        $fileCount = count($filePost['name']);
        $fileKeys = array_keys($filePost);
        
        for ($i = 0; $i < $fileCount; $i++) {
            foreach ($fileKeys as $key) {
                $fileArray[$i][$key] = $filePost[$key][$i];
            }
        }
        
        return $fileArray;
    }
    
    /**
     * الحصول على رسالة خطأ الرفع
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'حجم الملف كبير جداً (تجاوز الحد المسموح في الخادم)';
            case UPLOAD_ERR_FORM_SIZE:
                return 'حجم الملف كبير جداً (تجاوز الحد المسموح في النموذج)';
            case UPLOAD_ERR_PARTIAL:
                return 'تم رفع جزء من الملف فقط';
            case UPLOAD_ERR_NO_FILE:
                return 'لم يتم اختيار ملف';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'مجلد الملفات المؤقتة غير موجود';
            case UPLOAD_ERR_CANT_WRITE:
                return 'فشل في كتابة الملف على القرص';
            case UPLOAD_ERR_EXTENSION:
                return 'امتداد PHP أوقف رفع الملف';
            default:
                return 'خطأ غير معروف في رفع الملف';
        }
    }
    
    /**
     * الحصول على الأخطاء
     * Get errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * تنظيف الملفات المؤقتة القديمة
     * Clean old temporary files
     */
    public function cleanTempFiles($olderThanHours = 24) {
        $tempDir = $this->uploadPath . 'temp/';
        $cutoffTime = time() - ($olderThanHours * 3600);
        
        if (!is_dir($tempDir)) {
            return 0;
        }
        
        $files = glob($tempDir . '*');
        $deletedCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }
        
        return $deletedCount;
    }
    
    /**
     * الحصول على إحصائيات الملفات
     * Get file statistics
     */
    public function getFileStats() {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'categories' => []
        ];
        
        $categories = ['contracts', 'documents', 'images', 'temp'];
        
        foreach ($categories as $category) {
            $categoryPath = $this->uploadPath . $category . '/';
            if (is_dir($categoryPath)) {
                $files = glob($categoryPath . '*');
                $categorySize = 0;
                $categoryCount = 0;
                
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $categorySize += filesize($file);
                        $categoryCount++;
                    }
                }
                
                $stats['categories'][$category] = [
                    'count' => $categoryCount,
                    'size' => $categorySize
                ];
                
                $stats['total_files'] += $categoryCount;
                $stats['total_size'] += $categorySize;
            }
        }
        
        return $stats;
    }
}

// إنشاء مثيل عام لرفع الملفات
$fileUpload = new FileUpload();
?>

