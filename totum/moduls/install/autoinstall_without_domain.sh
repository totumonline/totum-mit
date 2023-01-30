#!/bin/bash


if [[ $(sudo ps aux | grep -i apt | wc -l) -ne 1 ]]
then
  echo
  echo "THIS SERVER IS NOW INSTALLING SOMETHING, WAIT 5 MIN AND TRY AGAIN"
  echo
  exit 0
else
  echo
  echo "APT is OK..."
  echo
fi

if [[ $(sudo cat /etc/issue | grep -c 'Ubuntu 20') -ne 1 ]]
then
  echo
  echo "THIS SERVER IS NOT A UBUNTU 20. CHECK: sudo cat /etc/issue"
  echo
  exit 0
else
  echo
  echo "Ubuntu version is OK. Let's go..."
  echo
fi

if [[ $(sudo locale | grep -c 'LANG=en_US.UTF-8') -ne 1 ]]
then
  echo
  echo -e "\e[40;1;37mTHIS SERVER HAVE NOT \e[40;1;31men_US.UTF-8\e[40;1;37m LOCALE. YOU HAVE TO EXECUTE:"
  echo
  echo -e "sudo curl -O https://raw.githubusercontent.com/totumonline/totum-mit/master/totum/moduls/install/setlocale.sh && sudo bash setlocale.sh" 
  echo
  echo -e "AND FOLLOW THE ON-SCREEN INSTRUCTIONS TO SETUP THE CORRECT LOCALE\033[0m"
  echo
  read -p "If you ready to go, type (A) we will download and run setlocale.sh: " TOTUMLOCALE
else
  TOTUMLOCALE="RUN"
fi

if [[ $TOTUMLOCALE = "A" ]]
then
echo
sudo curl -O https://raw.githubusercontent.com/totumonline/totum-mit/master/totum/moduls/install/setlocale.sh && sudo bash setlocale.sh
echo
elif [[ $TOTUMLOCALE = "a" ]]
then
echo
sudo curl -O https://raw.githubusercontent.com/totumonline/totum-mit/master/totum/moduls/install/setlocale.sh && sudo bash setlocale.sh
echo
elif [[ $TOTUMLOCALE = "RUN" ]]
then
echo
echo "Locale is OK. Let's go..."
echo
else
echo
  exit 0
fi


