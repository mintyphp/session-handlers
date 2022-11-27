<?php
session_start(['use_strict_mode' => true, 'lazy_write' => true]);
$_SESSION['user'] = 1;
session_write_close();
session_regenerate_id(true);
