<?php

namespace App\Server;

use Ratchet\ConnectionInterface;

/**
 * Stores and manages all active WebSocket connections
 */
class ConnectionManager
{
    private array $connections = []; // resourceId => ConnectionInterface

    public function add(ConnectionInterface $conn): void
    {
        $this->connections[$conn->resourceId] = $conn;
    }

    public function remove(int $resourceId): void
    {
        unset($this->connections[$resourceId]);
    }

    public function get(int $resourceId): ?ConnectionInterface
    {
        return $this->connections[$resourceId] ?? null;
    }
}
