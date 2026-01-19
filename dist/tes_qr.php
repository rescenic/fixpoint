<?php
require 'vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

$kode = $_GET['kode'] ?? 'GD';

$generator = new BarcodeGeneratorPNG();
header('Content-Type: image/png');

echo $generator->getBarcode($kode, $generator::TYPE_CODE_128);
exit;
