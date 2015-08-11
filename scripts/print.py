#!/usr/bin/python
# -*- coding: utf-8 -*-

import sys

from ftplib import FTP

tfile = sys.argv[1]
#ftp = FTP("137.226.150.198")
ftp = FTP("137.226.150.099")
file = open(tfile,"rb")

ftp.login()
ftp.cwd('/lp4')
ftp.storbinary("STOR "+tfile, file)
file.close()
ftp.quit()
