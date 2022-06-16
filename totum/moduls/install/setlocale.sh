#!/bin/bash

echo
echo -e "\e[40;1;37m                                                                                                   \033[0m"
echo -e "\e[40;1;37m   This script will install en_US.UTF-8 locale to this server.                                     \033[0m"
echo -e "\e[40;1;37m   On first screen you have to put a star ONLY near [en_US.UTF-8 UTF-8]. You can do it by SPACE.   \033[0m"
echo -e "\e[40;1;37m   For change cursor to OK press TAB and ENTER to confirm.                                         \033[0m"
echo -e "\e[40;1;37m   On second screen you must select [en_US.UTF-8].                                                 \033[0m"
echo -e "\e[40;1;37m   After locale have been installed server will be rebooted.                                       \033[0m"
echo -e "\e[40;1;37m                                                                                                   \033[0m"
echo


read -p "If you ready to go, type (A): " TOTUMRUN

if [[ $TOTUMRUN = "A" ]]
then
echo
echo "Started"
echo
elif [[ $TOTUMRUN = "a" ]]
then
echo
echo "Started"
echo
else
echo
  exit 0
fi

sudo dpkg-reconfigure locales

read -p "Licale is set. Press Enter and server will be rebooted. New locale will be applyed only after reboot! Press [Enter]:" TOTUMRUN

sudo reboot
