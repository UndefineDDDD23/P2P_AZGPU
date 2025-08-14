<?php

namespace App\Server;

use App\Server\MessageHandler;
use App\Server\RoomManager;
use App\Logger\LoggerInterface;
use Ratchet\ConnectionInterface;
use App\Server\ConnectionManager;
use Ratchet\MessageComponentInterface;

/**
 * Entry point for WebSocket server
 */
class WebSocketServer implements MessageComponentInterface
{
    private ConnectionManager $connectionManager;
    private RoomManager $roomManager;
    private MessageHandler $messageHandler;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->connectionManager = new ConnectionManager();
        $this->roomManager = new RoomManager($logger);
        $this->messageHandler = new MessageHandler($this->roomManager, $logger);
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->connectionManager->add($conn);
        $this->logger->info("New connection opened", ['resourceId' => $conn->resourceId]);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $this->messageHandler->handle($from, $msg);
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->logger->info("Connection closed", ['resourceId' => $conn->resourceId]);
        $this->roomManager->removeConnectionFromAllRooms($conn->resourceId);
        $this->connectionManager->remove($conn->resourceId);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $this->logger->error("Connection error", ['error' => $e->getMessage()]);
        $conn->close();
    }
}
