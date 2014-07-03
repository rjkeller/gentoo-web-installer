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
namespace Pixonite\ServerManagerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use Oranges\MasterContainer;

/**
 * Server configuration for a Gentoo install.
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class Server
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    public $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     */
    public $name;

    /**
     * Note: Public IP addresses selected here should also have an entry in the
     * IpAddress entity to configure the network properly.
     * 
     * @var string
     *
     * @ORM\Column(name="public_ip", type="string", length=16, nullable=true)
     */
    public $publicIp;

    /**
     * @var string
     *
     * @ORM\Column(name="hostname", type="string", length=255)
     */
    public $hostname;

    /**
     *POSSIBLE VALUE: { 'PENDING_CREATE_START', 'PENDING_CREATE', 'ACTIVE', 'INACTIVE' }
     * 
     *  @var string
     *
     * @ORM\Column(name="status", type="string", length=35)
     */
    public $status;

    /**
     * @var integer
     *
     * @ORM\Column(name="site_id", type="integer")
     */
    public $siteId;

    /**
     * A string value that corresponds to kernel settings contained in the twig
     * folder:
     * ServerManagerBundle/Resources/views/kernels/{kernel_name}.txt.twig
     * 
     * @var string
     * @ORM\Column(name="kernel", type="string", length=255)
     */
    public $kernel;

    /**
     * @var string
     * 
     * Acceptable values: { 'dev', 'stage', 'live' }
     *
     * @ORM\Column(name="site_mode", type="text")
     */
    public $siteMode = "live";

    /**
     * Name of your ethernet adapter. Best to get this value from running
     * 'ifconfig' from the LiveCD.
     * 
     * @var string
     * @ORM\Column(name="eth0_name", type="string", length=10)
     */
    public $eth0_name = "eth0";

    /**
     * Disk drives. Set this to a comma-delimited value for RAID support.
     * 
     * @var string
     * @ORM\Column(name="disks", type="string", length=255)
     */
    public $disks = "/dev/sda,/dev/sdb";

    /**
     * @var string
     * @ORM\Column(name="enableRaid", type="boolean")
     */
    public $enableRaid = false;

    /**
     * @var integer
     * @ORM\Column(name="num_cpus", type="integer")
     */
    public $numCpus = 1;

    /**
     * @var string
     * @ORM\Column(name="input_devices", type="string", length=255, nullable=true)
     */
    public $inputDevices = "";

    /**
     * Name of the video card driver(s) to use (e.g., nvidia, nouveau, vesa,
     * etc).
     * 
     * @var string
     * @ORM\Column(name="video_cards", type="string", length=255, nullable=true)
     */
    public $videoCards = "";

    public function getHost()
    {
        $name = explode(".", $this->hostname);
        return $name[0];
    }

    /**
     * Whether or not this server is using a VMWare-optimized kernel.
     */
    public function isVmware()
    {
        return strpos($this->kernel, "vmware") !== false;
    }

    /**
     * Returns the log output from server generation scripts.
     */
    public function getLogs()
    {
        $em = MasterContainer::get('doctrine')->getManager();
        return $em->createQuery("
            SELECT
                l
            FROM
                PixoniteServerManagerBundle:LogFile l
            WHERE
                l.serverId = '". $this->id ."'
            ")
            ->getResult();
    }
}
