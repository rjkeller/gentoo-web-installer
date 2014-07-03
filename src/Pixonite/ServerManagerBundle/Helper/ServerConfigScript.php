<?php
/**
 * Gentoo Server Manager Project
 * Copyright (C) 2014, Roger L Keller (rjkellercode@pixonite.com),
 * All rights reserved.
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3.0 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.
 */
namespace Pixonite\ServerManagerBundle\Helper;

use Oranges\MasterContainer;

use Pixonite\ServerManagerBundle\Helper\InitManagers\SystemD;
use Pixonite\ServerManagerBundle\Entity\Server;

/**
 * This class provides all of the functions for building Gentoo installs.
 */
class ServerConfigScript
{
	private $_server;
	private $_deviceMapping = array(
		'sd' => 'vd'
	);
	private $_fileSystem = "ext4";

	private $_mountPoints = array();

	private $_initManager = null;

	private $_ipAddr = null;
	private $_site = null;

	public function __construct(Server $server) {
		$em = MasterContainer::get('doctrine')->getManager();

		$this->_server = $server;

		$this->_ipAddr = $em->getRepository("PixoniteServerManagerBundle:IpAddress")
				->findOneByPublicIp($this->_server->publicIp);

		$this->_site = $em->getRepository("PixoniteServerManagerBundle:Site")
				->findOneById($this->_server->siteId);
	}

	protected function getBuildServer()
	{
		return MasterContainer::getParameter("build_server_host");
	}

	public function addBuildServerToHostsFile()
	{
		if (!MasterContainer::hasParameter("build_server_host"))
			return;
		echo "echo '". MasterContainer::getParameter("build_server_ip") . " ". MasterContainer::getParameter("build_server_host") ."\n' >> /etc/hosts\n";
	}

	public function activateLocalNetwork()
	{
		if ($this->_server->publicIp != null) {

			$ipAddr = $this->_ipAddr;

			echo "ifconfig ". $this->_server->eth0_name ." ". $ipAddr->publicIp ." broadcast ". $ipAddr->broadcast ." netmask ". $ipAddr->netmask ." up\n";
			echo "route add default gw ". $ipAddr->gateway ."\n";
			echo 'echo "nameserver '. $ipAddr->nameserver .'" > /etc/resolv.conf';
			echo "\n";
		}
	}

	public function syncSystemClock()
	{
		echo "service ntpd stop\n";
		echo "ntpdate -s time.nist.gov\n";
		echo "service ntpd start\n";
	}

