# MintyPHP session save handlers

This repository contains a set of PHP session save handlers that support locking. I also contains a test suite to prove that they do. Current handlers are:

- **standard**
  - **default** ([SessionHandler](https://www.php.net/manual/en/class.sessionhandler.php))  
    Uses the "files" session module (PHP built-in).
  - **memcachedn** ([NativeMemcachedSessionHandler](src/NativeMemcachedSessionHandler.php))  
    Uses the "memcached" session module (ext-memcached).
  - **redisn** ([NativeRedisSessionHandler](src/NativeRedisSessionHandler.php))  
    Uses the "redis" session module (ext-redis).
- **strict** ([docs](https://www.php.net/manual/en/session.configuration.php#ini.session.use-strict-mode))
  - **files** ([FilesSessionHandler](src/FilesSessionHandler.php))  
    Locks using ".lock" files instead of "flock" calls.
  - **memcached** ([MemcachedSessionHandler](src/MemcachedSessionHandler.php))  
    Locks using Memcache's atomic "add" operation.
  - **redis** ([RedisSessionHandler](src/RedisSessionHandler.php))  
    Locks using Redis' atomic "setNx" operation.

## Requirements

You can install the dependencies of this script using:

    sudo apt install php-cli php-curl

Optional dependencies can be installed using:

    sudo apt install memcached php-memcached redis php-redis

You need PHP 7.4 or higher to run the code.

## Using the handlers

This package is on [Packagist](https://packagist.org/packages/mintyphp/session-handlers) and can be installed using [Composer](https://getcomposer.org/download/), using:

    composer require mintyphp/session-handlers

You can use the Redis handler by adding these two lines to your PHP code:

    ini_set('session.save_path', 'tcp://localhost:6379');
    ini_set('session.use_strict_mode', true);
    session_set_save_handler(new RedisSessionHandler(), true);

Note that these lines must be executed before the "session_start()" call.

## Running the tests

You can run the tests from the command line using:

    php run-tests.php

The code will execute in about 1 second per handler and test 104 HTTP calls for each handler. The following output means that the tests succeeded:

    standard - default    : OK
    standard - memcachedn : OK
    standard - redisn     : OK
    strict   - files      : OK
    strict   - memcached  : OK
    strict   - redis      : OK

The word "FAILED" appears on a failed test and "SKIPPED" is shown when the PHP module is not loaded for either Redis or Memcache.

## Stress testing

Use this for 100 runs:

    for i in `seq 1 100`; do php run-tests.php silent; done

As shown, you may use the argument "silent" to suppress output on successful or skipped tests.

## Links to other locking handlers

Below you find a few other implementations of locking Session handlers:

- https://github.com/mevdschee/symfony-session-tests (Symfony "files")
- https://github.com/stechstudio/laravel-raw-sessions (Laravel "files")
- https://github.com/1ma/RedisSessionHandler (Redis)
- https://github.com/colinmollenhour/php-redis-session-abstract (Redis)
- https://github.com/kronostechnologies/redis-session-handler (Redis)

Enjoy!
