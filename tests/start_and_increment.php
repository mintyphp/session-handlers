<?php
session_start();
$_SESSION['user'] += 1;
usleep(random_int(0, 2000));
