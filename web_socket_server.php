<?php
require 'vendor/autoload.php';
require 'ProductNotifier.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

echo "Starting WebSocket server...\n";

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ProductNotifier()
        )
    ),
    8080 // Change port if needed
);

echo "WebSocket server started on ws://localhost:8080\n";
$server->run();

?>