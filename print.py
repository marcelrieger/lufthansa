import sys

from ftplib import FTP

tfile = sys.argv[1]
ftp = FTP("137.226.150.198")
file = open(tfile,"rb")

ftp.login()
ftp.storbinary("STOR "+tfile, file)
file.close()
ftp.quit()
