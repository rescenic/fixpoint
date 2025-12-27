<?php
/**
 * Autoloader untuk FPDF, FPDI, dan PHPWord
 * Versi Fixed - Menghindari duplicate class declaration
 */

// Cegah multiple include
if (defined('FPDI_AUTOLOAD_LOADED')) {
    return;
}
define('FPDI_AUTOLOAD_LOADED', true);

// ========================================
// 1. LOAD FPDF (Base Library)
// ========================================
if (!class_exists('FPDF')) {
    $fpdf_file = __DIR__ . '/fpdf/fpdf.php';
    if (file_exists($fpdf_file)) {
        require_once $fpdf_file;
    }
}

// ========================================
// 2. LOAD FPDI - GUNAKAN AUTOLOAD BAWAAN
// ========================================
// PENTING: Jangan load manual satu-satu, gunakan autoload yang sudah disediakan FPDI
if (!class_exists('setasign\\Fpdi\\Fpdi')) {
    $fpdi_autoload = __DIR__ . '/fpdi/src/autoload.php';
    
    if (file_exists($fpdi_autoload)) {
        require_once $fpdi_autoload;
    } else {
        // Fallback jika tidak ada autoload.php
        // Load file-file penting secara berurutan
        $fpdi_files = [
            '/fpdi/src/FpdiException.php',
            '/fpdi/src/FpdfTplTrait.php',
            '/fpdi/src/FpdfTpl.php',
            '/fpdi/src/FpdiTrait.php',
            '/fpdi/src/Fpdi.php'
        ];
        
        foreach ($fpdi_files as $file) {
            $full_path = __DIR__ . $file;
            if (file_exists($full_path)) {
                require_once $full_path;
            }
        }
    }
}

// ========================================
// 3. AUTOLOAD PHPWord
// ========================================
spl_autoload_register(function ($class) {
    // Hanya handle class PhpOffice\PhpWord
    if (strpos($class, 'PhpOffice\\PhpWord\\') !== 0) {
        return;
    }
    
    $classPath = str_replace('PhpOffice\\PhpWord\\', '', $class);
    $classPath = str_replace('\\', '/', $classPath);
    
    $file = __DIR__ . '/phpword/src/PhpWord/' . $classPath . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    // Path alternatif
    $file = __DIR__ . '/phpword/PhpWord/' . $classPath . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
});

// ========================================
// 4. AUTOLOAD PhpOffice\Common (Dependency PHPWord)
// ========================================
spl_autoload_register(function ($class) {
    if (strpos($class, 'PhpOffice\\Common\\') !== 0) {
        return;
    }
    
    $classPath = str_replace('PhpOffice\\Common\\', '', $class);
    $classPath = str_replace('\\', '/', $classPath);
    
    $paths = [
        __DIR__ . '/phpword/src/Common/' . $classPath . '.php',
        __DIR__ . '/phpword/Common/' . $classPath . '.php',
    ];
    
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
});

// ========================================
// 5. AUTOLOAD FPDI Dependencies (Parser, Reader, dll)
// ========================================
spl_autoload_register(function ($class) {
    // Handle class dari namespace setasign
    if (strpos($class, 'setasign\\') !== 0) {
        return;
    }
    
    // Konversi namespace ke path
    $classPath = str_replace('setasign\\', '', $class);
    $classPath = str_replace('\\', '/', $classPath);
    
    // Coba di folder fpdi/src
    $file = __DIR__ . '/fpdi/src/' . $classPath . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
});