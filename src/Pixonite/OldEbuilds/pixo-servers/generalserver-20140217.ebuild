EAPI="5"

SLOT="0"

DESCRIPTION="Stafford"
HOMEPAGE="http://www.staffordholdings.com"
SRC_URI="..."

SLOT="0"
KEYWORDS="alpha amd64 hppa ia64 ppc ppc64 sparc x86"

DEPEND="dev-python/pip"

REQUIRED_USE="systemd -openrc iptables dbus -llvm"
IUSE="${REQUIRED_USE} vmware systemd openrc pixo-devtools pixo-hardened pixo-"

RDEPEND="
	sys-apps/systemd
	dhcp? (net-misc/dhcpcd)
	sys-process/cronie
	app-editors/vim
	net-misc/ntp
	app-admin/sudo
	net-firewall/iptables
	net-analyzer/fail2ban

	sys-process/htop
	sys-process/iotop
	sys-boot/grub

	vmware? (app-emulation/open-vm-tools)
"

src_unpack() {
	mkdir -p "${S}";
}

src_compile() {

}

src_install() {
	echo 'America/Los_Angeles' > /etc/timezone

	echo 'en_US ISO-8859-1
en_US.UTF-8 UTF-8' > /etc/locale.gen
	env-update && source /etc/profile
	locale-gen

	eselect locale list
	eselect locale set 5
	env-update && source /etc/profile

	ln -s /usr/lib/systemd/system/cronie.service /etc/systemd/system/multi-user.target.wants/

	ln -s /usr/lib/systemd/system/sshd.service /etc/systemd/system/multi-user.target.wants/

	ln -s /usr/lib/systemd/system/ntpd.service /etc/systemd/system/multi-user.target.wants/

	ln -s /usr/lib/systemd/system/fail2ban.service /etc/systemd/system/multi-user.target.wants/

	chmod +w /etc/sudoers
	echo '%admin ALL=(ALL) ALL
' >> /etc/sudoers
	chmod -w /etc/sudoers

	groupadd admin
useradd -G admin rjkeller
mkdir -p /home/rjkeller/.ssh
chown -R rjkeller:rjkeller /home/rjkeller
sed -i 's/#PermitRootLogin yes/PermitRootLogin no/g' /etc/ssh/sshd_config
echo '456123
456123' | passwd root
echo '456123
456123' | passwd rjkeller

grub2-install /dev/sdc
echo '
GRUB_CMDLINE_LINUX="init=/usr/lib/systemd/systemd"
' >> /etc/default/grub
grub2-mkconfig -o /boot/grub/grub.cfg

	eselect editor list
	eselect editor set 3
	env-update && source /etc/profile
}
