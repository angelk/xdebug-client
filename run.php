<?php
error_reporting(E_ALL);

/* Allow the script to hang around waiting for connections. */
set_time_limit(0);

require_once './vendor/autoload.php';

$app = new Symfony\Component\Console\Application();
$app->add(new \App\Command\RunCommand());
$app->run();
