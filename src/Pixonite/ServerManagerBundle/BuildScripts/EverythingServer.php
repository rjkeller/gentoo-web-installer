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
class EverythingServer
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

        $scriptBuilder->installStage3('amd64-hardened+nomultilib');
    }

    public function install($scriptBuilder)
    {
        $disks = explode(",", $this->server->disks);
        $scriptBuilder->installPortage();

        if ($this->site->hasAppFlag('PIXO/SECURE_TOOLS'))
          $scriptBuilder->eselect('profile', 13);
        else
          $scriptBuilder->eselect('profile', 9);

        $scriptBuilder->setTimezone('America/Los_Angeles');

        $scriptBuilder->setLocale("en_US ISO-8859-1\nen_US.UTF-8 UTF-8");

        $scriptBuilder->eselect('locale', 'en_US.utf8');

        $scriptBuilder->emerge('gcc');
        if ($this->site->hasAppFlag('PIXO/SECURE_TOOLS'))
          $scriptBuilder->eselect('gcc-config', 6);
        else
          $scriptBuilder->eselect('gcc-config', 2);

        if ($this->site->hasAppFlag('PIXO/SECURE_TOOLS'))
          $scriptBuilder->buildKernel('hardened-sources');
        else
          $scriptBuilder->buildKernel('gentoo-sources');

        $scriptBuilder->setInitManager(new SystemD());
        $scriptBuilder->emergeUpdateAll();

        $scriptBuilder->createFstab();

        $scriptBuilder->installNetwork();

        $scriptBuilder->emerge('cronie');
        $scriptBuilder->emerge('sshd');

        $scriptBuilder->addUserAccounts();

        $scriptBuilder->emerge('app-arch/zip');
        $scriptBuilder->emerge('app-arch/unzip');
        $scriptBuilder->emerge('vim');
        $scriptBuilder->eselect('editor', 3);

        $scriptBuilder->emerge('syslog-ng');
        $scriptBuilder->emerge('logrotate');

        $scriptBuilder->emerge('ntp');
        $scriptBuilder->emerge('sudo');

        $scriptBuilder->emergeIptablesFirewall(array(
            'open' => '80',
            'open' => '22',
            'acceptAll' => '71.19.151.32/28'
          ));

        $scriptBuilder->emerge('fail2ban');

        if ($this->site->hasAppFlag('www-servers/apache')) {
          $scriptBuilder->emerge('www-servers/apache');
        }

        //don't need terminal tools on desktops
        if (!$this->site->hasAppFlag('gnome')) {
          $scriptBuilder->emerge('sys-process/htop');
          $scriptBuilder->emerge('sys-process/iotop');
        }

        if ($this->site->hasAppFlag('php')) {
          $scriptBuilder->emerge('php');
        }


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

          if ($this->server->isVmware()) {
            $scriptBuilder->cmd("
              sed -i 's/\/var\/www/\/mnt\/hgfs\/Sites/g' /etc/apache2/vhosts.d/Pixonite.conf
              sed -i 's/www.Pixoniteholdings.com/Pixonite.". $this->server->hostname ."/g' /etc/apache2/vhosts.d/Pixonite.conf
            ");
          }
        }

        if ($this->site->hasAppFlag('redis')) {
          $scriptBuilder->emerge('dev-db/redis');

          if ($this->site->hasAppFlag('php'))
            $scriptBuilder->emerge('dev-php/pecl-redis');
        }

        if ($this->site->hasAppFlag('PIXO/SECURE_TOOLS')) {
          $scriptBuilder->emerge('app-forensics/chkrootkit');
          $scriptBuilder->emerge('app-antivirus/clamav');
          $scriptBuilder->emerge('apparmor');
          $scriptBuilder->emerge('sec-policy/apparmor-profiles');
        }

        if ($this->site->hasAppFlag('mongodb')) {
          $scriptBuilder->emerge('dev-db/mongodb');

          if ($this->site->hasAppFlag('php'))
            $scriptBuilder->emerge('dev-php/pecl-mongo');
        }

        if ($this->site->hasAppFlag('mariadb')) {
          $scriptBuilder->emerge('mariadb');
          $scriptBuilder->emerge('mariadb/xtrabackup');
        }

        if ($this->site->hasAppFlag('fluxbox')) {
          $scriptBuilder->installFluxbox();
          $scriptBuilder->emerge('plymouth');
        }


        if ($this->site->hasAppFlag('gnome')) {
          $scriptBuilder->cmd("echo 'gnome-base/gnome-session branding' >> /etc/portage/package.use\n");
          $scriptBuilder->installGnome();
          $scriptBuilder->emerge('gnome-extra/gnome-tweak-tool');
          $scriptBuilder->emerge('gnome-extra/gnome-system-monitor');
          $scriptBuilder->emerge('gnome-extra/gnome-screensaver');
        }


        if ($this->site->hasAppFlag('conky')) {
          $scriptBuilder->emerge('conky');
        }

        if ($this->site->hasAppFlag('vlc')) {
          $scriptBuilder->emerge('media-video/vlc');
        }

        if ($this->site->hasAppFlag('thunderbird')) {
          $scriptBuilder->emerge('mail-client/thunderbird');
        }

        if ($this->site->hasAppFlag('chromium')) {
          $scriptBuilder->emerge('www-client/chromium');

          if ($this->site->hasAppFlag('flash-player')) {
            $scriptBuilder->emerge('www-plugins/adobe-flash');
          }
        }

        if ($this->site->hasAppFlag("virt-manager")) {
          $scriptBuilder->emerge('app-emulation/virt-manager');
        }

        if ($this->site->hasAppFlag("pidgin")) {
          $scriptBuilder->emerge('net-im/pidgin');
        }

        if ($this->site->hasAppFlag("libreoffice")) {
          $scriptBuilder->emerge('app-office/libreoffice');
        }

        if ($this->site->hasAppFlag("libreoffice")) {
          $scriptBuilder->emerge('app-emulation/virtualbox');
        }

        if ($this->site->hasAppFlag("truecrypt")) {
          $scriptBuilder->emerge('app-crypt/truecrypt');
        }



        if ($this->site->hasAppFlag('RJ_DEV_TOOLS')) {
          $scriptBuilder->emerge('dev-ruby/rubygems');
          $scriptBuilder->emerge('dev-php/xdebug');
          $scriptBuilder->emerge('gem:update:system');
          $scriptBuilder->emerge('gem:install:sass');
          $scriptBuilder->emerge('gem:install:compass');
          $scriptBuilder->emerge('gem:install:zurb-foundation');
          $scriptBuilder->emerge('git');
          $scriptBuilder->emerge('subversion');
          $scriptBuilder->emerge('php:composer');
        }

        if ($this->server->isVmware()) {
          $scriptBuilder->emerge('app-emulation/open-vm-tools');
        }

        $scriptBuilder->installGrub2($disks[0]);

        $scriptBuilder->emergeUpdateAll();
        $scriptBuilder->emergeNoReplace('app-editors/nano');
        $scriptBuilder->emergeCleanup();

    }
}
