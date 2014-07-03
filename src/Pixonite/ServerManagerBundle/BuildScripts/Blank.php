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
class Blank
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
        return '';
    }

    public function init($scriptBuilder)
    {
        $disks = explode(",", $this->server->disks);
        $scriptBuilder->activateLocalNetwork();

        $scriptBuilder->syncSystemClock();

        if ($this->server->enableRaid) {
            $diskDeviceNames = "";
            for ($i = 0; $i < count($disks); $i++)
            {
                $diskDeviceNames .= $disks[$i] . "NUM ";
                $scriptBuilder->disk_mkdisk($disks[$i],
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

            $scriptBuilder->disk_mkfs("/dev/vg-sata/root", "/", "std", "0 1");
            $scriptBuilder->disk_mkfs("/dev/vg-sata/boot", "/boot", "boot");
            $scriptBuilder->disk_mkswap("/dev/vg-sata/swap");

            echo "mdadm --detail --scan > /etc/mdadm.conf\n";
        }
        else {
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
        }

        $scriptBuilder->installStage3('amd64-hardened+nomultilib');
    }

    public function install($scriptBuilder)
    {
        $disks = explode(",", $this->server->disks);
        $scriptBuilder->installPortage();

        $scriptBuilder->eselect('profile', 13);

        $scriptBuilder->setTimezone('America/Los_Angeles');

        $scriptBuilder->setLocale("en_US ISO-8859-1\nen_US.UTF-8 UTF-8");

        $scriptBuilder->eselect('locale', 'en_US.utf8');

        $scriptBuilder->buildKernel('hardened-sources');

        $scriptBuilder->setInitManager(new SystemD());
        $scriptBuilder->emergeUpdateAll();

        $scriptBuilder->createFstab();

        $scriptBuilder->installNetwork();

        $packagesToInstall = array('syslog-ng',
            'logrotate',
            'cronie',
            'sudo',
          );

        if ($this->server->enableRaid) {
            $packagesToInstall[] = "sys-fs/mdadm";
            $packagesToInstall[] = "lvm2";
            $packagesToInstall[] = "genkernel";
        }

        $scriptBuilder->parallelEmerge($packagesToInstall);

        $scriptBuilder->emerge('sshd');
        $scriptBuilder->addUserAccounts();
        $scriptBuilder->eselect('editor', 3);

        $scriptBuilder->installGrub2($disks);

    }
}
