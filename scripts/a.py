#!/usr/bin/python

import threading
import time

exitFlag = 0

class myThread (threading.Thread):
    def __init__(self):
        threading.Thread.__init__(self)

    def run(self):
        print "Starting "
        time.sleep(2)
        print "Exiting "


# Create new threads
thread1 = myThread()

# Start new Threads
thread1.start()


print "Exiting Main Thread"