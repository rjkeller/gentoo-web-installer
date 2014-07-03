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
namespace Pixonite\ServerManagerBundle\BuildScripts;

use Pixonite\ServerManagerBundle\Helper\InitManagers\SystemD;
use Oranges\MasterContainer;

/**
 * FIGURE OUT MODULES IN NEW KERNEL:
 *  lspci -k
 */
class DevDesktop
{
	private $server;
	private $site;

	public function __construct($server = null)
	{
		if ($server != null) {
			$em = MasterContainer::get('doctrine')->getManager();
			$this->server = $server;
			$this->site = $em->getRepository("PixoniteServerManagerBundle:Site")
				->findOneById($server->siteId);
		}
	}

	public function getPortageInfo()
	{
		return 'FONTUSE="truetype type1 cleartype corefonts"
XUSE="udisks ipv6 pulseaudio opengl alsa X -llvm ffmpeg"
FLUXBOXUSE="-gnome -kde -qt gtk"
SYSTEMDUSE="systemd -openrc dbus"
GNOMEUSE="gtk gnome -kde -qt"
';
	}

	public function getPortageUseFlags()
	{
		return '${FLUXBOXUSE} ${XUSE} ${FONTUSE}';
	}

	public function init($scriptBuilder)
	{
		$disks = explode(",", $this->server->disks);
		$scriptBuilder->activateLocalNetwork();

		$scriptBuilder->syncSystemClock();

		$scriptBuilder->disk_mkdisk($disks[0],
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
		$scriptBuilder->disk_mkfs($disks[0] .'4', "/", "std", "0 1");
		$scriptBuilder->disk_mkfs($disks[0] .'2', "/boot", "boot");
		$scriptBuilder->disk_mkswap($disks[0] .'3');

		for ($i = 1; $i < count($disks); $i++)
		{
			$scriptBuilder->disk_mkdisk($disks[$i],
				"
				mklabel gpt
				unit mib
				mkpart primary 1 -1
				print
				quit
			  ");
			$scriptBuilder->disk_mkfs($disks[$i] .'1', "/var/lib/mysql", "std", "0 2");
		}

		$scriptBuilder->installStage3('amd64-nomultilib');
	}

	public function install($scriptBuilder)
	{
		$disks = explode(",", $this->server->disks);
		$scriptBuilder->installPortage();

		$scriptBuilder->eselect('profile', 9);

		$scriptBuilder->setTimezone('America/Los_Angeles');

		$scriptBuilder->setLocale("en_US ISO-8859-1\nen_US.UTF-8 UTF-8");

		$scriptBuilder->eselect('locale', 'en_US.utf8');

		$scriptBuilder->emerge('gcc');
		$scriptBuilder->eselect('gcc-config', 2);

		$scriptBuilder->buildKernel('gentoo-sources');

		$scriptBuilder->setInitManager(new SystemD());
		$scriptBuilder->emergeUpdateAll();

		$scriptBuilder->createFstab();

		$scriptBuilder->installNetwork();

		$scriptBuilder->emerge('cronie');
		$scriptBuilder->emerge('sshd');

		$scriptBuilder->addUserAccounts();

		$scriptBuilder->parallelEmerge(array(
			"cronie",
			"sshd",
			"app-arch/zip",
			"app-arch/unzip",
			"vim",
			"syslog-ng",
			"logrotate",
			"ntp",
			"sudo",

			"plymouth",

			"xorg-server",
			"fluxbox",

			"conky",

			"mail-client/thunderbird",
			"www-client/chromium",
			"app-emulation/virt-manager",
			"net-im/pidgin",

			"app-office/libreoffice",
			"app-crypt/truecrypt"
			));


		$scriptBuilder->eselect('editor', 3);


		$scriptBuilder->emergeIptablesFirewall(array(
			'open' => '22',
		  ));

		$scriptBuilder->emerge('fail2ban');

		$scriptBuilder->installFluxbox();

		$scriptBuilder->emerge('media-video/ffmpeg');
		$scriptBuilder->emerge('media-video/vlc');

		$scriptBuilder->emerge('www-plugins/adobe-flash');

//        $scriptBuilder->emerge('app-emulation/virtualbox');


		$scriptBuilder->installGrub2($disks[0]);

		$scriptBuilder->emergeUpdateAll();
		$scriptBuilder->emergeNoReplace('app-editors/nano');
//        $scriptBuilder->emergeCleanup();

	}
}
