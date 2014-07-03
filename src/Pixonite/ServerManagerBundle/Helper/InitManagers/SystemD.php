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
namespace Pixonite\ServerManagerBundle\Helper\InitManagers;

use Oranges\MasterContainer;

class SystemD
{
    public function getName()
    {
        return "systemd";
    }

    public function activateService($serviceName)
    {
        //-- Build an init script if necessary

        switch ($serviceName) {
            case 'iptables':
                echo 'echo "[Unit]
Description=Packet Filtering Framework

[Service]
Type=oneshot
ExecStart=/sbin/iptables-restore /var/lib/iptables/rules-save
ExecStop=/sbin/iptables -F
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
" > /usr/lib/systemd/system/iptables.service';
                    echo "\n";
                    break;

                case 'app-emulation/open-vm-tools':
                    echo 'echo "[Unit]
Description=HGFS Mount

[Service]
Type=oneshot
ExecStart=/sbin/modprobe vmhgfs
ExecStart=/bin/mount -t vmhgfs .host:/ /mnt/hgfs
ExecStop=/bin/umount /mnt/hgfs
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
" > /usr/lib/systemd/system/hgfs.service';
            echo "\n";
        }

        //-- activate the service
        switch ($serviceName) {
            case 'ntp':
                echo "ln -s /usr/lib/systemd/system/ntpd.service /etc/systemd/system/multi-user.target.wants/\n";
                break;

            case 'app-emulation/open-vm-tools':
                echo "ln -s /usr/lib/systemd/system/hgfs.service /etc/systemd/system/multi-user.target.wants/\n";
                break;

            case 'dev-db/redis':
                echo "ln -s /usr/lib/systemd/system/redis.service /etc/systemd/system/multi-user.target.wants/\n";
                break;

            case 'www-servers/apache':
                echo "ln -s /usr/lib/systemd/system/apache2.service /etc/systemd/system/multi-user.target.wants/\n";
                break;

            case 'sys-fs/mdadm':
                echo "ln -s /usr/lib/systemd/system/mdadm.service /etc/systemd/system/multi-user.target.wants/\n";
                break;

            case 'app-antivirus/clamav':
                echo "ln -s /usr/lib/systemd/system/clamd.service /etc/systemd/system/multi-user.target.wants/\n";
                echo "ln -s /usr/lib/systemd/system/freshclamd.service /etc/systemd/system/multi-user.target.wants/\n";

                break;

            case 'dev-db/mongodb':
                echo "ln -s /usr/lib/systemd/system/mongodb.service /etc/systemd/system/multi-user.target.wants/\n";
                break;

            case 'mariadb':
            case 'mysql':
                echo "ln -s /usr/lib/systemd/system/mysqld.service /etc/systemd/system/multi-user.target.wants/\n";
                break;

            case 'syslog-ng':
                echo "ln -s /usr/lib/systemd/system/". $serviceName .".service /etc/systemd/system/syslog.service\n";
                echo "ln -s /usr/lib/systemd/system/". $serviceName .".service /etc/systemd/system/multi-user.target.wants/\n";
                break;

            case 'lvm2':
                echo "ln -s /usr/lib/systemd/system/lvm2-lvmetad.service /etc/systemd/system/multi-user.target.wants/\n";
                echo "ln -s /usr/lib/systemd/system/lvm2-monitor.service /etc/systemd/system/multi-user.target.wants/\n";
                break;

            case 'app-emulation/libvirt':
                echo "ln -s '/usr/lib64/systemd/system/libvirtd.service' '/etc/systemd/system/multi-user.target.wants/libvirtd.service'\n";
                echo "ln -s '/usr/lib64/systemd/system/libvirt-guests.service' '/etc/systemd/system/multi-user.target.wants/libvirt-guests.service'\n";
                break;

            default:
                echo "ln -s /usr/lib/systemd/system/". $serviceName .".service /etc/systemd/system/multi-user.target.wants/\n";
                break;
        }

    }

    public function activateNetwork($server, $ipAddress = null) {
        if ($ipAddress != null) {
            echo 'echo "[Unit]
Description=Static network service 
After=local-fs.target 
Documentation=man:ifconfig(8) 
Documentation=man:route(8) 

[Service] 
Type=oneshot 
RemainAfterExit=yes 
ExecStart=/bin/ifconfig '. $server->eth0_name .' '. $ipAddress->publicIp .' broadcast '. $ipAddress->broadcast .' netmask '. $ipAddress->netmask .' up
ExecStart=/bin/route add default gw '. $ipAddress->gateway .'

[Install] 
WantedBy=multi-user.target
" > /usr/lib/systemd/system/network.'. $server->eth0_name .'.service';
        echo "\n";

        }
        //if this is a DHCP connection
        else {
            echo 'echo "[Unit]
Description=DHCP on '. $server->eth0_name .'
After=basic.target

[Service] 
Type=oneshot 
RemainAfterExit=yes 
ExecStart=/bin/ifconfig '. $server->eth0_name .' up
ExecStart=/sbin/dhcpcd -B '. $server->eth0_name .'

[Install] 
WantedBy=multi-user.target
" > /usr/lib/systemd/system/network.'. $server->eth0_name .'.service';
            echo "\n";

        }

        echo "ln -s /usr/lib/systemd/system/network.". $server->eth0_name .".service /etc/systemd/system/multi-user.target.wants/\n";
        echo "\n";
    }
}