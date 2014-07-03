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
namespace Pixonite\ServerManagerBundle\Helper;

use Pixonite\ServerManagerBundle\Entity\Server;
use Pixonite\ServerManagerBundle\Entity\LogFile;
use Oranges\MasterContainer;
use GearmanClient;

/**
 * Runs commands on a remote machine using GearMan and logs the results.
 */
class ExecOnServer
{
    /**
     * Runs the specified commands on the specified server.
     * 
     * @return LogFile - log file with the output.
     */
    public static function runShellCommands(Server $server, $commandsUrl, $doBackground = false)
    {
        $templating = MasterContainer::get('templating');
        $commands = $templating->render(
            'PixoniteServerManagerBundle:ConfigFiles:runCommand.txt.twig',
            array(
                'server' => $server,
                'file' => $commandsUrl,
            ));

        $logFile = new LogFile();
        $logFile->serverId = $server->id;
        $logFile->logFileName = uniqid("/var/log/buildServer/". $server->hostname . "-") . ".txt";
        $em->persist($logFile);
        $em->flush();

        //use a GearMan call to run the commands on bash on the specified
        //machine.
        $c = new GearmanClient();
        $c->addServer();
        if ($doBackground)
            $c->doBackground('buildserver_bash', $commands);
        else
            $c->do('buildserver_bash', $commands);

    }
}