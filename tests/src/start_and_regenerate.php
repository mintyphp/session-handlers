<?php
session_start();
$_SESSION['user'] = 1;
session_regenerate_id(true);
