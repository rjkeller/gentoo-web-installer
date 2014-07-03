EAPI="5"

SLOT="0"

DESCRIPTION="Pixonite DevTools"
HOMEPAGE="http://www.pixonite.com"

SLOT="0"
KEYWORDS="~amd64 ~x86"

REQUIRED_USE="mariadb"
IUSE="${REQUIRED_USE} systemd openrc"

RDEPEND="dev-php/xdebug
    pixo-apps/sass
    pixo-apps/composer
    git
    subversion
    "

src_unpack() {
    mkdir -p "${S}";
}
