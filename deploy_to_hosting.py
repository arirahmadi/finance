import ftplib
import os
import zipfile
import urllib.request
import ssl

FTP_HOST = "153.92.11.30"
FTP_USER = "u150322875"
FTP_PASS = "Arirahmadi123$"
PROJECT_DIR = os.path.dirname(os.path.abspath(__file__))

print("Connecting to Hostinger FTP...")
ftp = ftplib.FTP()
ftp.connect(FTP_HOST, 21, timeout=30)
ftp.login(FTP_USER, FTP_PASS)
print("FTP Login Success!")

# Determine working directory on server
target_path = None
for path in ["domains/berkahsinergigemilang.com/public_html/finance", "/public_html/finance"]:
    try:
        ftp.cwd(path)
        target_path = path
        print(f"Set remote directory to: {path}")
        break
    except Exception as e:
        print(f"Could not change directory to {path}: {e}")

if not target_path:
    print("Failed to locate target directory on server.")
    ftp.quit()
    exit(1)

# 1. Create unzip.php scripts
unzip_root = """<?php
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
"""

unzip_public = """<?php
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
"""

# 2. Create migrate_runner.php script
migrate_runner = """<?php
$token = $_GET['token'] ?? '';
$expected = 'default_secret_token_123';
if ($token !== $expected) {
    http_response_code(403);
    echo 'FORBIDDEN';
    exit;
}

define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$status = $kernel->call('migrate', ['--force' => true]);

echo "MIGRATE STATUS: " . $status . "\\n";
echo "MIGRATE OUTPUT:\\n" . $kernel->output();
@unlink(__FILE__);
"""

unzip_root_path = os.path.join(PROJECT_DIR, "unzip.php")
with open(unzip_root_path, "w") as f:
    f.write(unzip_root)

unzip_pub_dir = os.path.join(PROJECT_DIR, "public")
os.makedirs(unzip_pub_dir, exist_ok=True)

unzip_pub_path = os.path.join(unzip_pub_dir, "unzip.php")
with open(unzip_pub_path, "w") as f:
    f.write(unzip_public)

migrate_runner_path = os.path.join(unzip_pub_dir, "migrate_runner.php")
with open(migrate_runner_path, "w") as f:
    f.write(migrate_runner)

# 3. Zip the project directory
zip_path = os.path.join(PROJECT_DIR, "project.zip")
print("Zipping local project files...")

excluded_dirs = {".git", ".github", "node_modules", "tests", "vendor", "storage"}
excluded_files = {".DS_Store", "project.zip", "phpunit.xml", "vite.config.js", "scratch_deploy.py", "deploy_to_hosting.py", ".env"}

with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
    for root, dirs, files in os.walk(PROJECT_DIR):
        dirs[:] = [d for d in dirs if d not in excluded_dirs]
        for file in files:
            if file in excluded_files:
                continue
            abs_file = os.path.join(root, file)
            rel_file = os.path.relpath(abs_file, PROJECT_DIR)
            zipf.write(abs_file, rel_file)

print(f"ZIP created successfully. Size: {os.path.getsize(zip_path)} bytes.")

# 4. Upload files via FTP
print("Uploading ZIP file to remote server...")
with open(zip_path, "rb") as f:
    ftp.storbinary("STOR project.zip", f)
print("project.zip uploaded.")

with open(unzip_root_path, "rb") as f:
    ftp.storbinary("STOR unzip.php", f)
print("unzip.php uploaded to remote root.")

try:
    ftp.mkd("public")
except Exception:
    pass

try:
    ftp.cwd("public")
    with open(unzip_pub_path, "rb") as f:
        ftp.storbinary("STOR unzip.php", f)
    print("unzip.php uploaded to remote public/.")
    
    with open(migrate_runner_path, "rb") as f:
        ftp.storbinary("STOR migrate_runner.php", f)
    print("migrate_runner.php uploaded to remote public/.")
except Exception as e:
    print(f"Failed to upload public files: {e}")

ftp.quit()
print("FTP Session Closed.")

# Clean up local temporary scripts
if os.path.exists(zip_path):
    os.remove(zip_path)
if os.path.exists(unzip_root_path):
    os.remove(unzip_root_path)
if os.path.exists(unzip_pub_path):
    os.remove(unzip_pub_path)
if os.path.exists(migrate_runner_path):
    os.remove(migrate_runner_path)

# 5. Trigger extraction and database migration over HTTP
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

print("Extracting ZIP archive on live server...")
unzipped = False
for url in [
    "https://finance.berkahsinergigemilang.com/unzip.php",
    "https://finance.berkahsinergigemilang.com/public/unzip.php"
]:
    try:
        print(f"Calling: {url}")
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req, context=ctx, timeout=30) as resp:
            body = resp.read().decode('utf-8')
            print(f"Response: {body}")
            if "SUCCESS" in body:
                unzipped = True
                break
    except Exception as e:
        print(f"Extraction call failed: {e}")

if not unzipped:
    print("Warning: Unzip verification not confirmed.")

print("Running database migrations on live server...")
try:
    migrate_url = "https://finance.berkahsinergigemilang.com/public/migrate_runner.php?token=default_secret_token_123"
    print(f"Calling: {migrate_url}")
    req = urllib.request.Request(migrate_url, headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(req, context=ctx, timeout=60) as resp:
        print(resp.read().decode('utf-8'))
except Exception as e:
    print(f"Migration call failed: {e}")

print("Clearing application cache...")
try:
    cache_url = "https://finance.berkahsinergigemilang.com/clear-cache"
    req = urllib.request.Request(cache_url, headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(req, context=ctx, timeout=15) as resp:
        print("Cache reset response:", resp.read().decode('utf-8'))
except Exception as e:
    print(f"Cache reset failed: {e}")

print("\nDEPLOYMENT COMPLETE!")
