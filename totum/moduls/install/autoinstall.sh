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
echo -e "\033[43m\033[30m   TOTUM AUTOINSTALL SCRIPT                                              \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   This install script will help you to install Totum online             \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   \033[43m\033[31mONLY ON CLEAR!!! Ubuntu 20 \033[43m\033[30mwith SSL certificate and DKIM.             \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   For success you have to \033[43m\033[31mDELEGATE A VALID DOMAIN \033[43m\033[30mto this server.       \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m   If you not shure about you domain — cansel this install and check:    \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[31m   ping YOU_DOMAIN                                                       \033[0m"
echo -e "\033[43m\033[30m                                                                         \033[0m"
echo -e "\033[43m\033[30m- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -\033[0m"
echo

read -p "If you ready to go, type (A) or cancel (Ctrl + C) and check you domain with ping: " TOTUMRUN

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

TOTUMTIMEZONE=$(tzselect)


read -p "Create password for database: " TOTUMBASEPASS

read -p "Enter your email: " CERTBOTEMAIL

read -p "Create Totum superuser password: " TOTUMADMINPASS

read -p "Enter domain without http/https delegated! to this server like totum.online: " CERTBOTDOMAIN

echo
echo "1) EN"
echo "2) RU"
echo "3) ZH (by snmin)"
echo

read -p "Select language: " TOTUMLANG

if [[ $TOTUMLANG -eq 1 ]]
then
  TOTUMLANG=en
elif [[ $TOTUMLANG -eq 2 ]]
then
  TOTUMLANG=ru
elif [[ $TOTUMLANG -eq 3 ]]
then
  TOTUMLANG=zh
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
echo -e "\033[1mDomain:\033[0m " $CERTBOTDOMAIN
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

if [[ $(sudo certbot --version 2>&1 | grep -c 'command not found') -eq 1 ]]
then
sudo apt -y install certbot
else
echo "Certbot are installed."
fi

echo
echo -e "\033[41m Please answer YES to the following two questions. To continue, press [Enter]: \033[0m"
echo

read TOTUMDUMMY


sudo iptables -A INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 443 -j ACCEPT

sudo apt -y install iptables-persistent

sudo service netfilter-persistent save

echo
echo "Check domain..."
echo

CERTBOTANSWER=$(sudo certbot certonly --standalone --dry-run --register-unsafely-without-email --agree-tos -d $CERTBOTDOMAIN)

if [[ $(echo $CERTBOTANSWER | grep -c 'The dry run was successful.') -eq 1 ]]
then
echo
echo "Domain is OK!"
echo
else
echo
echo "Certbot did't get certificate for you domain:"
echo
echo $CERTBOTDOMAIN
echo
echo "Check DNS for you domain and try again! If you setup NS or DNS less than 3 hours ago, maybe these changes have not reached the Let's encrypt servers. Wait one hour and try again"
echo
echo $CERTBOTANSWER
echo
  exit 0
fi

# Prepare

sudo apt update
sudo apt -y install software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt -y install git unzip curl nano htop wget mc

sudo useradd -s /bin/bash -m totum

sudo timedatectl set-timezone $TOTUMTIMEZONE

# Install PHP

sudo apt -y install php8.0 php8.0-bcmath php8.0-cli php8.0-curl php8.0-fpm php8.0-gd php8.0-mbstring php8.0-opcache php8.0-pgsql php8.0-xml php8.0-zip php8.0-soap
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


# Obtain SSL cert 

sudo curl -O https://raw.githubusercontent.com/totumonline/totum-mit-docker/main/certbot/etc_letsencrypt/cli.ini
sudo chown root:root ./cli.ini
sudo mv ./cli.ini /etc/letsencrypt/cli.ini

sudo certbot register --email $CERTBOTEMAIL --agree-tos --no-eff-email
sudo certbot certonly -d $CERTBOTDOMAIN

sudo curl -O https://raw.githubusercontent.com/totumonline/totum-mit-docker/main/nginx_fpm_conf/totum_nginx_SSL.conf
sudo chown root:root ./totum_nginx_SSL.conf
sudo mv ./totum_nginx_SSL.conf /etc/nginx/sites-available/totum.online.conf


SSLDOMAIN=$(sudo find /etc/letsencrypt/live/* -type d)
SSLDOMAIN=$(basename $SSLDOMAIN)
sudo sed -i "s:YOU_DOMAIN:${SSLDOMAIN}:g" /etc/nginx/sites-available/totum.online.conf
sudo sed -i "s:/var/www/:/home/totum/:g" /etc/nginx/sites-available/totum.online.conf

sudo service nginx restart

sudo bash -c "echo -e '49 */12 * * * certbot renew --quiet --allow-subset-of-names' | crontab -u root -"

# Install Exim

sudo apt -y install exim4

# Create DKIM

sudo mkdir /home/totum/dkim
cd /home/totum/dkim
sudo openssl genrsa -out private.pem 1024
sudo openssl rsa -pubout -in private.pem -out public.pem
sudo openssl pkey -in private.pem -out domain.key
sudo chown -R Debian-exim:Debian-exim /home/totum/dkim
sudo chmod 644 domain.key
sudo cat public.pem | sudo bash -c "tr -d '\n' > key_for_dkim.txt"
sudo sed -i "s:-----BEGIN PUBLIC KEY-----::g" key_for_dkim.txt
sudo sed -i "s:-----END PUBLIC KEY-----::g" key_for_dkim.txt
DKIMKEY=$(sudo cat key_for_dkim.txt)
sudo bash -c "echo -e 'Add TXT record for DKIM:\n\nmail._domainkey.${CERTBOTDOMAIN}\n\nv=DKIM1; k=rsa; t=s; p=PUBLIC_KEY\n\nAdd TXT record for SPF:\n\nv=spf1 ip4:$(curl ifconfig.me/ip) ~all\n\nMost hoster's have port 25 for sending emails blocked by default to combat spam - check with your hoster's support to see what you need to do to get them to unblock your emails.' > TXT_record_for_domain.txt"
sudo sed -i "s:PUBLIC_KEY:${DKIMKEY}:g" TXT_record_for_domain.txt
cd ~

# config Exim4 here

sudo curl -O https://raw.githubusercontent.com/totumonline/totum-mit/master/totum/moduls/install/exim4.conf.template
sudo chown root:root ./exim4.conf.template
sudo mv ./exim4.conf.template /etc/exim4/exim4.conf.template

sudo curl -O https://raw.githubusercontent.com/totumonline/totum-mit/master/totum/moduls/install/update-exim4.conf.conf
sudo chown root:root ./update-exim4.conf.conf
sudo mv ./update-exim4.conf.conf /etc/exim4/update-exim4.conf.conf

sudo echo "${CERTBOTDOMAIN}" > /etc/mailname

sudo update-exim4.conf
sudo service exim4 restart

# Show notification about DKIM and SPF

echo
echo -e "\033[41m --- IMPORTANT! --- \033[40m"
echo

sudo cat /home/totum/dkim/TXT_record_for_domain.txt

echo
echo -e "\033[0m\033[41m ------ END! ------ \033[0m"
echo


# Final text

echo
echo -e "\033[32m ------ DONE! ------ \033[0m"
echo
echo -e "\033[32m NOW YOU CAN OPEN YOU BROWSER AT \033[0mhttps://"$CERTBOTDOMAIN "\033[32mAND LOGIN AS \033[0madmin \033[32mAND \033[0m"$TOTUMADMINPASS
echo
