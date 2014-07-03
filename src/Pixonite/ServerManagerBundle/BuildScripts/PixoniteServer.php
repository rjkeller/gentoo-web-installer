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
class PixoniteServer
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
                $diskDeviceNames .= $disks[$i] . " ";
                $scriptBuilder->disk_mkdisk($disks[$i],
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
            }

            echo "mdadm --create /dev/md2 --level=1 --raid-devices=2 ". $diskDeviceNames ."\n";
            echo "mdadm --create /dev/md3 --level=1 --raid-devices=2 ". $diskDeviceNames ."\n";
            echo "mdadm --create /dev/md4 --level=1 --raid-devices=2 ". $diskDeviceNames ."\n";

            $scriptBuilder->disk_mkfs("/dev/md4", "/", "std", "0 1");
            $scriptBuilder->disk_mkfs("/dev/md2", "/boot", "boot");
            $scriptBuilder->disk_mkswap("/dev/md3");
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

        if ($this->site->hasAppFlag('gcc'))
          $scriptBuilder->emerge('sys-devel/gcc');

        $scriptBuilder->buildKernel('hardened-sources');

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
            'www-servers/apache',
            'sys-process/htop',
            'sys-process/iotop',
            'php',
            'dev-db/redis',
            'dev-php/pecl-redis',
            'apparmor',
            'sec-policy/apparmor-profiles',
            'dev-php/phpunit',
            'app-misc/screen',
          );

        if ($this->site->hasAppFlag('mongodb')) {
          $packagesToInstall[] = "dev-db/mongodb";
          $packagesToInstall[] = "dev-php/pecl-mongo";
        }

        if ($this->site->hasAppFlag('mariadb')) {
          $packagesToInstall[] = "mariadb";
        }

        $scriptBuilder->parallelEmerge($packagesToInstall);

        if ($this->site->hasAppFlag('mariadb')) {
          $scriptBuilder->emerge('mariadb/xtrabackup');
        }
        $scriptBuilder->emerge('sshd');
        $scriptBuilder->addUserAccounts();
        $scriptBuilder->eselect('editor', 3);

        $scriptBuilder->emergeIptablesFirewall(array(
            'open' => '80',
            'open' => '22',
            'acceptAll' => '71.19.151.32/28'
          ));

        $scriptBuilder->emerge('fail2ban');

        if ($this->site->hasAppFlag('pixo-apps/Pixonite')) {
          $scriptBuilder->emerge("dev-python/pip");
          $scriptBuilder->emerge("pip:aws");
          
          if (!$this->server->isVmware()) {
            $scriptBuilder->cmd('
              mkdir -p /var/www/Pixonite
              aws s3 cp s3://pixosites/Pixonite/public_html/ /var/www/Pixonite/ --recursive

              rm -rf /var/www/Pixonite/app/cache
              rm -rf /var/www/Pixonite/app/logs
              mkdir /var/www/Pixonite/app/cache
              mkdir /var/www/Pixonite/app/logs

              chmod -R 777 /var/www/Pixonite/app/cache /var/www/Pixonite/app/logs
            ');
          }
          
          $scriptBuilder->cmd('
            chown -R rjkeller:rjkeller /var/www
            rm -rf /etc/apache2/vhosts.d/*
            aws s3 cp s3://pixosites/Pixonite/config_files/apache2/httpd.conf /etc/apache2/vhosts.d/Pixonite.conf
          ');
        }



        if ($this->server->siteMode == 'dev') {
          $scriptBuilder->emerge('dev-ruby/rubygems');
          $scriptBuilder->emerge('dev-php/xdebug');
          $scriptBuilder->cmd('ln -s /usr/bin/gem19 /usr/bin/gem');
          $scriptBuilder->cmd('gem update system');
          $scriptBuilder->cmd('gem install sass');
          $scriptBuilder->cmd('gem install compass');
          $scriptBuilder->cmd('gem install zurb-foundation');

          $scriptBuilder->emerge('dev-vcs/git');
          $scriptBuilder->emerge('dev-vcs/subversion');
          $scriptBuilder->emerge('php:composer');
        }

        if ($this->site->hasAppFlag('vmwaretools')) {
          $scriptBuilder->emerge('app-emulation/open-vm-tools');
        }

        $scriptBuilder->installGrub2($disks[0]);

    }
}
