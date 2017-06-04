<?php

//
// Track backwards until we discover our composer.json.
//

for (
	$root_path  = __DIR__;
	$root_path != '/' && !is_file($root_path . DIRECTORY_SEPARATOR . 'composer.json');
	$root_path  = realpath($root_path . DIRECTORY_SEPARATOR . '..')
);

$loader  = require $root_path . '/vendor/autoload.php';
$hiraeth = new Hiraeth\Application($root_path, $loader);

exit($hiraeth->run(function(Parsedown $parsedown) {
	?>
	<html>
		<head>
			<title>Welcome to Hiraeth</title>
			<link href="hiraeth.css" rel="stylesheet" />
			<link href="prism.css" rel="stylesheet" />
			<script src="prism.js" type="text/javascript"></script>
		</head>
		<body>
			<?= $parsedown->text(file_get_contents($this->getFile('README.md'))) ?>
		</body>
	</html>
	<?php
}));
