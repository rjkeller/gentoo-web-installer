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
namespace Pixonite\ServerManagerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Pixonite\ServerManagerBundle\Helper\ServerConfigScript;
use Pixonite\ServerManagerBundle\Helper\BuildScript;
use Pixonite\ServerManagerBundle\BuildScripts\PixoniteServer;

/**
 * IpAddress controller.
 *
 * @Route("/config_files")
 */
class ConfigFilesController extends Controller
{
    /**
     * @Route("/{server_id}/createVm", name="config-files_createVm")
     * @Template("PixoniteServerManagerBundle:ConfigFiles:createVm.txt.twig")
     */
    public function createVmAction($server_id)
    {
        $template_vars = array();
        $em = $this->get('doctrine')->getManager();

        if (isset($_GET['private_id']))
            $template_vars['privateIp'] = $_GET['private_ip'];
        else
            $template_vars['privateIp'] = "PRIVATE_IP_GOES_HERE";

        $template_vars['server'] = $em
            ->getRepository("PixoniteServerManagerBundle:Server")
            ->findOneById($server_id);
        $template_vars['site'] = $em
            ->getRepository("PixoniteServerManagerBundle:Site")
            ->findOneById($template_vars['server']->siteId);

        return $template_vars;
    }

    /**
     * @Route("/xtrabackup/hourly", name="config-files_xtrabackup_hourly")
     * @Template("PixoniteServerManagerBundle:ConfigFiles:xtrabackup_hourly.sh.twig")
     */
    public function xtraBackupHourlyAction()
    {      return array();    }

    /**
     * @Route("/xtrabackup/daily", name="config-files_xtrabackup_daily")
     * @Template("PixoniteServerManagerBundle:ConfigFiles:xtrabackup_daily.sh.twig")
     */
    public function xtraBackupDailyAction()
    {      return array();    }

    /**
     * @Route("/{server_id}/conky", name="config-files_conky")
     * @Template("PixoniteServerManagerBundle:ConfigFiles:conky.txt.twig")
     */
    public function conkyAction($server_id)
    {
        return $this->createVmAction($server_id);
    }

    /**
     * @Route("/{server_id}/portage", name="config-files_portage")
     * @Template("PixoniteServerManagerBundle:ConfigFiles:PortageMakeConf.txt.twig")
     */
    public function portageAction($server_id)
    {
        return $this->createVmAction($server_id);
    }

    /**
     * @Route("/{server_id}/init", name="config-files_init")
     * @Template("PixoniteServerManagerBundle:ConfigFiles:init.txt.twig")
     */
    public function initAction($server_id)
    {
        $em = $this->getDoctrine()->getManager();
        $server = $em->getRepository("PixoniteServerManagerBundle:Server")
            ->findOneById($server_id);
        $site = $em->getRepository("PixoniteServerManagerBundle:Site")
            ->findOneById($server->siteId);

        $scriptBuilder = new BuildScript(
            new ServerConfigScript($server));

        $serverType = new $site->serverGeneratorClass($server);
        $serverType->init($scriptBuilder);
        die();
    }

    /**
     * @Route("/{server_id}/install", name="config-files_install")
     * @Template("PixoniteServerManagerBundle:ConfigFiles:install.txt.twig")
     */
    public function installAction($server_id)
    {
        $em = $this->getDoctrine()->getManager();
        $server = $em->getRepository("PixoniteServerManagerBundle:Server")
            ->findOneById($server_id);
        $site = $em->getRepository("PixoniteServerManagerBundle:Site")
            ->findOneById($server->siteId);

        $scriptBuilder = new BuildScript(
            new ServerConfigScript($server));

        $serverType = new $site->serverGeneratorClass($server);
        ob_start();
        $serverType->init($scriptBuilder);

        ob_end_clean();
        $serverType->install($scriptBuilder);
        ob_end_flush();
        die();
    }

    /**
     * @Route("/{server_id}/kernel", name="config-files_kernel")
     * @Template("PixoniteServerManagerBundle:ConfigFiles:kernel.txt.twig")
     */
    public function kernelAction($server_id)
    {
        $em = $this->get('doctrine')->getManager();
        $server = $em->getRepository("PixoniteServerManagerBundle:Server")
            ->findOneById($server_id);


        return $this->render("PixoniteServerManagerBundle:kernels:". $server->kernel .".txt.twig",
            array());
        return $this->createVmAction($server_id);
    }
}
