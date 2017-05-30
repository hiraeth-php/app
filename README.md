# Hiraeth

> Hiraeth (pronounced [hiraɪ̯θ][1]) is a Welsh word for which there is no direct English translation. The online Welsh-English dictionary of the University of Wales, Lampeter likens it to homesickness tinged with grief or sadness over the lost or departed. -- Wikipedia

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

## Integration

Hiraeth provides two interfaces for different types of integrations.  Both of these interfaces are designed to enable you to configure dependencies for your application.

1. Delegates
2. Providers

### Delegates

Delegates are basically factories for the dependency injector which provide some meta-information about what class is constructed, what interfaces it can serve, and ultimately, an instance to be injected.

If you only rely on concrete classes without any complex instantiation, delegates may not be necessary.  However, the benefit of of using delegates (or providers) is that you can make use of the `Hiraeth\Configuration` to change the behavior.

Let's take the `Hiraeth\Relay\RunnerDelegate` as an example.  First, let's define the delegate:

```php
<?php

namespace Hiraeth\Relay;

use Hiraeth;
use Relay;

/**
 *
 */
class RunnerDelegate implements Hiraeth\Delegate
{
	...
}
```

First, let's define the class which the delegate is responsible for constructing:

```php
/**
 * Get the class for which the delegate operates.
 *
 * @static
 * @access public
 * @return string The class for which the delegate operates
 */
static public function getClass()
{
	return 'Relay\Runner';
}
```

Next, we'll set any interfaces for which this class provides a concrete implementation:

```php
/**
 * Get the interfaces for which the delegate provides a class.
 *
 * @static
 * @access public
 * @return array A list of interfaces for which the delegate provides a class
 */
static public function getInterfaces()
{
	return [];
}
```

In this case, our `Relay\Runner` does not provide any concrete implementation for interfaces, so we just return an empty array.


From there we can define a constructor with additional dependencies we'll use to configure the `Relay\Runner`:


```php
/**
 * Construct the relay delegate
 *
 * @access public
 * @param Hiraeth\Configuration $config The Hiraeth configuration instance
 * @param Relay\ResolverInterface $resolver A resolver responsible for constructing middleware instances
 * @return void
 */
public function __construct(Hiraeth\Configuration $config, Relay\ResolverInterface $resolver)
{
	$this->config   = $config;
	$this->resolver = $resolver;
}
```

We will use the `Hiraeth\Configuration` to get configuration information and also request an implementation of `Relay\ResolverInterface` to pass to our Runner when we instantiate.

Lastly, let's look at our instantiation:

```php
/**
 * Get the instance of the class for which the delegate operates.
 *
 * @access public
 * @param Hiraeth\Broker $broker The dependency injector instance
 * @return Relay\Runner The instance of our relay runner
 */
public function __invoke(Hiraeth\Broker $broker)
{
	$queue  = $this->config->get('relay', 'middleware.queue', array());
	$runner = new Relay\Runner($queue, $this->resolver);

	if (in_array('Relay\Middleware\SessionHeadersHandler', $queue)) {
		ini_set('session.use_cookies', FALSE);
		ini_set('session.use_only_cookies', TRUE);
		ini_set('session.use_trans_sid', FALSE);
		ini_set('session.cache_limiter', '');
	}

	$broker->share($runner);

	return $runner;
}
```

Our injector is provided to our `__invoke()` method in the event that we need to build additional dynamic dependencies or, as seen in this case towards the end of the method, share our instance such that future requests for this dependency will return the same instance.

In addition to this, we get our first look at how we use the configuration.  Since we want to make our middlewares more easily configurable, we use a configuration file to get a list of our middleware classes.

In this example, our configuration file will simply be named `relay.jin` (note that the first argument for `$this->config->get()` references the configuration file without the extension). Contained within it, we'll provide our list of middlewares as an array:

```ini
[middleware]
queue = [
	"Relay\\Middleware\\ResponseSender",
	"Relay\\Middleware\\SessionHeadersHandler"
]
```

The final argument to our configuration call is a default value, in the case that the configuration file or the particular section/configuration data cannot be found.

Once the delegate and its configuration is defined, we can register the delegate with our application in the `app.jin` file by adding it to the delegates list.  Alternatively, we can add this information to the same `relay.jin` file since delegates/providers can be registered across any configuration file:

```ini
[application]
delegates = [
	"Hiraeth\\Relay\\RunnerDelegate",
]
```

Together, all of this means that when we request a `Relay\Runner` for dependency injection (for the first time), our delegate will be used to construct and configure that instance based on our configuration.

There is a slight problem with this initial implementation, however.  Our delegate requests an instance of the `Relay\ResolverInterface` which our dependency injector doesn't know how to make.  In order to let it know how to make that, we will need to create a delegate for our resolver as well.

Using the same principles as before, we come up with the following:

```php
namespace Hiraeth\Relay;

use Hiraeth;

/**
 *
 */
class ResolverDelegate implements Hiraeth\Delegate
{
	/**
	 * Get the class for which the delegate operates.
	 *
	 * @static
	 * @access public
	 * @return string The class for which the delegate operates
	 */
	static public function getClass()
	{
		return 'Hiraeth\Relay\Resolver';
	}


	/**
	 * Get the interfaces for which the delegate provides a class.
	 *
	 * @static
	 * @access public
	 * @return array A list of interfaces for which the delegate provides a class
	 */
	static public function getInterfaces()
	{
		return [
			'Relay\ResolverInterface'
		];
	}


	/**
	 * Get the instance of the class for which the delegate operates.
	 *
	 * @access public
	 * @param Hiraeth\Broker $broker The dependency injector instance
	 * @return Object The instance of the class for which the delegate operates
	 */
	public function __invoke(Hiraeth\Broker $broker)
	{
		return new Resolver($broker);
	}
}
```

Note, that unlike with our runner's delegate, here we make use of the `getInterfaces()` method to say that in cases where a `Relay\ResolverInterface` is required (such as by our runner's delegate), the dependency injector should also use this delegate.

Lastly, we add the resolver delegate to our configuration as well:

```ini
[application]
delegates = [
	"Hiraeth\\Relay\\RunnerDelegate",
	"Hiraeth\\Relay\\ResolverDelegate"
]
```

For a complete example of how this all comes together, check out [the `hiraeth/relay` package](https://github.com/hiraeth-php/relay).  This package provides both the delegates seen above, as well as the requisite configuration and supporting files to make use of relay in your application (`.htaccess`, `index.php`).

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

While providers are extremely useful especially for the injection of commonly used objects, it is important to remember that delegates can also prepare instances in this way before returning them.  Creating an interface and a provider in order to extend a concrete implementation may not be the best option.
