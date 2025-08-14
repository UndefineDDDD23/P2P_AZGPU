<?php

namespace App\Server;

use Ratchet\ConnectionInterface;
use App\Logger\LoggerInterface;
use App\Support\Config;

/**
 * Handles all room-related operations: create, join, leave, signaling
 */
class RoomManager
{
    private array $rooms = []; // roomId => ['secretKey' => ..., 'connections' => [resourceId => ConnectionInterface]]
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function createRoom(ConnectionInterface $admin, array $data): void
    {
        $adminPassword = $data['adminPassword'] ?? '';
        $validAdminPassword = 'root';

        if ($adminPassword !== $validAdminPassword) {
            $this->logger->error("Attempt to create room with invalid admin password", ['resourceId' => $admin->resourceId]);
            $admin->send(json_encode(['error' => 'Invalid admin password']));
            return;
        }

        $roomId = bin2hex(random_bytes(4));
        $secretKey = bin2hex(random_bytes(16));

        $this->rooms[$roomId] = [
            'secretKey' => $secretKey,
            'connections' => [$admin->resourceId => $admin]
        ];

        $this->logger->info("Room created", [
            'roomId' => $roomId,
            'secretKey' => $secretKey,
            'resourceId' => $admin->resourceId
        ]);

        $roomUrl = Config::get('APP_URL') . "?roomId={$roomId}&key={$secretKey}";

        $admin->send(json_encode([
            'type' => 'room-created',
            'roomId' => $roomId,
            'secretKey' => $secretKey,
            'url' => $roomUrl
        ]));
    }

    public function joinRoom(ConnectionInterface $client, ?string $roomId, ?string $key): void
    {
        if (!$roomId || !$key) {
            $this->logger->error("roomId or key missing", ['resourceId' => $client->resourceId]);
            $client->send(json_encode(['error' => 'roomId and key are required']));
            return;
        }

        if (!isset($this->rooms[$roomId])) {
            $client->send(json_encode(['error' => 'Room does not exist']));
            return;
        }

        if ($this->rooms[$roomId]['secretKey'] !== $key) {
            $client->send(json_encode(['error' => 'Invalid room key']));
            return;
        }

        $this->rooms[$roomId]['connections'][$client->resourceId] = $client;

        foreach ($this->rooms[$roomId]['connections'] as $connection) {
            if ($connection !== $client) {
                $connection->send(json_encode([
                    'type' => 'new-peer',
                    'peerId' => $client->resourceId
                ]));
            }
        }
    }

    public function sendSignal(ConnectionInterface $from, ?string $roomId, ?string $targetId, ?array $signalData): void
    {
        if (!$roomId || !$targetId || !$signalData) {
            $this->logger->error("Invalid signaling data");
            return;
        }

        if (!isset($this->rooms[$roomId]['connections'][$targetId])) {
            $this->logger->warning("Target user not found", ['targetId' => $targetId]);
            return;
        }

        $this->rooms[$roomId]['connections'][$targetId]->send(json_encode([
            'type' => 'signal',
            'peerId' => $from->resourceId,
            'signalData' => $signalData
        ]));
    }

    public function leaveRoom(ConnectionInterface $client, ?string $roomId): void
    {
        if (!isset($this->rooms[$roomId]['connections'][$client->resourceId])) {
            return;
        }

        unset($this->rooms[$roomId]['connections'][$client->resourceId]);

        foreach ($this->rooms[$roomId]['connections'] as $connection) {
            $connection->send(json_encode([
                'type' => 'peer-left',
                'peerId' => $client->resourceId
            ]));
        }
    }

    public function removeConnectionFromAllRooms(int $resourceId): void
    {
        foreach ($this->rooms as $roomId => &$room) {
            if (isset($room['connections'][$resourceId])) {
                unset($room['connections'][$resourceId]);
            }
        }
    }
}
