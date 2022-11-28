# MintyPHP session save handlers

A few PHP session save handlers that support locking and a test suite to prove that they do. Current handlers are:

- FilesSessionHandler.php - a session handler that locks using ".lock" files instead of "flock" calls.
- MemcacheSessionHandler.php - a session handler that stores it's session data in Memcache.
- RedisSessionHandler.php - a session handler that stores it's session data in Redis.

## Requirements

You can install the dependencies of this script using:

    sudo apt install php-cli curl

You need PHP 7.4 or higher to run the code. 

## Using the handlers

You can use the Memcache handler by adding these two lines to your PHP code:

    ini_set('session.save_path', 'tcp://localhost:11211');
    session_set_save_handler(new MemcacheSessionHandler(), true);

Note that these lines must be executed before the "session_start()" call.

## Running the tests

You can run the tests from the command line using:

    php run-tests.php

The code will execute in about 1 second and test 12 HTTP calls in 3 save handlers. No output means that the tests succeeded.
