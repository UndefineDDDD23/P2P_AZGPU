<?php
require __DIR__ . '/vendor/autoload.php';

use App\Logger\FileLogger;
use App\Server\WebSocketServer;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

define('PROJECT_ROOT', __DIR__);

$logger = new FileLogger(PROJECT_ROOT.'/log.txt');

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new WebSocketServer($logger)
        )
    ),
    8080
);

$server->run();
