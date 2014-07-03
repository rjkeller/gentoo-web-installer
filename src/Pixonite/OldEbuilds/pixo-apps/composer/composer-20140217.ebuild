EAPI="5"

SLOT="0"

DESCRIPTION="Pixonite - Composer"
HOMEPAGE="http://www.pixonite.com"

SLOT="0"
KEYWORDS="~amd64 ~x86"

REQUIRED_USE=""
IUSE="${REQUIRED_USE} systemd openrc"

RDEPEND="
    >=dev-lang/php-5.5.7[phar]
    net-misc/curl
    "

src_unpack() {
    mkdir -p "${S}";
}

src_install() {
    ## INSTALL COMPOSER
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin
    mv /bin/composer.phar /usr/local/bin/composer
}
