EAPI="5"

SLOT="0"

DESCRIPTION="Pixonite - Composer"
HOMEPAGE="http://www.pixonite.com"

SLOT="0"
KEYWORDS="~amd64 ~x86"

REQUIRED_USE=""
IUSE="${REQUIRED_USE} systemd openrc"

RDEPEND="dev-ruby/rubygems
    "

src_unpack() {
    mkdir -p "${S}";
}

src_install() {
    ## INSTALL SASS/COMPASS
    gem update system
    gem install sass
    gem install compass
    gem install zurb-foundation
}
