<?php
require_once __DIR__ . '/includes/guard.php';

$_SESSION = [];
session_destroy();

rediriger('/nolabel/index.php');