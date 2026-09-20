<?php

require_once __DIR__ . '/../src/Storage.php';
require_once __DIR__ . '/../src/Auth.php';

use Glider\Auth;

Auth::logout();
header('Location: /login.php');
exit;