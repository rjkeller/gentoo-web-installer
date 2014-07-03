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

/**
 * An IP Address that can be allocated to a new Gentoo instance.
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class IpAddress
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
     * @ORM\Column(name="public_ip", type="string", length=16)
     */
    public $publicIp;

    /**
     * @var string
     *
     * @ORM\Column(name="broadcast", type="string", length=16, nullable=true)
     */
    public $broadcast = null;

    /**
     * @var string
     *
     * @ORM\Column(name="netmask", type="string", length=16, nullable=true)
     */
    public $netmask = null;

    /**
     * @var string
     *
     * @ORM\Column(name="gateway", type="string", length=16, nullable=true)
     */
    public $gateway = null;

    /**
     * @var string
     *
     * @ORM\Column(name="nameserver", type="string", length=16, nullable=true)
     */
    public $nameserver = null;
}
