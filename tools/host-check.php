<?php
// Host check for the DonWeb shared host.
// This script shows the PHP version, the extensions and the limits that the system needs.
//
// CAUTION: Delete this file from the host after you read the result.
//
// 1. Upload this file to the public folder of the host through FTP.
// 2. Open https://<your-domain>/host-check.php in a browser.
// 3. Copy the result.
// 4. Delete the file from the host.

header('Content-Type: text/plain; charset=utf-8');

function yes_no($value)
{
    return $value ? 'yes' : 'NO';
}

echo "PHP version: " . PHP_VERSION . "\n";
echo "PHP interface: " . PHP_SAPI . "\n";
echo "\n";

echo "Extensions\n";
// curl downloads the lists of licensees of ENACOM and URSEC. zlib reads the ODS file of URSEC.
// openssl connects to the SMTP server of the QSL mailer (port 465 with SSL).
$extensions = array('pdo_mysql', 'mysqli', 'gd', 'mbstring', 'fileinfo', 'json', 'session', 'zlib', 'curl', 'openssl');
foreach ($extensions as $extension) {
    echo "  " . $extension . ": " . yes_no(extension_loaded($extension)) . "\n";
}
echo "\n";

// The system writes text on JPEG and PNG templates with a TrueType font.
echo "GD functions\n";
if (function_exists('gd_info')) {
    $gd = gd_info();
    $jpeg = !empty($gd['JPEG Support']) || !empty($gd['JPG Support']);
    echo "  GD version: " . $gd['GD Version'] . "\n";
    echo "  FreeType: " . yes_no(!empty($gd['FreeType Support'])) . "\n";
    echo "  JPEG: " . yes_no($jpeg) . "\n";
    echo "  PNG: " . yes_no(!empty($gd['PNG Support'])) . "\n";
} else {
    echo "  GD is not available.\n";
}
echo "\n";

echo "Limits\n";
$settings = array('file_uploads', 'upload_max_filesize', 'post_max_size', 'memory_limit', 'max_execution_time');
foreach ($settings as $setting) {
    echo "  " . $setting . ": " . ini_get($setting) . "\n";
}
echo "\n";

echo "Files\n";
echo "  This folder is writable: " . yes_no(is_writable(__DIR__)) . "\n";
