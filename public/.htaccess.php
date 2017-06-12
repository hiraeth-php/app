<?php

//
// CLI Server compatibility
//

$extension = pathinfo($_SERVER['REQUEST_URI'], PATHINFO_EXTENSION);
$filepath  = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['REQUEST_URI'];

if ($extension == 'html') {
	if (is_file($filepath)) {
		header('HTTP/1.1 301 Moved Permanently');
		header('Location: ' . substr($_SERVER['REQUEST_URI'], 0, -5));
		exit(0);
	}
}

if (!is_file($_SERVER['DOCUMENT_ROOT'] . '/.nostatic')) {
	if (!is_dir($filepath)) {
		if (is_file($filepath . '.html')) {
			include($filepath . '.html');
			exit(0);
		}

	} else {
		if (is_file($filepath . '/index.html')) {
			include($filepath . '/index.html');
			exit(0);
		}
	}
}

if (is_file($filepath)) {
	return FALSE;
}

include($_SERVER['DOCUMENT_ROOT'] . '/index.php');
