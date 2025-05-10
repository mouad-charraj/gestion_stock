<?php
use WebSocket\Client; // Make sure this matches your WebSocket client library

function notifyClients($message) {
    try {
        $client = new Client("ws://localhost:8080"); // Update with your WS server address if different
        $client->send($message);
        $client->close();
    } catch (Exception $e) {
        echo "WebSocket error: " . $e->getMessage();
    }
}
