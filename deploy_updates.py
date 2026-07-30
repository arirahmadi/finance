import ftplib
import os
import urllib.request
import ssl

FTP_HOST = "153.92.11.30"
FTP_USER = "u150322875"
FTP_PASS = "Arirahmadi123$"

files_to_upload = [
    ("app/Http/Controllers/Web/WebController.php", "app/Http/Controllers/Web/WebController.php"),
    ("resources/views/dashboard.blade.php", "resources/views/dashboard.blade.php"),
    ("routes/web.php", "routes/web.php")
]

print("Connecting to FTP Hostinger...")
ftp = ftplib.FTP()
ftp.connect(FTP_HOST, 21, timeout=30)
ftp.login(FTP_USER, FTP_PASS)
print("FTP Login Success!")

# Set target directory
target_path = "domains/berkahsinergigemilang.com/public_html/finance"
try:
    ftp.cwd(target_path)
    print(f"Working directory set to: {target_path}")
except Exception as e:
    try:
        target_path = "/public_html/finance"
        ftp.cwd(target_path)
        print(f"Working directory set to: {target_path}")
    except Exception as ex:
        print("Failed to navigate to target directory on server:", ex)
        exit(1)

for local_path, remote_path in files_to_upload:
    if not os.path.exists(local_path):
        print(f"Local file {local_path} does not exist!")
        continue
    
    print(f"Uploading {local_path} -> {remote_path}...")
    with open(local_path, "rb") as f:
        ftp.storbinary(f"STOR {remote_path}", f)
    print(f"Uploaded {remote_path} successfully!")

ftp.quit()
print("FTP Upload Complete!")

# Clear cache on server
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

try:
    print("Triggering clear-cache on server...")
    url = "https://finance.berkahsinergigemilang.com/clear-cache"
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(req, context=ctx, timeout=15) as resp:
        print("Response:", resp.read().decode('utf-8'))
except Exception as e:
    try:
        url = "https://finance.berkahsinergigemilang.com/public/clear-cache"
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req, context=ctx, timeout=15) as resp:
            print("Response (public/clear-cache):", resp.read().decode('utf-8'))
    except Exception as ex:
        print("Cache clear trigger error:", ex)

print("\nDEPLOYMENT OF UPDATES COMPLETE!")
