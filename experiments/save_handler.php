<?php

use MintyPHP\FilesSessionHandler;
use MintyPHP\LoggingSessionHandler;

include '../FilesSessionHandler.php';
include '../LoggingSessionHandler.php';

session_set_save_handler(new LoggingSessionHandler(new FilesSessionHandler()), true);

//session_set_save_handler(
//    self::open,
//    self::close,
//    self::read,
//    self::write,
//    self::destroy,
//    self::gc,
//    self::create_sid,
//    self::validate_sid,
//    self::update_timestamp
//);

session_start(['use_strict_mode' => true, 'lazy_write' => true]);
$_SESSION['user'] = 2;
session_write_close();
//session_regenerate_id(true);
