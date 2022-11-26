<?php

use MintyPHP\FilesSessionHandler;
use MintyPHP\LoggingSessionHandler;

include '../FilesSessionHandler.php';
include '../LoggingSessionHandler.php';

header('Content-Type: text/plain');

session_set_save_handler(new LoggingSessionHandler(new FilesSessionHandler()), true);

ob_start();
var_dump('session_start');
var_dump(session_start(['use_strict_mode' => true, 'lazy_write' => true]));
$_SESSION['user'] = 1;
var_dump('session_write_close');
var_dump(session_write_close());
//var_dump('session_regenerate_id');
//var_dump(session_regenerate_id(true));
ob_end_flush();
