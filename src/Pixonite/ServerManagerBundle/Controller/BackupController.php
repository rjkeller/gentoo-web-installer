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

use Pixonite\ServerManagerBundle\Helper\ExecOnServer;

use GearmanClient;

/**
 * Experimental and incomplete system to help with managing gentoo backups.
 *
 * @Route("/backups")
 */
class BackupController extends Controller
{

	/**
	 * @Route("/", name="server_backups")
	 */
	public function backupListAction()
	{
		if ($_GET['create'])
		{
			$em = $this->getDoctrine()->getManager();
			$server = $em->getRepository('PixoniteServerManagerBundle:Server')
				->find($_GET['server_id']);

			ExecOnServer::runShellCommands($server,
				$this->generateUrl('server_backups_create', array('server_id' => $_GET['server_id']))
			);
		}
	}

	/**
	 * @Route("/server/{server_id}/create", name="server_backups_create")
     * @Template("PixoniteServerManagerBundle:Backup:createBackup.txt.twig")
	 */
	public function createBackupAction($server_id)
	{
		$em = $this->getDoctrine()->getManager();
		$server = $em->getRepository('PixoniteServerManagerBundle:Server')
			->find($_GET['server_id']);

		$backup_name = $server->name . '_' .date('Ymd_His_' . rand());

		$backup = new Backup();
		$backup->server_ip = $server->id;
		$backup->filename = $backup_name;
		$em->persist($backup);
		$em->flush();

		return array(
			'server' => $server->id,
			'backup_name' => $backup_name,
		);
	}

	/**
	 * @Route("/server/{server_id}/restore", name="server_backups_restore")
     * @Template("PixoniteServerManagerBundle:Backup:restoreBackup.txt.twig")
	 */
	public function restoreBackupAction($backup_id, $server_id)
	{
	}

	/**
	 * @Route("/backup/{backup_id}/delete", name="server_backups_delete")
     * @Template("PixoniteServerManagerBundle:Backup:deleteBackup.txt.twig")
	 */
	public function deleteBackupAction($backup_id)
	{
		$em = $this->getDoctrine()->getManager();
		$backup = $em->getRepository('PixoniteServerManagerBundle:Backup')
			->find($_GET['backup_id']);

		return array('backup_name' => $backup->filename);
	}
}
