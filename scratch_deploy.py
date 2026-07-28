import ftplib
import os
import zipfile
import urllib.request
import ssl

FTP_HOST = "153.92.11.30"
FTP_USER = "u150322875"
FTP_PASS = "Arirahmadi123$"
PROJECT_DIR = "/Users/arirahmadi/Documents/system/finance-sys"

print("Connecting to FTP...")
ftp = ftplib.FTP()
ftp.connect(FTP_HOST, 21, timeout=30)
ftp.login(FTP_USER, FTP_PASS)
print("FTP Login Success!")

# Explore remote directory structure
remote_dirs = []
ftp.dir(remote_dirs.append)
print("Remote Root Contents:")
for line in remote_dirs:
    print(" ", line)

target_path = None
# Check paths
try:
    ftp.cwd("domains/berkahsinergigemilang.com/public_html/finance")
    target_path = "domains/berkahsinergigemilang.com/public_html/finance"
    print("Found target path: domains/berkahsinergigemilang.com/public_html/finance")
except Exception as e:
    print("Path 1 check failed:", e)

if not target_path:
    try:
        ftp.cwd("/public_html/finance")
        target_path = "/public_html/finance"
        print("Found target path: /public_html/finance")
    except Exception as e:
        print("Path 2 check failed:", e)

if not target_path:
    try:
        ftp.cwd("/public_html")
        target_path = "/public_html"
        print("Found target path: /public_html")
    except Exception as e:
        print("Path 3 check failed:", e)

print("Target working directory set to:", target_path)

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

unzip_root_path = os.path.join(PROJECT_DIR, "unzip.php")
with open(unzip_root_path, "w") as f:
    f.write(unzip_root)

unzip_pub_dir = os.path.join(PROJECT_DIR, "public")
os.makedirs(unzip_pub_dir, exist_ok=True)
unzip_pub_path = os.path.join(unzip_pub_dir, "unzip.php")
with open(unzip_pub_path, "w") as f:
    f.write(unzip_public)

zip_path = os.path.join(PROJECT_DIR, "project.zip")
print("Creating project.zip...")

excluded_dirs = {".git", ".github", "node_modules", "tests", "vendor", "storage"}
excluded_files = {".DS_Store", "project.zip", "phpunit.xml", "vite.config.js", "scratch_deploy.py"}

with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
    for root, dirs, files in os.walk(PROJECT_DIR):
        dirs[:] = [d for d in dirs if d not in excluded_dirs]
        for file in files:
            if file in excluded_files:
                continue
            abs_file = os.path.join(root, file)
            rel_file = os.path.relpath(abs_file, PROJECT_DIR)
            zipf.write(abs_file, rel_file)

print("project.zip created successfully. Size:", os.path.getsize(zip_path), "bytes")

print("Uploading files via FTP...")
with open(zip_path, "rb") as f:
    ftp.storbinary("STOR project.zip", f)
print("Uploaded project.zip")

with open(unzip_root_path, "rb") as f:
    ftp.storbinary("STOR unzip.php", f)
print("Uploaded unzip.php")

try:
    ftp.mkd("public")
except Exception:
    pass
try:
    ftp.cwd("public")
    with open(unzip_pub_path, "rb") as f:
        ftp.storbinary("STOR unzip.php", f)
    print("Uploaded public/unzip.php")
except Exception as e:
    print("Failed to upload public/unzip.php:", e)

ftp.quit()
print("FTP Upload Finished!")

if os.path.exists(zip_path):
    os.remove(zip_path)
if os.path.exists(unzip_root_path):
    os.remove(unzip_root_path)
if os.path.exists(unzip_pub_path):
    os.remove(unzip_pub_path)

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

print("Triggering unzip on server...")
for url in [
    "https://finance.berkahsinergigemilang.com/unzip.php",
    "https://finance.berkahsinergigemilang.com/public/unzip.php"
]:
    try:
        print(f"Calling {url}...")
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req, context=ctx, timeout=30) as resp:
            body = resp.read().decode('utf-8')
            print(f"Response from {url}:", body)
            if "SUCCESS" in body:
                break
    except Exception as e:
        print(f"Error calling {url}:", e)

try:
    print("Triggering /clear-cache...")
    req = urllib.request.Request("https://finance.berkahsinergigemilang.com/clear-cache", headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(req, context=ctx, timeout=30) as resp:
        print("Clear-cache response:", resp.read().decode('utf-8'))
except Exception as e:
    print("Error clearing cache:", e)

print("DEPLOYMENT COMPLETE!")
