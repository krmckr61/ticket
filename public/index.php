<?php

require_once __DIR__ . '/../vendor/autoload.php';
$pf = new Ticket\PathFinder();
$app = $pf->getApp();
//routes
$app->mount('/', new \Ticket\User\Controllers\UserController());
$app->mount('/admin', new \Ticket\Admin\Controllers\AdminController());
$app->run();