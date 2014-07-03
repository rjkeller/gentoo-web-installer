EAPI="5"

SLOT="0"

DESCRIPTION="Stafford"
HOMEPAGE="http://www.staffordholdings.com"
SRC_URI="..."

SLOT="0"
KEYWORDS="alpha amd64 hppa ia64 ppc ppc64 sparc x86"

DEPEND="dev-python/pip"

REQUIRED_USE="apache2 php json curl ruby_targets_ruby21 intl iptables mhash mariadb"
IUSE="${REQUIRED_USE} vmware systemd openrc pixo-devtools pixo-hardened pixo-"

RDEPEND="
	=dev-lang/php-5.5.*[hash phar crypt pdo gd imap mysql]
	=www-servers/apache-2.4.*
	=dev-db/redis-2.8.*
	=dev-db/mariadb-5.5.*

	systemd? (sys-apps/systemd)
	sys-process/cronie

	pixo-apps/mariadb
	pixo-apps/apache

	pixo-devtools? (pixo-apps/devtools)"

src_unpack() {
	mkdir -p "${S}";
}

src_compile() {
	mkdir -p /var/www/stafford
	aws s3 cp s3://pixosites/stafford/public_html/ /var/www/stafford/ --recursive

	rm -rf /var/www/stafford/app/cache
	rm -rf /var/www/stafford/app/logs
	mkdir /var/www/stafford/app/cache
	mkdir /var/www/stafford/app/logs

	chmod -R 777 /var/www/stafford/app/cache /var/www/stafford/app/logs

	chown -R rjkeller:rjkeller /var/www
	rm -rf /etc/apache2/vhosts.d/*
	aws s3 cp s3://pixosites/stafford/config_files/apache2/httpd.conf /etc/apache2/vhosts.d/stafford.conf

	if use vmware ; then
		sed -i 's/\/var\/www/\/mnt\/hgfs\/Sites/g' /etc/apache2/vhosts.d/stafford.conf
		sed -i 's/www.staffordholdings.com/stafford.". $this->server->hostname ."/g' /etc/apache2/vhosts.d/stafford.conf
	fi
}

src_install() {
	if use systemd ; then
		ln -s /usr/lib/systemd/system/cronie.service /etc/systemd/system/multi-user.target.wants
	fi
	if use openrc ; then
		rc-update add cronie default
	fi
}
