<?php
if (extension_loaded('imagick')) {
    echo "✅ Imagick SUDAH terinstall!";
    phpinfo(INFO_MODULES);
} else {
    echo "❌ Imagick BELUM terinstall!";
}
?>