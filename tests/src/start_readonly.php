<?php
session_start(['read_and_close' => true]);
session_write_close();
$_SESSION['user'] = 1;
