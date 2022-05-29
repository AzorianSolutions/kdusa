<?php

spl_autoload_register(function ($name) {
	$managedNamespaces = ['Spidermatt'];
	$managed = false;

	foreach($managedNamespaces as $ns) {
		if(substr($name, 0, strlen($ns) + 1) == "$ns\\") {
			$managed = true;
			break;
		}
	}

	if(!$managed) return false;

	$libPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR
		. str_replace('\\', '/', $name) . '.php';

	require_once $libPath;
});