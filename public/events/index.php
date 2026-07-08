<?php
// This file allows Laravel to handle the /events route even though the public/events directory exists.
// It intercepts the request and forwards it to Laravel's main index.php, spoofing the SCRIPT_NAME.

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__.'/../index.php');

require __DIR__.'/../index.php';
