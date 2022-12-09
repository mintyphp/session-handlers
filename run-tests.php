<?php
chdir(__DIR__);
$handlers = [
    'standard' => [
        'default' => [],
        'memcachedn' => ['memcached'],
        'redisn' => ['redis'],
    ],
    'strict' => [
        'files' => [],
        'memcached' => ['memcached'],
        'redis' => ['redis'],
    ],
];
$parallel = 100;
// execute single test
if ($_SERVER['SERVER_PORT'] ?? 0) {

    header('Content-Type: text/plain');

    $path = trim($_SERVER['QUERY_STRING'], '/');
    list($strictMode, $handlerName, $fileName) = explode('/', $path, 3);

    switch ($handlerName) {
        case 'redis':
            ini_set('session.save_path', 'tcp://localhost:6379');
            include 'src/RedisSessionHandler.php';
            $handler = new MintyPHP\RedisSessionHandler();
            break;
        case 'redisn':
            ini_set('session.save_path', 'tcp://localhost:6379');
            include 'src/NativeRedisSessionHandler.php';
            $handler = new MintyPHP\NativeRedisSessionHandler();
            break;
        case 'memcached':
            ini_set('session.save_path', 'localhost:11211');
            include 'src/MemcachedSessionHandler.php';
            $handler = new MintyPHP\MemcachedSessionHandler();
            break;
        case 'memcachedn':
            ini_set('session.save_path', 'localhost:11211');
            ini_set('memcached.sess_lock_wait_min', 20); // 20ms (default = 150ms)
            include 'src/NativeMemcachedSessionHandler.php';
            $handler = new MintyPHP\NativeMemcachedSessionHandler();
            break;
        case 'files':
            include 'src/FilesSessionHandler.php';
            $handler = new MintyPHP\FilesSessionHandler();
            break;
        case 'default':
            $handler = new \SessionHandler();
            break;
        default:
            die('invalid handler name');
    }

    include 'src/LoggingSessionHandler.php';
    ini_set('session.use_strict_mode', $strictMode == 'strict');
    session_set_save_handler(new MintyPHP\LoggingSessionHandler($handler), true);
    header('X-Session-Save-Path: ' . ini_get('session.save_path'));

    ob_start();

    if (!preg_match('/^[a-z0-9_-]+$/', $fileName)) {
        die('invalid file name');
    }
    if (file_exists("tests/src/$fileName.php")) {
        include "tests/src/$fileName.php";
    }

    // get timestamp and content
    list($msec, $sec) = explode(' ', microtime());
    $timestamp = $sec . substr($msec, 2, 6);
    header("X-Session-Flush-At: $timestamp");
    ob_end_flush();

    die();
}
// start test runner
foreach ($handlers as $strictMode => $strictModeHandlers) {
    foreach ($strictModeHandlers as $handlerName => $extensions) {
        // check extension
        $extensionsLoaded = true;
        foreach ($extensions as $extension) {
            if (!extension_loaded($extension)) {
                $extensionsLoaded = false;
            }
        }
        if (!$extensionsLoaded) {
            if (($argv[1] ?? '') != 'silent') {
                echo sprintf("%-11s: SKIPPED\n", $handlerName);
            }
            continue;
        }
        // start servers
        $serverPids = [];
        for ($j = 0; $j < $parallel; $j++) {
            $port = 9000 + $j;
            $serverPids[] = trim(exec("php -S localhost:$port > tmp/$port.server.log 2>&1 & echo \$!"));
        }
        // wait for ports
        for ($j = 0; $j < $parallel; $j++) {
            $port = 9000 + $j;
            while (false === ($fp = @fsockopen('localhost', $port, $errCode, $errStr))) {
                usleep(10 * 1000);
            }
            fclose($fp);
        }
        // execute scenarios
        $testsFailed = 0;
        $testsExecuted = 0;
        foreach (glob("tests/$strictMode/*.log") as $testFile) {
            $content = file_get_contents($testFile);
            list($head, $body) = explode("\n===\n", $content, 2);
            $paths = [];
            foreach (explode("\n", trim($head)) as $line) {
                list($count, $path) = explode(' ', $line);
                $paths[] = [$path, $count];
            }
            $oldSessionId = '';
            $sessionName = '';
            $sessionId = '';
            $responses = [];
            // execute requests
            foreach ($paths as list($path, $count)) {
                $mh = curl_multi_init();
                $chs = [];
                for ($j = 0; $j < $count; $j++) {
                    $port = 9000 + $j;
                    $ch = curl_init("http://localhost:$port/run-tests.php?$strictMode/$handlerName/$path");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: $sessionName=$sessionId"]);
                    curl_setopt($ch, CURLOPT_HEADER, true);
                    curl_multi_add_handle($mh, $ch);
                    $chs[$j] = $ch;
                }
                $running = null;
                do {
                    curl_multi_exec($mh, $running);
                } while ($running);
                for ($j = 0; $j < $count; $j++) {
                    $port = 9000 + $j;
                    file_put_contents("tmp/$port.client.log", curl_multi_getcontent($chs[$j]));
                }
                flush();
                $results = [];
                for ($j = 0; $j < $count; $j++) {
                    $port = 9000 + $j;
                    // parse response into headers and body
                    list($header, $logFile) = explode("\r\n\r\n", trim(file_get_contents("tmp/$port.client.log")), 2);
                    $headerLines = explode("\r\n", $header);
                    $headers = [];
                    array_shift($headerLines);
                    foreach ($headerLines as $headerLine) {
                        list($key, $value) = explode(': ', $headerLine);
                        $headers[$key] = $value;
                    }
                    // detect session name and session id
                    if (isset($headers['Set-Cookie'])) {
                        $oldSessionId = $sessionId;
                        list($sessionName, $sessionId) = explode('=', explode(';', $headers['Set-Cookie'])[0]);
                    }
                    // replace random session ids
                    $replacements = [
                        $sessionId => '{{current_random_session_id}}',
                        $oldSessionId => '{{previous_random_session_id}}',
                    ];
                    if (isset($headers['X-Session-Save-Path'])) {
                        $replacements[$headers['X-Session-Save-Path']] = '{{session_save_path}}';
                    }
                    $resultLogFile = str_replace(array_keys($replacements), array_values($replacements), $logFile);
                    // store with flush time as key (if available)
                    if (isset($headers['X-Session-Flush-At'])) {
                        $timestamp = $headers['X-Session-Flush-At'];
                        $results[$timestamp] = $resultLogFile;
                    } else {
                        $results[] = $resultLogFile;
                    }
                }
                ksort($results);
                $responses = array_merge($responses, $results);
            }
            // compare and report
            $newbody = implode("\n---\n", $responses);
            if (trim($body)) {
                if ($body != $newbody) {
                    echo "$testFile.$handlerName.out - FAILED\n";
                    file_put_contents("$testFile.$handlerName.out", "$head\n===\n$newbody");
                    $testsFailed += 1;
                }
            } else {
                file_put_contents($testFile, "$head\n===\n$newbody");
            }
            $testsExecuted += 1;
        }
        // stop servers
        foreach ($serverPids as $serverPid) {
            exec("kill $serverPid");
        }
        if (($argv[1] ?? '') != 'silent') {
            echo sprintf("%-11s: %s\n", $handlerName, $testsFailed ? 'FAILED' : 'OK');
        }
    }
}