echo -e "\e[40;1;37m                                                                         \033[0m"
echo -e "\e[40;1;37m                       ..       .*:-.                                    \033[0m"
echo -e "\e[40;1;37m                      -*:-.     -+*:.   .*:-.                            \033[0m"
echo -e "\e[40;1;37m                      -+*:-     -**:.   :+*-.                            \033[0m"
echo -e "\e[40;1;37m                      .++*-.    -*:-.   :**-.      ..-.                  \033[0m"
echo -e "\e[40;1;37m                       :+*:.    -**:.   **:-.     .:::-                  \033[0m"
echo -e "\e[40;1;37m                       .*+**.   :**:.  .++:-     ..***:                  \033[0m"
echo -e "\e[40;1;37m                        ***:-.  ::::.  -:::-    .:-::.                   \033[0m"
echo -e "\e[40;1;37m             ::--.      .*++::. :*::. -*::-.  .-*+**.                    \033[0m"
echo -e "\e[40;1;37m             -+:--.      .+:::-:::---:**+:. .::::::.                     \033[0m"
echo -e "\e[40;1;37m              **:-.       *+***::*::*:-::::::-+**-                       \033[0m"
echo -e "\e[40;1;37m              -+*:--.    .*::-------::::::::**+-                         \033[0m"
echo -e "\e[40;1;37m               -++:--------:-----------------::                          \033[0m"
echo -e "\e[40;1;37m                .++*::-----------------------::                          \033[0m"
echo -e "\e[40;1;37m                 .*++***:--------------------::                          \033[0m"
echo -e "\e[40;1;37m                   -+++++*::::::::**::::---:**:                          \033[0m"
echo -e "\e[40;1;37m                      -*+++++++++++*+++:::-:**.                          \033[0m"
echo -e "\e[40;1;37m                        .*+++++++++***+++****:                           \033[0m"
echo -e "\e[40;1;37m                           -++++++++***++++++.                           \033[0m"
echo -e "\e[40;1;37m                            *++++++*++++++++:                            \033[0m"
echo -e "\e[40;1;37m                            :++++++*++***+++-                            \033[0m"
echo -e "\e[40;1;37m                            -+*++++++++***+*:                            \033[0m"
echo -e "\e[40;1;37m                            -**+**+***+***+*:                            \033[0m"
echo -e "\e[40;1;37m                            -******::****:**:                            \033[0m"
echo -e "\e[40;1;37m                                                                         \033[0m"
echo -e "\033[43m\033[30m- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   TOTUM AUTOINSTALL SCRIPT !WITHOUT DOMAIN!                             \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   This install script will help you to install Totum online             \033[0m" 
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   \033[43m\033[31mONLY ON CLEAR!!! Ubuntu 20 \033[43m\033[30mwithout SSL and valid domain.              \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   For email you need to configure you SMTP in Conf.php in               \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[31m   /home/totum/totum-mit/Conf.php                                        \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\033[0m"
echo

read -p "If you ready to go, type (A) or cancel (Ctrl + C): " TOTUMRUN

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

CERTBOTDOMAIN=$(curl ifconfig.me/ip)

echo
echo -e "\033[41mIMPORTANT!!! Look at the next step. If after installation you see the error «schema not found» you can change it in /home/totum/totum-mit/Conf.php\033[0m"
echo
echo -e "Server IP detected as \033[1m>>> ${CERTBOTDOMAIN} <<<\033[0m "
echo
read -p "If it right type (A) or type your custom IP or localhost for access to Totum after install: " CERTBOTDOMAIN_CHECK
echo

if [[ $CERTBOTDOMAIN_CHECK = "A" ]]
then
echo
echo -e "IP selected as \033[1m${CERTBOTDOMAIN}\033[0m"
echo
elif [[ $TOTUMRUN = "a" ]]
then
echo
echo -e "IP selected as \033[1m${CERTBOTDOMAIN}\033[0m"
echo
else
echo
CERTBOTDOMAIN=${CERTBOTDOMAIN_CHECK}
echo -e "IP selected as \033[1m${CERTBOTDOMAIN}\033[0m"
echo
fi


TOTUMTIMEZONE=$(tzselect)

read -p "Create password for database: " TOTUMBASEPASS

read -p "Enter your email: " CERTBOTEMAIL

read -p "Create Totum superuser password: " TOTUMADMINPASS

echo
echo "1) EN"
echo "2) RU"
echo

read -p "Select language: " TOTUMLANG

if [[ $TOTUMLANG -eq 1 ]]
then
  TOTUMLANG=en
elif [[ $TOTUMLANG -eq 2 ]]
then
  TOTUMLANG=ru
else
  TOTUMLANG=en
fi

echo
echo "- - - - - - - - - - - - - - - - - - - - - -"
echo
echo -e "\033[1mCheck you settings:\033[0m"
echo
echo -e "\033[1mTimezone:\033[0m " $TOTUMTIMEZONE
echo
echo -e "\033[1mPass for database:\033[0m "$TOTUMBASEPASS
echo
echo -e "\033[1mEmail:\033[0m " $CERTBOTEMAIL
echo
echo -e "\033[1mPass for Totum admin:\033[0m " $TOTUMADMINPASS
echo
echo -e "\033[1mInstall ip:\033[0m " $CERTBOTDOMAIN
echo
echo -e "\033[1mLang:\033[0m " $TOTUMLANG
echo
echo "- - - - - - - - - - - - - - - - - - - - - - -"
echo

read -p "If you ready to install with this params type (A) or cancel Ctrl + C: " TOTUMRUN2

if [[ $TOTUMRUN2 = "A" ]]
then
echo
echo "Start installation"
echo
elif [[ $TOTUMRUN2 = "a" ]]
then
echo
echo "Start installation"
echo
else
echo
  exit 0
fi

echo
echo -e "\033[41m Please answer YES to the following two questions. To continue, press [Enter]: \033[0m"
echo

read TOTUMDUMMY


sudo iptables -A INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 443 -j ACCEPT

sudo apt -y install iptables-persistent

sudo service netfilter-persistent save

# Prepare

sudo apt update
sudo apt -y install software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt -y install git unzip curl nano htop wget mc

sudo useradd -s /bin/bash -m totum

sudo timedatectl set-timezone $TOTUMTIMEZONE

# Install PHP

sudo apt -y install php8.0 php8.0-bcmath php8.0-cli php8.0-curl php8.0-fpm php8.0-gd php8.0-mbstring php8.0-opcache php8.0-pgsql php8.0-xml php8.0-zip php8.0-soap php8.0-ldap
sudo service apache2 stop
sudo systemctl disable apache2
sudo curl -O https://raw.githubusercontent.com/totumonline/totum-mit-docker/main/nginx_fpm_conf/totum_fpm.conf
sudo chown root:root ./totum_fpm.conf
sudo mv ./totum_fpm.conf /etc/php/8.0/fpm/pool.d/totum.conf
sudo sed -i "s:Europe/London:${TOTUMTIMEZONE}:g" /etc/php/8.0/fpm/pool.d/totum.conf
sudo mkdir /var/lib/php/sessions_totum
sudo chown root:root /var/lib/php/sessions_totum
sudo chmod 1733 /var/lib/php/sessions_totum
sudo rm /etc/php/8.0/fpm/pool.d/www.conf
sudo service php8.0-fpm restart

# Install Postgres

sudo apt -y install postgresql
cd /
sudo -u postgres psql -c "CREATE USER totum WITH ENCRYPTED PASSWORD '${TOTUMBASEPASS}';"
sudo -u postgres psql -c "CREATE DATABASE totum;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE totum TO totum;"
cd ~

# Install Nginx

sudo apt -y install nginx
sudo curl -O https://raw.githubusercontent.com/totumonline/totum-mit-docker/main/nginx_fpm_conf/totum_nginx.conf
sudo chown root:root ./totum_nginx.conf
sudo mv ./totum_nginx.conf /etc/nginx/sites-available/totum.online.conf
sudo sed -i "s:/var/www/:/home/totum/:g" /etc/nginx/sites-available/totum.online.conf
sudo curl -O https://raw.githubusercontent.com/totumonline/ttmonline-mit-image/main/certbot/acme
sudo chown root:root ./acme
sudo mv ./acme /etc/nginx/acme
sudo mkdir -p /var/www/html/.well-known/acme-challenge
sudo rm /etc/nginx/sites-available/default
sudo rm /etc/nginx/sites-enabled/default
sudo ln -s /etc/nginx/sites-available/totum.online.conf /etc/nginx/sites-enabled/totum.online.conf
sudo service nginx restart

# Install Totum from user Totum

sudo -u totum bash -c "git clone https://github.com/totumonline/totum-mit.git /home/totum/totum-mit"
sudo -u totum bash -c "php -r \"copy('https://getcomposer.org/installer', '/home/totum/totum-mit/composer-setup.php');\""
cd /home/totum/totum-mit
sudo -u totum bash -c "php /home/totum/totum-mit/composer-setup.php --quiet"
sudo -u totum bash -c "rm /home/totum/totum-mit/composer-setup.php"
sudo -u totum bash -c "php /home/totum/totum-mit/composer.phar install --no-dev"

sudo -u totum bash -c "/home/totum/totum-mit/bin/totum install --pgdump=pg_dump --psql=psql -e -- ${TOTUMLANG} multi totum ${CERTBOTEMAIL} ${CERTBOTDOMAIN} admin ${TOTUMADMINPASS} totum localhost totum ${TOTUMBASEPASS}"

sudo bash -c "echo -e '* * * * * cd /home/totum/totum-mit/ && bin/totum schemas-crons\n*/10 * * * * cd /home/totum/totum-mit/ && bin/totum clean-tmp-dir\n*/10 * * * * cd /home/totum/totum-mit/ && bin/totum clean-schemas-tmp-tables' | crontab -u totum -"

sudo -u totum bash -c "openssl rand -base64 64 > /home/totum/totum-mit/Crypto.key"

# Replace Sendmail trait by SMTP trait

sudo sed -i "s:use WithPhpMailerTrait;:use WithPhpMailerSmtpTrait;\nprotected \$SmtpData = [\n'host' => 'YOU_HOST_HERE',\n'port' => 25,\n'login' => 'YOU_LOGIN_HERE',\n'pass' => 'YOU_PASS_HERE',\n];:g" /home/totum/totum-mit/Conf.php
sudo sed -i "s:WithPhpMailerTrait;:WithPhpMailerSmtpTrait;:g" /home/totum/totum-mit/Conf.php

# Show notification about DKIM and SPF

echo
echo -e "\033[41m --- IMPORTANT! --- \033[40m"
echo
echo -e "For SMTP setup open /home/totum/totum-mit/Conf.php and fill SMTP settings!"
echo
echo -e "\033[0m\033[41m ------ END! ------ \033[0m"
echo


# Final text

echo
echo -e "\033[32m ------ DONE! ------ \033[0m"
echo
echo -e "\033[32m NOW YOU CAN OPEN YOU BROWSER AT \033[0mhttp://"$CERTBOTDOMAIN "\033[32mAND LOGIN AS \033[0madmin \033[32mAND \033[0m"$TOTUMADMINPASS
echo
