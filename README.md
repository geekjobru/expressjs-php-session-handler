# PHP implementation of Node.js Express Session Handler

This library is meant to work as a Session Handler for PHP that is compatible with [express-session](https://github.com/expressjs/session).

This library writed for SOA (Service-oriented architecture) projects
for sharing session between a Node.js application and services writed on PHP.

## Requirements

- **PHP**: `7.2` or greater
- **phpredis**
- **php_serialize** Standart PHP Serialize Handler.

This library now created for using [express-session](https://github.com/expressjs/session)
with the [connect-redis](https://github.com/tj/connect-redis) Session Store as common session store.

## Installation

### Install Redis
You can install it using `pecl install redis`

In Docker file:
```dockerfile
FROM php:fpm
...
RUN pecl install redis && docker-php-ext-enable redis
```


### Composer

Just run to get the package from
[packagist.org](https://packagist.org/packages/geekjob/expressjs-php-session-handler):

```bash
composer require geekjob/expressjs-session-handler
```

## Setup and Usage

### Configure Node.js application

```js
app.use(session({
	name: 'sid',
	secret: 'secret key',
	cookie: {
		// Share cookie through sub domains if you use many domains for service architecture
		domain : '.your.domain',
		maxAge : Date.now() + 60000
	},
	store: new RedisStore({
		host  : 'redis',
		port  : 6379,
		client: redis,
		prefix: 'session:',
        ttl   : 3600 // 60 min
	})
}));
``` 


### Configure runtime

```php
require_once 'vendor/autoload.php';

\GeekJOB\ExpressjsSessionHandler::register([
	'name'   => 'sid',
	'secret' => 'secret key',
	'cookie' => [
		'domain'  => '.your.domain', // Share cookie through sub domains
		'path'    => '/',
		'maxage'  => strtotime('+1hour'), // Set maxage
	],
	'store' => [
		'handler' => 'redis',
		'path'    => 'tcp://127.0.0.1:6379',
		'prefix'  => 'session:',
        'ttl'	  => 3600 // 60 min
	],
	'secure' => false // Set to true if signature verification is needed.
]);
```

### Configure for production server via php.ini file

```ini
session.session_name = sid
session.save_handler = redis
session.save_path = "tcp://127.0.0.1/?prefix=session:"
session.serialize_handler = php_serialize

; After this number of seconds, stored data will be seen as 'garbage' and
; cleaned up by the garbage collection process.
; http://php.net/session.gc-maxlifetime
; default: session.gc_maxlifetime = 1440 
; Redis Sessions use this value for setting TTL
session.gc_maxlifetime =

; Lifetime in seconds of cookie or, if 0, until browser is restarted.
; http://php.net/session.cookie-lifetime
session.cookie_lifetime =
```

```php
require_once 'vendor/autoload.php';

\GeekJOB\ExpressjsSessionHandler::register([
	'secret' => 'secret key',
	'cookie' => [
		'domain'  => '.your.domain', // Share cookie through sub domains
		'path'    => '/',
	],
	'secure' => false // Set to true if signature verification is needed.
]);
```

### TODO
Unit tests :)
