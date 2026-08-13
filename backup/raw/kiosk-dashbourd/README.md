# 📝 Description
Dashbourdtool, um  Dashbourd anzuzeigen.

> [!NOTE]
> usefull with [php-auth](https://github.com/florianthepro/php-auth/tree/main)(edit.php) and [kiosk-pi](https://github.com/florianthepro/kiosk-pi/tree/main)

> [!TIP]
> Apache+php:
> ```
> sudo apt update \
>&& sudo apt install -y apache2 \
>&& sudo systemctl enable --now apache2 \
>&& sudo apt install -y php libapache2-mod-php php-cli php-common php-mysql php-xml php-curl php-gd php-mbstring \
>&& sudo systemctl reload apache2
> ```
> Beschreibbar machen:
>```
>sudo usermod -aG www-data #user
>sudo chown -R #user:www-data /var/www/html/#path/
>sudo find /var/www/html/#path/ -type d -exec chmod 770 {} \;
>sudo find /var/www/html/#path/ -type f -exec chmod 660 {} \;
>```
