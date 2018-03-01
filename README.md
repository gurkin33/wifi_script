It is the first version of the script - 1.2.0

clone from git do:
* touch log.txt
* crontab -e
* add line <i><b>*/2 * * * * php /var/www/html/wifi_script/script.php > /var/www/html/wifi_script/log.txt</b></i>


For Debug

http://<ip>:8080/wifi_script/script.php?debug=1
