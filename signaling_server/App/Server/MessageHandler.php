<?php

namespace App\Server;

use Ratchet\ConnectionInterface;
use App\Logger\LoggerInterface;

/**
 * Routes incoming WebSocket messages to appropriate room actions
 */
class MessageHandler
{
    private RoomManager $roomManager;
    private LoggerInterface $logger;

    public function __construct(RoomManager $roomManager, LoggerInterface $logger)
    {
        $this->roomManager = $roomManager;
        $this->logger = $logger;
    }

    public function handle(ConnectionInterface $from, string $msg): void
    {
        $data = json_decode($msg, true);

        if (!isset($data['type'])) {
            $this->logger->error("Message type is missing");
            return;
        }

        $roomId = $data['roomId'] ?? null;

        switch ($data['type']) {
            case 'create-room':
                $this->roomManager->createRoom($from, $data);
                break;

            case 'join-room':
                $this->roomManager->joinRoom($from, $roomId, $data['key'] ?? null);
                break;

            case 'signal':
                $this->roomManager->sendSignal($from, $roomId, $data['targetId'] ?? null, $data['signalData'] ?? null);
                break;

            case 'leave-room':
                $this->roomManager->leaveRoom($from, $roomId);
                break;

            default:
                $this->logger->warning("Unknown message type", ['type' => $data['type']]);
                break;
        }
    }
}
