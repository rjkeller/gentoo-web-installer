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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Pixonite\ServerManagerBundle\Entity\Server;
use Pixonite\ServerManagerBundle\Entity\LogFile;
use Pixonite\ServerManagerBundle\Form\ServerType;
use Pixonite\ServerManagerBundle\Helper\ServerConfigScript;
use Pixonite\ServerManagerBundle\Helper\BuildScript;
use Pixonite\ServerManagerBundle\BuildScripts\PixoniteServer;

use GearmanClient;

/**
 * Creates various shell scripts for setting up Gentoo. The shell scripts are
 * generated based on settings inputted by the user.
 *
 * @Route("/server/operations")
 * @author R.J. Keller <rjkellercode@pixonite.com>
 */
class AutomaticBuildController extends Controller
{
    /**
     * Lists all Server entities.
     *
     * @Route("/create-vm/{server_id}", name="server_operations_create-vm")
     * @Template
     */
    public function buildVmAction($server_id)
    {
        $em = $this->getDoctrine()->getManager();
        $templating = $this->get('templating');

        $server = $em->getRepository("PixoniteServerManagerBundle:Server")
            ->findOneById($server_id);

        $commands = $templating->render(
            'PixoniteServerManagerBundle:ConfigFiles:createVm.txt.twig',
            array('server' => $server));

        $c = new GearmanClient();
        $c->addServer();
        $c->doBackground('buildserver_bash', $commands);

        return array("server" => $server);
    }

    /**
     * Lists all Server entities.
     *
     * @Route("/installNow/{server_id}", name="server_operations_install")
     */
    public function installAction($server_id)
    {
        $em = $this->getDoctrine()->getManager();

        $server = $em->getRepository("PixoniteServerManagerBundle:Server")
            ->findOneById($server_id);


        $logFile = new LogFile();
        $logFile->serverId = $server_id;
        $logFile->logFileName = uniqid("/var/log/buildServer/". $server->hostname . "-") . ".txt";
        $em->persist($logFile);
        $em->flush();


        $templating = $this->get('templating');
        $commands = $templating->render(
            'PixoniteServerManagerBundle:ConfigFiles:startInstall.txt.twig',
            array(
                'server' => $server,
                'privateIp' => $_GET['ip_address'],
                'logFile' => $logFile,
             ));

        $c = new GearmanClient();
        $c->addServer();
        $c->doBackground('buildserver_bash', $commands);

        return $this->redirect($this->generateUrl(
            'server_log-viewer',
            array("logFileId" => $logFile->id)
        ));
    }


    /**
     * Lists all Server entities.
     *
     * @Route("/log-viewer/{logFileId}", name="server_log-viewer")
     */
    public function showStatusAction($logFileId)
    {
        $em = $this->getDoctrine()->getManager();
        $logFile = $em->getRepository("PixoniteServerManagerBundle:LogFile")
            ->findOneById($logFileId);

        require __DIR__ . '/../php-tail-read-only/PHPTail.php';

        $tail = new \PHPTail($logFile->logFileName);

        /**
         * We're getting an AJAX call
         */
        if(isset($_GET['ajax']))  {
            echo $tail->getNewLines($_GET['lastsize'], $_GET['grep'], $_GET['invert']);
            die();
        }

        /**
         * Regular GET/POST call, print out the GUI
         */
        $tail->generateGUI();
        die();
    }

    /**
     * Lists all Server entities.
     *
     * @Route("/log-viewer/{logFileId}/raw", name="server_log-viewer_raw")
     * @Template
     */
    public function showLogFileAction($logFileId)
    {
        $em = $this->getDoctrine()->getManager();
        $logFile = $em->getRepository("PixoniteServerManagerBundle:LogFile")
            ->findOneById($logFileId);

        return array(
            "logFile" => $logFile,
            "funcs" => $this,
        );
    }

    public function streamOutLogfile($file)
    {
        $f = fopen($file, "r");
        while ($s = fread($f, 600))
            echo $s;
        fclose($f);
    }
}
