<?php
chdir(__DIR__);
$handlers = ['default', 'files'];
$parallel = 10;
// execute single test
if ($_SERVER['SERVER_PORT'] ?? 0) {

    header('Content-Type: text/plain');

    $path = trim($_SERVER['REQUEST_URI'], '/');
    list($handlerName, $fileName) = explode('/', $path, 2);

    switch ($handlerName) {
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
    session_set_save_handler(new MintyPHP\LoggingSessionHandler($handler), true);

    ob_start();

    if (!preg_match('/^[a-z0-9_-]+$/', $fileName)) {
        die('invalid file name');
    }
    if (file_exists("tests/src/$fileName.php")) {
        include "tests/src/$fileName.php";
    }

    ob_end_flush();

    die();
}
// start test runner
foreach ($handlers as $handlerName) {
    $serverPids = [];
    for ($j = 0; $j < $parallel; $j++) {
        $port = 9000 + $j;
        $serverPids[] = trim(exec("php -S localhost:$port run-tests.php > /dev/null 2>&1 & echo \$!"));
    }
    foreach (glob("tests/*.log") as $testFile) {
        $content = file_get_contents($testFile);
        list($head, $body) = explode("\n===\n", $content, 2);
        $paths = [];
        foreach (explode("\n", trim($head)) as $line) {
            list($count, $path) = explode(' ', $line);
            $paths[$path] = $count;
        }
        $sessionId = bin2hex(random_bytes(16));
        for ($j = 0; $j < $parallel; $j++) {
            $port = 9000 + $j;
            if (file_exists("tmp/$port.log")) {
                unlink("tmp/$port.log");
            }
        }
        $responses = [];
        foreach ($paths as $path => $count) {
            $clientPids = [];
            for ($j = 0; $j < $count; $j++) {
                $port = 9000 + $j;
                $clientPids[] = trim(exec("curl -sS -b 'PHPSESSID=$sessionId' http://localhost:$port/$handlerName/$path -o tmp/$port.log & echo \$!"));
            }
            exec("wait " . implode(' ', $clientPids));
            flush();
            $results = [];
            for ($j = 0; $j < $count; $j++) {
                $port = 9000 + $j;
                $logFile = trim(file_get_contents("tmp/$port.log"));
                $results[] = str_replace($sessionId, '**********[random sid]**********', $logFile);
            }
            sort($results);
            $responses = array_merge($responses, $results);
        }
        //diff here.
        $body = implode("\n---\n", $responses);
        if (!trim($body)) {
            file_put_contents($testFile, "$head\n===\n$newbody");
        }
    }
    foreach ($serverPids as $serverPid) {
        exec("kill $serverPid");
    }
}
