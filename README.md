# Hiraeth

> Hiraeth (pronounced [hiraɪ̯θ][1]) is a Welsh word for which there is no direct English translation. The online Welsh-English dictionary of the University of Wales, Lampeter likens it to homesickness tinged with grief or sadness over the lost or departed. -- Wikipedia

## A Nano-Framework for PHP

Hiraeth is the product of 10+ years of PHP framework development.  Over those 10+ years, the PHP ecosystem has grown and developed to include some truly great libraries and components.  At the beginning of that journey, developers who were concerned with ease of use, stability, or flexibility encountered many barriers in the course of application or website development.

These barriers no longer exist.

## Getting Started

Installing:

```shell
composer create-project hiraeth/app hiraeth
```

Running the Development Server:

```shell
php bin/server
```

If you need to adjust any of the server settings, check out the `config/app.jin` file and look for:

```ini
[server]
php = php
host = localhost
port = 8080
docroot = public
```

You can now edit the `public/index.php` file to suit your needs.  The default file will serve up this README.  To get a better idea of how that works, let's take a look.

```php
for (
	$root_path  = __DIR__;
	$root_path != '/' && !is_file($root_path . DIRECTORY_SEPARATOR . 'composer.json');
	$root_path  = realpath($root_path . DIRECTORY_SEPARATOR . '..')
);
```

The first 5 lines of our `index.php` simply tracks backwards until the `composer.json` file is discovered.  This is the "root" of our Hiraeth installation.  From there, we include the composer autoloader and instantiate our application:

```php
$loader  = require $root_path . '/vendor/autoload.php';
$hiraeth = new Hiraeth\Application($root_path, $loader);
```

Lastly, we execute the application by providing our post-boot logic.  For the default `index.php`, this means getting a markdown parser dependency injected and outputting our markup with the rendered markdown.

```php
exit($hiraeth->run(function(Parsedown $parsedown) {
	?>
	<html>
		<head>
			<title>Welcome to Hiraeth</title>
			<link href="modest.css" rel="stylesheet" />
		</head>
		<body>
			<?= $parsedown->text(file_get_contents($this->getFile('README.md'))) ?>
		</body>
	</html>
	<?php
}));
```

The post-boot logic must be in the form of a `Closure`.  As you can see from `$this-getFile('README.md')` we take full advantage of PHP's ability to bind closures to a different scope.  So anything you execute in this callback runs within the scope of the `Hiraeth\Application`.
