#!/bin/bash

# fpp-plugin-Template install script

# Mark to reboot
sed -i -e "s/^restartFlag .*/restartFlag = 1/" ${FPPHOME}/media/settings