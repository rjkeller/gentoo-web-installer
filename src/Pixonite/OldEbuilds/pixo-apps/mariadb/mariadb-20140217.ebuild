EAPI="5"

SLOT="0"

DESCRIPTION="Pixonite MariaDb"
HOMEPAGE="http://www.pixonite.com"

SLOT="0"
KEYWORDS="~amd64 ~x86"

REQUIRED_USE="mariadb"
IUSE="${REQUIRED_USE} systemd openrc"

RDEPEND="dev-db/mariadb"

src_unpack() {
    mkdir -p "${S}";
}

src_install() {

    #####
    # set default passwords
    #####
    echo "456123
456123" | mysql_secure_installation

    /etc/init.d/mysql start

    echo "
CREATE USER 'prod'@'%' IDENTIFIED BY '456123';
GRANT ALL PRIVILEGES ON *.* TO 'prod'@'%';

CREATE USER 'rjkeller'@'%' IDENTIFIED BY '456123';
GRANT ALL PRIVILEGES ON *.* TO 'rjkeller'@'%';

" > /tmp/mysql

    mysql -u root -p456123 < /tmp/mysql
    rm -f /tmp/mysql
    /etc/init.d/mysql stop

    #####
    # enable MySQL by default
    #####
	if use systemd ; then
    	ln -s /usr/lib/systemd/system/mysql.service /etc/systemd/system/multi-user.target.wants
    fi
    if use openrc ; then
		rc-update add apache2 default
	fi
}
