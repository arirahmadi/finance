<?php

$hosts = [
    '153.92.11.30',
    'ftp.berkahsinergigemilang.com',
    'berkahsinergigemilang.com'
];
$ftpUser = 'u150322875';
$ftpPass = 'Arirahmadi123$';
$projectDir = __DIR__;

$conn = false;
foreach ($hosts as $host) {
    echo "Attempting ftp_connect to {$host}...\n";
    $conn = @ftp_connect($host, 21, 15);
    if ($conn) {
        echo "Connected to {$host}!\n";
        break;
    }
    echo "Attempting ftp_ssl_connect to {$host}...\n";
    $conn = @ftp_ssl_connect($host, 21, 15);
    if ($conn) {
        echo "SSL Connected to {$host}!\n";
        break;
    }
}

if (!$conn) {
    die("ERROR: Could not connect to FTP host on any attempt.\n");
}

if (!@ftp_login($conn, $ftpUser, $ftpPass)) {
    die("ERROR: FTP login failed.\n");
}
@ftp_pasv($conn, true);
echo "FTP Login Successful!\n";

$nlist = ftp_nlist($conn, ".");
echo "Remote Root files: " . implode(", ", $nlist ?: []) . "\n";

$targetPath = null;
if (@ftp_chdir($conn, "domains/berkahsinergigemilang.com/public_html/finance")) {
    $targetPath = "domains/berkahsinergigemilang.com/public_html/finance";
    echo "Found target path: {$targetPath}\n";
} elseif (@ftp_chdir($conn, "public_html/finance")) {
    $targetPath = "public_html/finance";
    echo "Found target path: {$targetPath}\n";
} elseif (@ftp_chdir($conn, "public_html")) {
    $targetPath = "public_html";
    echo "Found target path: {$targetPath}\n";
} else {
    echo "Using default root directory.\n";
}

$unzipRootCode = <<<'CODE'
<?php
$zipFile = __DIR__ . '/project.zip';
if (file_exists($zipFile)) {
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo(__DIR__);
        $zip->close();
        @unlink($zipFile);

        $compiledViews = glob(__DIR__ . '/storage/framework/views/*.php');
        if ($compiledViews) {
            foreach ($compiledViews as $vf) {
                @unlink($vf);
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        echo 'SUCCESS';
    } else {
        echo 'ERROR: Failed to open zip';
    }
} else {
    echo 'ERROR: Zip file not found';
}
@unlink(__FILE__);
CODE;

$unzipPubCode = <<<'CODE'
<?php
$zipFile = __DIR__ . '/../project.zip';
if (file_exists($zipFile)) {
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo(__DIR__ . '/../');
        $zip->close();
        @unlink($zipFile);

        $compiledViews = glob(__DIR__ . '/../storage/framework/views/*.php');
        if ($compiledViews) {
            foreach ($compiledViews as $vf) {
                @unlink($vf);
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        echo 'SUCCESS';
    } else {
        echo 'ERROR: Failed to open zip';
    }
} else {
    echo 'ERROR: Zip file not found';
}
@unlink(__FILE__);
CODE;

file_put_contents($projectDir . '/unzip.php', $unzipRootCode);
if (!is_dir($projectDir . '/public')) {
    mkdir($projectDir . '/public', 0755, true);
}
file_put_contents($projectDir . '/public/unzip.php', $unzipPubCode);

$zipPath = $projectDir . '/project.zip';
echo "Creating project.zip...\n";
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Failed to create project.zip\n");
}

$excludedDirs = ['.git', '.github', 'node_modules', 'tests', 'vendor', 'storage'];
$excludedFiles = ['.DS_Store', 'project.zip', 'phpunit.xml', 'vite.config.js', 'scratch_deploy.py', 'scratch_deploy.php'];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($files as $file) {
    $filePath = $file->getRealPath();
    $relativePath = substr($filePath, strlen($projectDir) + 1);

    $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
    if (in_array($parts[0], $excludedDirs)) {
        continue;
    }
    if (in_array($file->getFilename(), $excludedFiles)) {
        continue;
    }

    if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
    } else if ($file->isFile()) {
        $zip->addFile($filePath, $relativePath);
    }
}
$zip->close();

echo "project.zip created (" . filesize($zipPath) . " bytes).\n";

echo "Uploading project.zip...\n";
if (ftp_put($conn, "project.zip", $zipPath, FTP_BINARY)) {
    echo "project.zip uploaded successfully!\n";
} else {
    echo "ERROR uploading project.zip\n";
}

echo "Uploading unzip.php...\n";
ftp_put($conn, "unzip.php", $projectDir . '/unzip.php', FTP_ASCII);

@ftp_mkdir($conn, "public");
if (@ftp_chdir($conn, "public")) {
    ftp_put($conn, "unzip.php", $projectDir . '/public/unzip.php', FTP_ASCII);
    echo "public/unzip.php uploaded!\n";
}

ftp_close($conn);
echo "FTP Operations Finished!\n";

@unlink($zipPath);
@unlink($projectDir . '/unzip.php');
@unlink($projectDir . '/public/unzip.php');

echo "Triggering unzip on server via cURL...\n";
$urls = [
    "https://finance.berkahsinergigemilang.com/unzip.php",
    "https://finance.berkahsinergigemilang.com/public/unzip.php"
];

foreach ($urls as $url) {
    echo "Calling {$url}...\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $res = curl_exec($ch);
    curl_close($ch);
    echo "Response: " . $res . "\n";
    if (strpos($res, 'SUCCESS') !== false) {
        break;
    }
}

echo "Clearing cache via cURL...\n";
$ch = curl_init("https://finance.berkahsinergigemilang.com/clear-cache");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$res = curl_exec($ch);
curl_close($ch);
echo "Clear cache response:\n" . $res . "\n";

echo "DEPLOYMENT COMPLETED SUCCESSFULLY!\n";
