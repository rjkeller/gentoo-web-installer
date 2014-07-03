EAPI="5"

SLOT="0"

DESCRIPTION="Pixonite Apache2"
HOMEPAGE="http://www.pixonite.com"

SLOT="0"
KEYWORDS="~amd64 ~x86"

RDEPEND="www-servers/apache
    >=dev-lang/php-5.5.7[hash phar crypt pdo gd imap mysql]"

src_unpack() {
    mkdir -p "${S}";
}

src_install() {
    echo '

#PIXONITE SETTINGS
ServerName $(hostname)
KeepAlive On
MaxKeepAliveRequests 100
KeepAliveTimeout 15

StartServers       8
MinSpareServers    5
MaxSpareServers   20
ServerLimit      256
MaxClients       256
MaxRequestsPerChild  4000

Listen 80
' >> /etc/apache2/httpd.conf

    sed -i 's/-D DEFAULT_VHOST -D INFO/-D DEFAULT_VHOST -D INFO -D PHP5/g' /etc/conf.d/apache2

    ln -s /usr/lib/systemd/system/apache2.service /etc/systemd/system/multi-user.target.wants

    chmod -R 777 /var/www/staffordholdings.com/app/cache
    chmod -R 777 /var/www/staffordholdings.com/app/logs/
}
