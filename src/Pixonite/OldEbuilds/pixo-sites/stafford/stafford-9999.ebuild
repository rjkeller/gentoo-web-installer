EAPI="5"

SLOT="0"

DESCRIPTION="Stafford"
HOMEPAGE="http://www.staffordholdings.com"
SRC_URI="..."

SLOT="0"
KEYWORDS="alpha amd64 hppa ia64 ppc ppc64 sparc x86"

DEPEND="dev-python/pip"

REQUIRED_USE="php json curl ruby_targets_ruby21 intl iptables mhash mariadb"
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

	dev? (dev-php/xdebug
    	pixo-apps/sass
    	pixo-apps/composer
    	git
    	subversion)
	"

src_unpack() {
	mkdir -p "${S}";
}

src_compile() {

}

src_install() {
	mkdir -p /var/www/staffordholdings.com
	aws s3 cp s3://pixosites/stafford/public_html/ /var/www/staffordholdings.com/ --recursive

	rm -rf /var/www/staffordholdings.com/app/cache
	rm -rf /var/www/staffordholdings.com/app/logs
	mkdir /var/www/staffordholdings.com/app/cache
	mkdir /var/www/staffordholdings.com/app/logs

	chmod -R 777 /var/www/staffordholdings.com/app/cache
	chmod -R 777 /var/www/staffordholdings.com/app/logs

	chown -R rjkeller:rjkeller /var/www
	rm -rf /etc/apache2/vhosts.d/001-staffordholdings.com.conf
	aws s3 cp s3://pixosites/stafford/config_files/apache2/httpd.conf /etc/apache2/vhosts.d/001-staffordholdings.com.conf


	if use systemd ; then
		ln -s /usr/lib/systemd/system/cronie.service /etc/systemd/system/multi-user.target.wants
		ln -s /usr/lib/systemd/system/apache2.service /etc/systemd/system/multi-user.target.wants
        ln -s /usr/lib/systemd/system/mysqld.service /etc/systemd/system/multi-user.target.wants
	fi
	if use openrc ; then
		rc-update add cronie default
		rc-update add apache2 default
		rc-update add mysql default
	fi
}
