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
class VirtServer
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
        return '';
    }

    public function getPortageUseFlags()
    {
        return 'app-emulation/qemu spice ';
    }

    public function init($scriptBuilder)
    {
        $disks = explode(",", $this->server->disks);
        $scriptBuilder->activateLocalNetwork();

        $scriptBuilder->syncSystemClock();

        $scriptBuilder->formatDisks();

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

        if ($this->site->hasAppFlag('gcc'))
          $scriptBuilder->emerge('sys-devel/gcc');

        $scriptBuilder->buildKernel('gentoo-sources');

        $scriptBuilder->setInitManager(new SystemD());
        $scriptBuilder->emergeUpdateAll();

        $scriptBuilder->createFstab();

        $scriptBuilder->installNetwork();

        $packagesToInstall = array('syslog-ng',
            'logrotate',
            'cronie',
            'app-arch/zip',
            'app-arch/unzip',
            'vim',
            'ntp',
            'sudo',
            'sys-process/htop',
            'sys-process/iotop',
            'app-misc/screen',
            'app-emulation/qemu',
            'app-emulation/libvirt',
          );

        $scriptBuilder->parallelEmerge($packagesToInstall);

        $scriptBuilder->emerge('sshd');
        $scriptBuilder->addUserAccounts();
        $scriptBuilder->eselect('editor', 3);

        $scriptBuilder->emergeIptablesFirewall(array(
            'open' => '22',
            'acceptAll' => '71.19.151.32/28'
          ));

        $scriptBuilder->emerge('fail2ban');

        $scriptBuilder->installGrub2($disks[0]);

    }
}
