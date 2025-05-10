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

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if ($data) {
            echo "Message received: " . print_r($data, true) . "\n";
            $this->broadcast($data, $from);  // Pass the sender
        }
    }
    
    public function broadcast($message, $from) {
        foreach ($this->clients as $client) {
            if ($client !== $from) {
                $client->send(json_encode($message));
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