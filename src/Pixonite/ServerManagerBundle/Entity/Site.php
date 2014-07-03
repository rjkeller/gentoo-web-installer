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
 * Represents a server configuration for a website that this server hosts.
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class Site
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
     * @var string
     *
     * @ORM\Column(name="server_generator_class", type="string", length=255)
     */
    public $serverGeneratorClass;

    /**
     * @var string
     *
     * @ORM\Column(name="use_flags", type="text")
     */
    public $useFlags;

    /**
     * @var string
     *
     * @ORM\Column(name="app_flags", type="text")
     */
    public $appFlags;

    /**
     * @var string
     *
     * @ORM\Column(name="accept_keywords", type="text")
     */
    public $acceptKeywords;

    /**
     * @var string
     *
     * @ORM\Column(name="mask", type="text", nullable=true)
     */
    public $mask;

    /**
     * @var string
     *
     * @ORM\Column(name="package_server", type="string", length=255, nullable=true)
     */
    public $packageServer;


    public function getServerGenerator()
    {
        return new $this->serverGeneratorClass();
    }

    private $_appVersions;
    public function getAllForceAppVersions()
    {
        $apps = array();
        $out = explode("\n", $this->forceAppVersions);

        foreach ($out as $value)
        {
            if (!isset($data[1]))
                continue;
            $data = explode(" ", $value);
            $apps[$data[0]] = $data[1];
        }
        return $apps;
    }

    public function hasAppFlag($flag)
    {
        return strpos($this->appFlags, $flag) !== false;
    }

    public function getGlobalKeywords()
    {
        $keywords = explode("\n", $this->acceptKeywords);
        return trim($keywords[0]);
    }

    public function getPackageKeywords()
    {
        $pos = strpos($this->acceptKeywords, "\n");
        if ($pos === false)
            return "";
        return substr($this->acceptKeywords, $pos + 1, strlen($this->acceptKeywords));
    }

    public function getPackageMask()
    {
        return trim($this->mask);
    }

    public function getGlobalUSE()
    {
        $keywords = explode("\n", $this->useFlags);
        return trim($keywords[0]);
    }

    public function getPackageUSE()
    {
        $pos = strpos($this->useFlags, "\n");
        if ($pos === false)
            return "";
        return substr($this->useFlags, $pos + 1, strlen($this->useFlags));
    }
}
