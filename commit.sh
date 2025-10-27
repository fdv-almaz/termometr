#!/bin/bash

git status
git add --all
git status
git commit -m "changes in board_with_sensors_data_sent_to_the_server.ino"
git status
git push -u origin main
git status