	public function formatDisks()
	{
		$disks = explode(",", $this->_server->disks);

        if ($this->_server->enableRaid) {
            $diskDeviceNames = "";
            for ($i = 0; $i < count($disks); $i++)
            {
                $diskDeviceNames .= $disks[$i] . "NUM ";
                $this->disk_mkdisk($disks[$i],
                  "
                  mklabel gpt
                  unit mib
                  mkpart primary 1 3
                  set 1 bios_grub on
                  name 1 boot
                  mkpart primary 3 -1
                  name 2 llvm
                  set 2 raid on
                  print
                  quit
                ");
            }

            $numDisks = count($disks);

            echo "mdadm --create /dev/md0 --level=1 --raid-devices=". $numDisks ." ". str_replace('NUM', 2, $diskDeviceNames, $numDisks) ."\n";
            echo "mdadm --assemble --scan\n";

            echo "pvcreate /dev/md0\n";
            echo "vgcreate vg-sata /dev/md0\n";
            echo "lvcreate -L100M -n boot vg-sata\n";
            echo "lvcreate -L512M -n swap vg-sata\n";
            echo "lvcreate -l 100%FREE -n root vg-sata\n";

            $this->disk_mkfs("/dev/vg-sata/root", "/", "std", "0 1");
            $this->disk_mkfs("/dev/vg-sata/boot", "/boot", "boot");
            $this->disk_mkswap("/dev/vg-sata/swap");

            echo "mdadm --detail --scan > /etc/mdadm.conf\n";
        }
        else {
            $this->disk_mkdisk($disks[0],
                "
                mklabel gpt
                unit mib
                mkpart primary 1 3
                set 1 bios_grub on
                name 1 grub
                mkpart primary 3 131
                name 2 boot
                mkpart primary 131 643
                name 3 swap
                mkpart primary 643 -1
                name 4 rootfs
                print
                quit
              ");
            $this->disk_mkfs($disks[0] .'4', "/", "std", "0 1");
            $this->disk_mkfs($disks[0] .'2', "/boot", "boot");
            $this->disk_mkswap($disks[0] .'3');

            for ($i = 1; $i < count($disks); $i++)
            {
                $this->disk_mkdisk($disks[$i],
                  "
                  mklabel gpt
                  unit mib
                  mkpart primary 1 -1
                  print
                  quit
                ");
                $this->disk_mkfs($disks[$i] .'1', "/var/lib/mysql", "std", "0 2");
            }
        }
	}

	public function cmd($cmd) {
		echo $cmd ."\n";
	}

	public function disk_mkdisk($device, $options) {
		echo "echo \"". $options ."\" > /tmp/parted\n";
		echo "parted -a optimal ". $device ." < /tmp/parted\n";
		echo "rm -f /tmp/parted\n";
	}

	public function disk_mkfs($device, $mntPoint, $type, $options = "") {
		echo "mkfs.ext4 ". $device ."\n";
		echo "mkdir -p /mnt/gentoo". $mntPoint ."\n";
		echo "mount ". $device ." /mnt/gentoo". $mntPoint ."\n";

		$this->_mountPoints[] = array(
			"device" => $device,
			"mountPoint" => $mntPoint,
			"type" => $type,
		);
	}

	public function disk_mkswap($device) {
		echo "mkswap ". $device ."\n";
		echo "swapon ". $device ."\n";

		$this->_mountPoints[] = array(
			"device" => $device,
			"type" => "swap",
		);
	}

	public function installStage3($type) {
		$routing = MasterContainer::get('router');
		echo "cd /mnt/gentoo\n";

		if ($type == "amd64-hardened+nomultilib") {
			echo "wget $( echo http://distfiles.gentoo.org/releases/amd64/autobuilds/`curl http://distfiles.gentoo.org/releases/amd64/autobuilds/latest-stage3-amd64-hardened+nomultilib.txt -q | tail -n 1` )\n";
		}
		elseif ($type == "amd64-nomultilib") {
			echo "wget $( echo http://distfiles.gentoo.org/releases/amd64/autobuilds/`curl http://distfiles.gentoo.org/releases/amd64/autobuilds/latest-stage3-amd64-nomultilib.txt -q | tail -n 1` )\n";
		}

		echo "tar xjpf stage3*.tar.bz2\n";
		echo "rm -rf stage3*.tar.bz2\n";

		echo "echo \"nameserver 8.8.8.8\" > /mnt/gentoo/etc/resolv.conf\n";
		$this->addBuildServerToHostsFile();
		echo "wget 'http://". $this->getBuildServer() . $routing->generate('config-files_kernel', array('server_id' => $this->_server->id ))
			."' -O /mnt/gentoo/usr/src/.config\n";
		echo "wget 'http://". $this->getBuildServer() . $routing->generate('config-files_install', array('server_id' => $this->_server->id ))
			."' -O /mnt/gentoo/install.sh\n";

		echo "mount -t proc proc /mnt/gentoo/proc\n";
		echo "mount --rbind /sys /mnt/gentoo/sys\n";
		echo "mount --rbind /dev /mnt/gentoo/dev\n";
		echo "chroot /mnt/gentoo /bin/bash -c \"bash -x /install.sh\"\n";
	}

	public function installPortage() {
		$routing = MasterContainer::get('router');
		$this->addBuildServerToHostsFile();
		echo "wget 'http://". $this->getBuildServer() . $routing->generate('config-files_portage', array('server_id' => $this->_server->id ))
			."' -O /etc/portage/make.conf\n";
		echo "echo '". $this->_site->getPackageUSE() ."' > /etc/portage/package.use\n";
		echo "echo '". $this->_site->getPackageKeywords() ."' > /etc/portage/package.accept_keywords\n";
		echo "echo '". $this->_site->getPackageMask() ."' > /etc/portage/package.mask\n";


		echo "emerge-webrsync\n";
		echo "emerge --sync\n";
	}

	public function eselect($item, $num) {
		if ($item == "gcc-config") {
			echo "gcc-config -l\n";
			echo "gcc-config ". $num ."\n";
		}
		else {
			echo "eselect ". $item . " list\n";
			echo "eselect ". $item . " set ". $num ."\n";
		}

		echo "env-update && source /etc/profile\n";
	}

	public function setTimezone($timezone) {
		echo "echo 'America/Los_Angeles' > /etc/timezone\n";
		echo "emerge --config sys-libs/timezone-data\n";
	}

	public function setLocale($locale) {
		echo "echo 'en_US ISO-8859-1
en_US.UTF-8 UTF-8' > /etc/locale.gen\n";
		echo "env-update && source /etc/profile\n";
		echo "locale-gen\n";
	}

	public function parallelEmerge($allApps) {
		echo "emerge ";
		foreach ($allApps as $app) {
			echo $app;
			echo " ";
		}
		echo "\n";

		foreach ($allApps as $app) {
			echo $this->emerge($app, true);
			echo " ";
		}

	}

	public function emerge($app, $configOnly = false) {
		$routing = MasterContainer::get('router');

		switch ($app) {
			case 'pip:aws':
				echo "pip install awscli\n";

				echo "mkdir -p /root/.aws\n";

				echo 'echo "[default]
region = us-west-1
aws_access_key_id = AKIAIFQQ37ZPD4MBBH5A
aws_secret_access_key = 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
" > /root/.aws/config';
				echo "\n";

			case 'sshd':
				break;

			case 'mariadb/xtrabackup':
				echo "wget http://71.19.151.36/gentoo-files/percona-xtrabackup-2.1.6.tar.gz -O /root/percona.tar.gz\n";
				echo "cd /root\n";
				echo "tar xvfz percona.tar.gz\n";
				echo "cd percona-xtrabackup-2.1.6\n";

				echo "sed -i 's/-g -O0/-lrt -m64 -g -O0/g' /root/percona-xtrabackup-2.1.6/utils/build.sh\n";
				echo 'AUTO_DOWNLOAD="yes" ./utils/build.sh innodb55';
				echo "\n";
				echo "mv src/xtrabackup_innodb55 /usr/bin/xtrabackup\n";
				echo "mv innobackupex /usr/bin/innobackupex\n";

				echo "rm -rf /root/percona*\n";
				echo "cd /\n";

				if (!$this->_site->hasAppFlag('RJ_DEV_TOOLS')) {
					echo "wget 'http://71.19.151.36". $routing->generate('config-files_xtrabackup_hourly')
						."' -O /etc/cron.hourly/xtrabackup.sh\n";
					echo "wget 'http://71.19.151.36". $routing->generate('config-files_xtrabackup_daily')
						."' -O /etc/cron.daily/xtrabackup.sh\n";
				}

				break;

			case 'php:composer':
				echo "curl -sS https://getcomposer.org/installer | php -- --install-dir=/bin\n";
				echo "mv /bin/composer.phar /usr/local/bin/composer\n";
				break;

			default:
				if (!$configOnly)
					echo "emerge ". $app ."\n";
		}

		switch ($app) {
			case 'ntp':
			case 'app-emulation/open-vm-tools':
			case 'cronie':
			case 'sshd':
			case 'iptables':
			case 'fail2ban':
			case 'dev-db/redis':
			case 'www-servers/apache':
			case 'app-antivirus/clamav':
			case 'dev-db/mongodb':
			case 'mariadb':
			case 'mysql':
			case 'iptables':
			case 'syslog-ng':
			case 'sys-fs/mdadm':
			case 'lvm2':
			case 'app-emulation/libvirt':
				$this->_initManager->activateService($app);
		}

		//-- EMERGE post-config (where applicable)
		switch ($app) {
			case 'app-emulation/open-vm-tools':
				echo "mkdir -p /mnt/hgfs\n";
				break;

			case 'sys-fs/mdadm':
				echo "mdadm --examine --scan > /etc/mdadm.conf\n";
				break;

			case 'conky':
				echo "rm -rf /etc/conky/conky.conf\n";
				echo "wget 'http://71.19.151.36". $routing->generate('config-files_conky', array('server_id' => $this->_server->id ))
					."' -O /etc/conky/conky.conf\n";
				break;

			case 'genkernel':
				echo "rm /usr/share/genkernel/arch/x86_64/kernel-config\n";
            	echo "ln -s /usr/src/linux/.config /usr/share/genkernel/arch/x86_64/kernel-config\n";
            	break;

			case 'mariadb':
				
				echo 'echo "456123
456123" | emerge --config dev-db/mariadb';
				echo "\n";

				echo "echo '' > /var/lib/mysql/mysqld-bin.~rec~\n";
				echo "chown -R mysql:mysql /var/lib/mysql/mysqld-bin.~rec~\n";

				echo "mysqld &\n";

				echo "echo \"
CREATE USER 'prod'@'%' IDENTIFIED BY '456123';
GRANT ALL PRIVILEGES ON *.* TO 'prod'@'%';

CREATE USER 'rjkeller'@'%' IDENTIFIED BY '456123';
GRANT ALL PRIVILEGES ON *.* TO 'rjkeller'@'%';

\" > /tmp/mysql\n";
			
				echo "mysql -u root -p456123 < /tmp/mysql\n";
				echo "rm -f /tmp/mysql\n";
				echo "mysqladmin -u root -p456123 shutdown\n";
				break;


			case 'app-forensics/chkrootkit':
				echo "echo '0 3 * * * /usr/sbin/chkrootkit\n' >> /var/spool/cron/crontabs/root\n";
				echo "chown root:crontab /var/spool/cron/crontabs/root\n";
				break;


			case 'sudo':
				echo "chmod +w /etc/sudoers\n";
				echo "echo '%admin ALL=(ALL) ALL\n' >> /etc/sudoers\n";
				echo "chmod -w /etc/sudoers\n";
				break;

			case 'app-antivirus/clamav':
				echo "paxctl -m /usr/sbin/clamd /usr/bin/freshclam /usr/bin/clamconf\n";
				echo "freshclam\n";
				break;

			case 'www-servers/apache':
				echo "echo '

#PIXONITE SETTINGS
ServerName ". $this->_server->hostname ."
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
Listen 443
' >> /etc/apache2/httpd.conf\n";

			echo "sed -i 's/-D DEFAULT_VHOST -D INFO/-D DEFAULT_VHOST -D INFO -D PHP5/g' /etc/conf.d/apache2\n";

		}
	}

	public function buildKernel($kernel_type) {
		$this->emerge($kernel_type);

		echo "mv /usr/src/.config /usr/src/linux/.config\n";
		echo "touch /usr/src/linux/.config\n";

		echo "cd /usr/src/linux\n";
		echo "make\n";
		echo "make modules_install\n";

		echo "cp arch/x86_64/boot/bzImage /boot/kernel-`find /usr/src -name linux-3* | awk -Flinux- '{print \$NF }'`\n";
	}

	public function setInitManager($manager) {
		if ($manager->getName() == "systemd") {
			echo "echo '
sys-apps/dbus -systemd' >> /etc/portage/package.use\n";
			$this->emerge($manager->getName());
			echo "sed -i 's/sys-apps\/dbus -systemd/ /g' /etc/portage/package.use\n";
			echo "emerge sys-apps/dbus\n";
		}
		else {
			$this->emerge($manager->getName());
		}
		$this->_initManager = $manager;
	}

	public function emergeUpdateAll() {
		echo "emerge --update --deep --with-bdeps=y @world\n";
		echo "emerge @preserved-rebuild\n";
		echo "emerge --changed-use --deep world\n";
	}

	public function createFstab() {
		echo "echo '";
		$incr = 1;
		foreach ($this->_mountPoints as $mountPoint) {
			if ($mountPoint['type'] == "swap")
				echo $mountPoint['device'] ."\tnone\tswap\tsw\t0 0\n";
			else if ($mountPoint['type'] == "boot")
				echo $mountPoint['device'] ."\t". $mountPoint['mountPoint'] ."\t". $this->_fileSystem ."\tnoauto,noatime\t1 2\n";
			else
				echo $mountPoint['device'] ."\t". $mountPoint['mountPoint'] ."\t". $this->_fileSystem ."\tnoatime\t0 ". $incr++ . "\n";
		}
		echo "' > /etc/fstab\n";
		echo "rm -rf /etc/mtab\n";
		echo "ln -s /proc/self/mounts /etc/mtab\n";
	}

	public function installNetwork() {
		echo "echo '". $this->_server->hostname ."' > /etc/hostname\n";
		echo "echo 'hostname=\"". $this->_server->hostname ."\"' > /etc/conf.d/hostname\n";
		echo "echo \"127.0.0.1 localhost   ". $this->_server->hostname ."\n::1     localhost\n\" > /etc/hosts\n";
		echo "cd /etc/conf.d\n";

		$this->_initManager->activateNetwork($this->_server, $this->_ipAddr);
		if ($this->_ipAddr == null) {
			$this->emerge("net-misc/dhcpcd");
		}

	}


	public function addUserAccounts() {
		echo "groupadd admin\n";
		echo "useradd -G admin rjkeller\n";

		echo "mkdir -p /home/rjkeller/.ssh\n";
		echo "chown -R rjkeller:rjkeller /home/rjkeller\n";
		echo "\n";

		echo "sed -i 's/#PermitRootLogin yes/PermitRootLogin no/g' /etc/ssh/sshd_config\n";
		echo "echo '456123
456123' | passwd root\n";
		echo "echo '456123
456123' | passwd rjkeller\n";

	}

	public function emergeIptablesFirewall($rules) {
		$this->emerge("iptables");

		echo 'echo "*filter
:INPUT ACCEPT [0:0]
:FORWARD ACCEPT [0:0]
:OUTPUT ACCEPT [82:5518]
:RH-Firewall-1-INPUT - [0:0]
-A INPUT -j RH-Firewall-1-INPUT
-A INPUT -i '. $this->_server->eth0_name .' -p tcp -m state --state RELATED,ESTABLISHED -j ACCEPT
-A FORWARD -j RH-Firewall-1-INPUT
-A OUTPUT -o '. $this->_server->eth0_name .' -m state --state RELATED,ESTABLISHED -j ACCEPT
-A RH-Firewall-1-INPUT -i lo -j ACCEPT
-A RH-Firewall-1-INPUT -p icmp -m icmp --icmp-type any -j ACCEPT
-A RH-Firewall-1-INPUT -p esp -j ACCEPT
-A RH-Firewall-1-INPUT -p ah -j ACCEPT
-A RH-Firewall-1-INPUT -i '. $this->_server->eth0_name .' -p tcp -m tcp --sport 1024:65535 --dport 443 -m state --state NEW -j ACCEPT
-A RH-Firewall-1-INPUT -i '. $this->_server->eth0_name .' -p tcp -m tcp --sport 1024:65535 --dport 80 -m state --state NEW -j ACCEPT
-A RH-Firewall-1-INPUT -i '. $this->_server->eth0_name .' -p tcp -m tcp --sport 1024:65535 --dport 22 -m state --state NEW -j ACCEPT
-A RH-Firewall-1-INPUT -m state --state RELATED,ESTABLISHED -j ACCEPT
-A RH-Firewall-1-INPUT -s 71.19.151.32/28 -i '. $this->_server->eth0_name .' -j ACCEPT
-A RH-Firewall-1-INPUT -j REJECT --reject-with icmp-port-unreachable
COMMIT" > /var/lib/iptables/rules-save';
		echo "\n";
	}


	public function installGrub2($device) {
		$packagesToInstall = array("sys-boot/grub");
        if ($this->_server->enableRaid) {
            $packagesToInstall[] = "sys-fs/mdadm";
            $packagesToInstall[] = "lvm2";
            $packagesToInstall[] = "genkernel";
        }
        $this->parallelEmerge($packagesToInstall);

        if ($this->_server->enableRaid) {
        	foreach ($device as $dev) {
        		echo "grub2-install ". $dev ."\n";
        	}
        }
        else {
        	echo "grub2-install ". $device[0] ."\n";
        }
		echo "echo '
GRUB_CMDLINE_LINUX=\"init=/usr/lib/systemd/systemd\"
' >> /etc/default/grub\n";
		$cmdLineDefault = "rootfstype=ext4";
		if ($this->_server->enableRaid)
			$cmdLineDefault .= " domdadm dolvm"; 
		if ($this->_site->hasAppFlag("PIXO/SECURE_TOOLS"))
			$cmdLineDefault .= " apparmor=1 security=apparmor";

		if (!empty($cmdLineDefault)) {
			echo "echo '
GRUB_CMDLINE_LINUX_DEFAULT=\"". trim($cmdLineDefault) ."\"
' >> /etc/default/grub\n";
		}

        if ($this->_server->enableRaid) {
            echo "genkernel --lvm --mdadm --install initramfs\n";
        }

		echo "grub2-mkconfig -o /boot/grub/grub.cfg\n";
	}

	public function installFluxbox()
	{
		echo "emerge commonbox-styles commonbox-styles-extra fluxbox-styles-fluxmod gentoo-artwork\n";
		echo "echo 'exec startfluxbox\n' > /home/rjkeller/.xinitrc\n";
		echo "echo 'exec startfluxbox\n' > /root/.xinitrc\n";

	}

	public function installGnome()
	{
		echo "echo 'gnome-base/gnome-session branding' >> /etc/portage/package.use\n";
		echo "echo 'export XDG_MENU_PREFIX=gnome-\nexec gnome-session\n' > /home/rjkeller/.xinitrc\n";
		echo "echo 'export XDG_MENU_PREFIX=gnome-\nexec gnome-session\n' > /root/.xinitrc\n";

		echo "emerge gnome-light\n";
		echo "emerge xf86-input-keyboard\n";
		echo "emerge xf86-input-evdev\n";
		echo "emerge media-fonts/corefonts\n";
		echo "emerge net-misc/x11-ssh-askpass\n";
		echo "emerge alsa-utils\n";
		echo "emerge gnome-theme-manager\n";

	}

	public function emergeNoReplace($app) {
		echo "emerge --noreplace ". $app ."\n";
	}

	public function emergeCleanup() {
		echo "emerge --depclean\n";
	}
}
