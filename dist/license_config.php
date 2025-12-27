<?php
/**
 * Konfigurasi License Server
 */

function getLicenseConfig() {
    return [
        // GANTI URL INI dengan URL license server Anda!
        // Misal: http://localhost/fixlicense/api/validate.php
        // Atau: https://license.domain.com/license-system/api/validate.php
        'api_url' => 'http://localhost/fixlicense/api/validate.php',
        
        'timeout' => 10,
        'revalidate_days' => 30,
        'mode' => 'online'
    ];
}
?>