<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ProductNotifier implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    // Called when a new connection is established
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n"; // For debugging purposes
    }

    // Called when a connection is closed
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection closed: {$conn->resourceId}\n"; // For debugging purposes
    }

    // Called when a message is received
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if ($data) {
            echo "Message received: " . print_r($data, true) . "\n";
            $this->broadcast($data);  // Broadcasting the message to all connected clients
        }
    }
    
    public function broadcast($message) {
        foreach ($this->clients as $client) {
            if ($client !== $this->from) {
                $client->send(json_encode($message));  // Send the message to each client
            }
        }
    }

    // Called when an error occurs
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n"; // For debugging purposes
        $conn->close();
    }
}

?>