# Hiraeth

> Hiraeth (pronounced [hiraɪ̯θ]) is a Welsh word for which there is no direct English translation. The online Welsh-English dictionary of the University of Wales, Lampeter likens it to homesickness tinged with grief or sadness over the lost or departed. -- Wikipedia

## A Nano-Framework for PHP

Hiraeth is the product of 10+ years of PHP framework development.  Over those 10+ years, the PHP ecosystem has grown and developed to include some truly great libraries and components.  At the beginning of that journey, developers who were concerned with ease of use, stability, or flexibility encountered many barriers in the course of application or website development.

These barriers no longer exist.  Hiraeth is designed to let you use and re-use the packages you love without locking you in.

## Getting Started

Installing:

```shell
composer create-project hiraeth/app hiraeth
```

Running the Development Server:

```shell
php bin/server
```

You can now visit Hiraeth at [http://localhost:8080](http://localhost:8080).  If you need to adjust any of the server settings, check out the `config/app.jin` file and look for:

```json
[server]
php = php
host = localhost
port = 8080
docroot = public
```

## Hello World!

Let's create a new file in `public/hello.php`.  Begin by placing the following code at the top:

```php
<?php

for (
	$root_path  = __DIR__;
	$root_path != '/' && !is_file($root_path . DIRECTORY_SEPARATOR . 'composer.json');
	$root_path  = realpath($root_path . DIRECTORY_SEPARATOR . '..')
);
```

These first 7 lines simply track backwards until the `composer.json` file is discovered.  This is the "root" of our Hiraeth installation which will be contained in the `$root_path` variable.  From there, we include the composer autoloader and instantiate our application:

```php
$loader  = require $root_path . '/vendor/autoload.php';
$hiraeth = new Hiraeth\Application($root_path, $loader);
```

Lastly, we execute the application by providing our post-boot logic:

```php
exit($hiraeth->run(function() {
	echo 'Hello World!';
}));
```

Save the file and visit [http://localhost:8080/hello.php](http://localhost:8080/hello.php) to see your message.  _Note: The post-boot logic must be in the form of a `Closure` because it will be bound to the scope of our `Hiraeth\Application`._

## Configuration

Configuration files in Hiraeth are [Jsonified Ini Notation](https://github.com/dotink/jin) or "JIN" files.  Put simply, the structure of these files is exactly like an INI file, but the values are fully qualified JSON.  Let's create a test file at `config/text.jin`.

```json
subject = "World!"

[hello]
translations = [
	"Hello",
	"Hola",
	"Bonjour",
	"Ciao"
]
```

### Getting Data

Returning to our `public/hello.php` file, we can use our config to make things a bit more interesting:

```php
exit($hiraeth->run(function() {
	$subject      = $this->config->get('test', 'subject', 'World!');
	$translations = $this->config->get('test', 'hello.translations', array());

	foreach ($translations as $translation) {
		echo $translation . ' ' . $subject . '<br />';
	}
}));
```

The `get()` method on a `Hiraeth\Configuration` takes 3 arguments:

1. The file path (minus the `.jin` extension) relative to the configuration root.
2. The data path.  Each path element is separated by a `.` in the path string.
3. A default value in the event that the requisite configuration value is not set.

It is also possible to get data across multiple configuration files by replacing the file path with a `*`:

```php
exit($hiraeth->run(function() {
	$subject      = $this->config->get('test', 'subject', 'World!');
	$translations = $this->config->get('*', 'hello.translations', array());

	foreach ($translations as $path => $values) {
		foreach ($values as $translation) {
			echo $translation . ' ' . $subject . '<br />';
		}
	}
}));
```

_Note: That when retrieving values across multiple configurations, the values are not merged.  Getting from mulitple configurations will always return an array whose keys are the configuration path and whose values are the requested
data._

## Application

In addition to the `run()` method which bootstraps the system and then executes your post-boot logic, the `Hireath\Application` provides a number of methods for working with the application environment.  For example, you can retrieve environment settings:

```php
exit($hiraeth->run(function() {
	echo 'The DEBUG environment setting is set to ' . $this->getEnvironment('DEBUG', 0);
}));
```

The `getEnvironment()` method is a simple way to get an environment setting based on name, and if it's not set, to enable the return of a default.  There's no special magic, here.  It's just a wrapper around `getenv()`.  To simplify environment configuration, however, Hiraeth does support the use of a `.env` file.

It is also possible to easily check for files/directories relative to the document root and get their full path:

```php
exit($hiraeth->run(function() {
	if ($this->hasFile('README.md')) {
		echo file_get_contents($this->getFile('README.md'));
	}
}));
```

The above would echo the contents of this file (assuming it's not deleted).  It is also possible to create an empty file and the necessary directory structure, by providing `TRUE` as the second argument to `getFile()`.  If the file does not exist, Hiraeth will attempt to make it.

```php
exit($hiraeth->run(function() {
	$cache_path = 'writable/cache/nonexisting/path/mytool.cache';

	if ($this->hasFile($file)) {
		$data = require $this->getFile($cache_path);
	} else {
		$cache = $this->getFile($cache_path, TRUE);

		//
		// Do work to generated complex $data
		//

		file_put_contents($cache, $data);
	}

	//
	// Use $data
	//
}));
```

Similar to `hasFile()` and `getFile()` methods are the `hasDirectory()` and `getDirectory()` methods.  The only difference being that `getDirectory()` does not require any arguments and if no relative path is supplied it will return the full path of the application directory:

```php
exit($hiraeth->run(function() {
	echo 'Hiraeth is installed to ' . $this->getDirectory();
}));
```




## Dependency Injection

Dependency injection is an important feature in Hiraeth.  Although it is possible to configure dependency injection directly (`Hiraeth\Broker` is an alias for [the auryn dependency injector](https://github.com/rdlowrey/auryn)), Hiraeth is designed to encapsulate dependency configuration.  There are two forms of encapsulation depending on the complexity in setting up the dependency:

- Hiraeth\Delegate
- Hiraeth\Provider


### Simple Dependencies

For a simple dependency that requires no additional configuration or setup during instantiation, it is possible to inject this without any encapsulation:

```php
exit($hiraeth->run(function(Parsedown $parsedown) {
	$parsedown->text(file_get_contents($this->getFile('README.md')));
}));
```


### Delegates

Delegates are basically factories for the dependency injector which provide some meta-information about what class is constructed, what interfaces it can serve, and ultimately, an instance to be injected.

Let's create a simple delegate for [monolog](https://github.com/Seldaek/monolog) as an example.  The `Hiraeth\Delegate` interface requires the implementation of 3 methods:

##### `getClass()`

The `getClass()` method is responsible for returning the class for which the delegate operates.  That is, the class which the delegate is responsible for building.  In our example, this is quite simple:

```php
static public function getClass()
{
	return 'Monolog\Logger';
}
```

##### `getInterfaces()`

If the dependency the delegate is responsible for constructing should be used in an instance where an interface is typehinted, then we can return an array of those interfaces.  In our case, if the broker encounters any dependency for the `Psr\Log\LoggerInterface` interface, we will want it to use this logger.

```php
static public function getInterfaces()
{
	return [
		'Psr\Log\LoggerInterface'
	];
}
```
Note that both `getClass()` and `getInterfaces()` are static methods -- this is so that our delegate can be configured without instantiating any of the additional dependencies it may need to construct the object.

##### `__invoke(Hiraeth\Broker $broker)`

Last, but not least is the `__invoke()` method which takes a single argument which is the broker itself and is responsible for building the class:

```php
public function __invoke(Hiraeth\Broker $broker)
{
	$app      = $broker->make('Hiraeth\Application');
	$config   = $broker->make('Hiraeth\Config');
	$handlers = $config->get('monolog', 'handlers', array());
	$logger   = new Monolog\Logger('app');

	if (isset($handlers['file'])) {
		$logger->pushHandler(new Monolog\Handler\RotatingFileHandler(
			$app->getFile($handlers['file'])
		));
	}

	$broker->share($logger);

	return $logger;
}
```

In the example above, we use the `$broker` to additional make our share `$app` an `$config` instance.  However, an alternative approach to this is to implement a `__construct()` method which will also be dependency injected.  This has benefits both with regards to testing and also with regards making our dependencies clearer for code helpers and documentation generator:

```php
public function __construct(Hiraeth\Application $app, Hiraeth\Configuration $config)
{
	$this->app    = $app;
	$this->config = $config;
}
```

Now, instead of using the `$broker`, we can rewrite our `__invoke()` to use the contructor injected dependencies.

```php
public function __invoke(Hiraeth\Broker $broker)
{
	$logger   = new Monolog\Logger('app');
	$handlers = $this->config->get('monolog', 'handlers', array());

	if (isset($handlers['file'])) {
		$logger->pushHandler(new Monolog\Handler\RotatingFileHandler(
			$this->app->getFile($handlers['file'])
		));
	}

	$broker->share($logger);

	return $logger;
}
```

The final result can be seen in the `hiraeth/monolog` package in [the LoggerDelegate class](https://github.com/hiraeth-php/monolog/blob/master/src/LoggerDelegate.php).

#### Registering Delegates

In order to register a delegate for use by the system, you will need to add it to a `delegates` list in an `[application]` section of a configuration.  You can create a separate config file so that it can simply be copied to the configuration directory, for example `monolog.jin` or you could add it directly to the `app.jin` list of delegates:

```json
[application]
delegates = [
	"Hiraeth\\Monolog\\LoggerDelegate"
]
```

In this case, we'll add it to a separate `monolog.jin` file since we also use that file to configure the handlers in our `__invoke` method:

```
$handlers = $this->config->get('monolog', 'handlers', array());
```

Right now our delegate only supports a single handler (namely a file handler), so let's make sure we add that information as well:

```json
[handlers]
file = writable/logs/app.log
```

#### Using a Delegate

Once our delegate is created and registered with the system, anywhere we have dependency injection in use, we can get our logger:

```php
exit($hiraeth->run(function(Psr\Log\LoggerInterface $logger) {
	$logger->error('You forgot to write an application');
}));
```

## Providers

Providers are similar to delegates in that:

1. They are distinct classes which are designed to configure or add dependencies to instantiated dependencies.
2. They can request additional dependencies through the `__construct()` method.
3. They define a number of interfaces, for which they provide configuration or dependency information.

However, where delegates are responsible for constructing a concrete class, a provider is only responsible for configuration or setter-style dependency injection interfaces.  So, imagine if you will that you have a number of classes which all have optional caching ability.  In order to enable this caching ability, you create an interface:

```php
interface Cacheable
{
	public function setCache(Cache $cache);
}
```

Rather than create delegates for every class which might use caching (especially if there's no other need for a delegate), it is possible to simply create a provider:

```php
namespace Hiraeth\Relay;

use Hiraeth;

/**
 *
 */
class CacheProvider implements Hiraeth\Provider
{
	/**
	 * The cache instance to provide
	 *
	 * @access protected
	 * @var Cache A cache instance
	 */
	protected $cache = NULL;

	/**
	 * Get the interfaces for which the provider operates.
	 *
	 * @access public
	 * @return array A list of interfaces for which the provider operates
	 */
	static public function getInterfaces()
	{
		return [
			'Cacheable'
		];
	}


	/**
	 * Construct the provider with requisite dependencies.
	 *
	 * @access public
	 * @param Cache $cache A cache instance for the provider to provide
	 * @return void
	 */
	public function __construct(Cache $cache)
	{
		$this->cache = $cache;
	}


	/**
	 * Prepare the instance.
	 *
	 * @access public
	 * @return Object The prepared instance
	 */
	public function __invoke($cacheable_instance, Hiraeth\Broker $broker)
	{
		$cacheable_instance->setCache($this->cache);

		return $cacheable_instance;
	}
}
```

While providers are extremely useful especially for the injection of commonly used objects, it is important to remember that delegates can also prepare instances in this way before returning them.  Creating an interface and a provider in order to extend a concrete implementation may not be the best option
