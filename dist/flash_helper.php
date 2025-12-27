<?php
/**
 * Helper Functions untuk Flash Message
 * File: flash_helper.php
 * 
 * Cara Penggunaan:
 * 1. Include file ini di awal halaman: include 'flash_helper.php';
 * 2. Set flash: set_flash('success', 'Berhasil menyimpan data');
 * 3. Show flash: show_flash(); (di HTML)
 */

/**
 * Set flash message dengan format standar
 * 
 * @param string $type success, error, warning, info
 * @param string $message Pesan yang ingin ditampilkan
 */
function set_flash($type, $message) {
    $icons = [
        'success' => 'fa-check-circle',
        'error'   => 'fa-times-circle',
        'warning' => 'fa-exclamation-triangle',
        'info'    => 'fa-info-circle'
    ];
    
    $_SESSION['flash'] = [
        'type'    => $type,
        'icon'    => $icons[$type] ?? 'fa-info-circle',
        'message' => $message
    ];
}

/**
 * Tampilkan flash message
 * Call di dalam HTML body
 */
function show_flash() {
    if (!isset($_SESSION['flash'])) {
        return '';
    }
    
    $flash = $_SESSION['flash'];
    
    $bgColors = [
        'success' => '#28a745',
        'error'   => '#dc3545',
        'warning' => '#ffc107',
        'info'    => '#17a2b8'
    ];
    
    $textColors = [
        'success' => '#fff',
        'error'   => '#fff',
        'warning' => '#212529',
        'info'    => '#fff'
    ];
    
    $bgColor = $bgColors[$flash['type']] ?? '#17a2b8';
    $textColor = $textColors[$flash['type']] ?? '#fff';
    
    $html = '
    <div class="flash-message-center" id="flashMsg" style="
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 9999;
        min-width: 320px;
        max-width: 90%;
        text-align: center;
        padding: 25px 30px;
        border-radius: 12px;
        font-weight: 500;
        background: '.$bgColor.';
        color: '.$textColor.';
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        animation: fadeInOut 4s ease forwards;
    ">
        <i class="fas '.$flash['icon'].'" style="font-size: 40px; margin-bottom: 10px;"></i><br>
        <div style="font-size: 15px;">'.htmlspecialchars($flash['message']).'</div>
    </div>
    
    <style>
    @keyframes fadeInOut {
        0% { opacity: 0; transform: translate(-50%, -60%); }
        10%, 90% { opacity: 1; transform: translate(-50%, -50%); }
        100% { opacity: 0; transform: translate(-50%, -40%); }
    }
    </style>
    
    <script>
    setTimeout(function() {
        document.getElementById("flashMsg").style.display = "none";
    }, 4000);
    </script>
    ';
    
    unset($_SESSION['flash']);
    return $html;
}

/**
 * Check apakah ada flash message
 * 
 * @return bool
 */
function has_flash() {
    return isset($_SESSION['flash']);
}

/**
 * Get flash message tanpa menghapusnya dari session
 * 
 * @return array|null
 */
function get_flash() {
    return $_SESSION['flash'] ?? null;
}

/**
 * Clear flash message dari session
 */
function clear_flash() {
    unset($_SESSION['flash']);
}