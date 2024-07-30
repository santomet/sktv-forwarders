#!/bin/bash
#This script generates a set of .ts videos from unavailable.png

ffmpeg -loop 1 -i unavailable.png -c:v libx264 -preset placebo -tune stillimage -t 00:00:30 -r 1 -g 1000 -pix_fmt yuv420p unavailable_1080p.ts
ffmpeg -loop 1 -i unavailable.png -s 640x360 -c:v libx264 -preset placebo -tune stillimage -t 00:00:30 -r 1 -g 1000 -pix_fmt yuv420p unavailable_360p.ts
