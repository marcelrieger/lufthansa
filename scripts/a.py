#!/usr/bin/env python
# -*- coding: utf-8 -*-

HOST = "137.226.151.148"
PORT = 4223

NUM_LEDS = 16

r = [0]*NUM_LEDS
g = [0]*NUM_LEDS
b = [0]*NUM_LEDS
r_index = 0

import time
from tinkerforge.ip_connection import IPConnection
from tinkerforge.bricklet_led_strip import LEDStrip

# Frame rendered callback, is called when a new frame was rendered
# We increase the index of one blue LED with every frame
def cb_frame_rendered(ls, length):
    global r_index

    r_index += 1

    print("Index: "+str(r_index))
    time.sleep(0.5)
    # Set new data for next render cycle
    ls.set_rgb_values(r_index, 1, [0]*16, [255]*16, [0]*16)

if __name__ == "__main__":
    ipcon = IPConnection() # Create IP connection
    ls = LEDStrip("oV7", ipcon) # Create device object

    ipcon.connect(HOST, PORT) # Connect to brickd
    # Don't use device before ipcon is connected

    # Set frame duration to 50ms (20 frames per second)
    ls.set_frame_duration(50)

    # Register frame rendered callback to function cb_frame_rendered
    ls.register_callback(ls.CALLBACK_FRAME_RENDERED,
                         lambda x: cb_frame_rendered(ls, x))

    # Set initial rgb values to get started
    ls.set_rgb_values(0, NUM_LEDS, r, g, b)

    raw_input('Press key to exit\n') # Use input() in Python 3
    ipcon.disconnect()