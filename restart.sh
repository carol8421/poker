#!/bin/bash
kill -9 $(ps -aux|grep server.php |grep -v grep | awk '{print $2}')
php server.php &
exit
