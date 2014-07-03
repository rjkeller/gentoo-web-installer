<?php
namespace Oranges\misc;

use Oranges\framework\BuildOptions;

/**
 * Provides some really basic utility functions.
 */
class WgTextTools
{
	public static function hash($data, $salt = null)
	{
		$algorithm = "sha512";

		if ($algorithm == "sha1" && $salt != null)
			$data = substr($salt, 0, 9) . $data;
		else if ($salt != null)
			$data = $salt . "wg". $data ."wg". $salt;

		$hash = hash($algorithm, $data);
		return $hash;
	}

	public static function uniqueid($num = -1)
	{
		if ($num == -1)
			return md5(uniqid(rand(), true));
		else
			return substr(WgTextTools::uniqueid(), 0, $num);
	}

	public static function truncate($str, $num = 55)
	{
		if (strlen($str) > $num)
			return substr($str, 0, $num) . "...";
		else
			return $str;
	}
}
