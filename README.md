# MintyPHP session save handlers

This repository contains a set of PHP session save handlers that support locking. I also contains a test suite to prove that they do. Current handlers are:

- FilesSessionHandler.php - a session handler that locks using ".lock" files instead of "flock" calls.
- MemcacheSessionHandler.php - a session handler that stores it's session data in Memcache.
- RedisSessionHandler.php - a session handler that stores it's session data in Redis.

## Requirements

You can install the dependencies of this script using:

    sudo apt install php-cli php-curl

Optional dependencies can be installed using:

    sudo apt install memcached php-memcache redis php-redis

You need PHP 7.4 or higher to run the code.

## Using the handlers

You can use the Memcache handler by adding these two lines to your PHP code:

    ini_set('session.save_path', 'tcp://localhost:11211');
    session_set_save_handler(new MemcacheSessionHandler(), true);

Note that these lines must be executed before the "session_start()" call.

## Running the tests

You can run the tests from the command line using:

    php run-tests.php

The code will execute in about 1 second and test 14 HTTP calls in 3 save handlers. The following output means that the tests succeeded:

    default   : OK
    files     : OK
    memcache  : OK
    redis     : OK

The word "FAILED" appears on a failed test and "SKIPPED" is shown when the PHP module is not loaded for either Redis or Memcache.

## Stress testing

Use this for 100 runs:

    for i in `seq 1 100`; do php run-tests.php silent; done

As shown, you may use the argument "silent" to suppress output on successful or skipped tests.
