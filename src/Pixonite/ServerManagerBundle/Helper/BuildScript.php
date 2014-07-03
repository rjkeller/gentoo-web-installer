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

class BuildScript
{
	private $serverScript;
	private $step = 1;

	public function __construct(ServerConfigScript $serverScript) {
		$this->serverScript = $serverScript;
	}

	public function __call($name, $arguments)
	{
/*
		echo "\n";
		echo "echo '**** DEBUG ". $this->step ." ". $name ." ****'\n";
		echo "\n";

		echo "df -h\n";
*/
		$printArgs = "";
		if (isset($arguments[0]) && is_string($arguments[0]))
			$printArgs = $arguments[0];
		if ($name == "cmd")
			$printArgs = "";

		echo "\n";
		echo "echo '**** STEP ". $this->step ." ". $name ." ". $printArgs ." ****'\n";
		echo "\n";

		call_user_func_array(array($this->serverScript, $name), $arguments);

		$this->step++;
	}
}
