import ftplib

FTP_HOST = "153.92.11.30"
FTP_USER = "u150322875"
FTP_PASS = "Arirahmadi123$"

print("Connecting to FTP...")
ftp = ftplib.FTP()
ftp.connect(FTP_HOST, 21, timeout=30)
ftp.login(FTP_USER, FTP_PASS)
print("FTP Login Success!")

for path in ["domains/berkahsinergigemilang.com/public_html/finance", "/public_html/finance"]:
    try:
        ftp.cwd(path)
        print(f"\nListing files in: {path}")
        files = []
        ftp.retrlines("LIST -a", files.append)
        for f in files:
            print(f)
    except Exception as e:
        print(f"Error listing {path}: {e}")

ftp.quit()
print("\nDone!")
