<?php
$upload_dir = __DIR__ . '/images/profiles/';
echo "Upload directory path: " . $upload_dir . "<br>";
echo "Directory exists: " . (is_dir($upload_dir) ? 'YES' : 'NO') . "<br>";
echo "Directory writable: " . (is_writable($upload_dir) ? 'YES' : 'NO') . "<br>";

if (!is_dir($upload_dir)) {
    if (mkdir($upload_dir, 0755, true)) {
        echo "Directory created successfully!<br>";
    } else {
        echo "Failed to create directory<br>";
    }
}

echo "Current permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "<br>";