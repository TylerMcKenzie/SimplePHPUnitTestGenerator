<?php
/**
 * Created by Tyler Mckenzie
 * Date: 4/10/2019
 */

require_once(__DIR__ . "/vendor/autoload.php");
//include "./src/PHPUnitGenerator.php";

$file = "";
$skip = null;

for ($i = 0; $i < count($argv); $i++) {
	$current_arg = $argv[$i];
	if (!empty($skip) && $current_arg === $skip) { continue; }

	switch ($current_arg) {
		case "-f":
		case "--file":
			$next = $argv[$i+1];
			$file = $next;
			$skip = $next;
			break;
		default:
			break;
	}
}

$code_gen = new Phptestgen\PHPUnitGenerator();

$code_gen->setFile($file);
$code_gen->run();
