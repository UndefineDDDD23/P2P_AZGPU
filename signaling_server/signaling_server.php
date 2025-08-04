<?php
require __DIR__ . '/vendor/autoload.php';

use App\Logger\LoggerInterface;
use App\Support\Config;
use App\Logger\FileLogger;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class WebSocketServer implements MessageComponentInterface {
    // Структура комнат: roomId => [ 'secretKey' => ..., 'connections' => [ resourceId => ConnectionInterface, ... ] ]
    private $rooms = [];
    private $connections = []; // Все подключения
    private $logger;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->connections[$conn->resourceId] = $conn;
        $this->logger->info("Новое подключение", ['resourceId' => $conn->resourceId]);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        if (!isset($data['type'])) {
            $this->logger->error("Тип сообщения не указан", ['message' => $msg]);
            return;
        }

        $roomId = $data['roomId'] ?? null;

        switch ($data['type']) {
            case 'create-room':
                $this->handleCreateRoom($from, $data);
                break;

            case 'join-room':
                // Передаём также секретный ключ (key) из данных
                $this->handleJoinRoom($from, $roomId, $data['key'] ?? null);
                break;

            case 'signal':
                $this->handleSignal($from, $roomId, $data['targetId'] ?? null, $data['signalData'] ?? null);
                break;

            case 'leave-room':
                $this->handleLeaveRoom($from, $roomId);
                break;

            default:
                $this->logger->warning("Неизвестный тип сообщения", ['message' => $msg]);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->logger->info("Клиент отключился", ['resourceId' => $conn->resourceId]);
        $this->removeConnectionFromAllRooms($conn->resourceId);
        unset($this->connections[$conn->resourceId]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->logger->error("Ошибка соединения", ['error' => $e->getMessage()]);
        $conn->close();
    }

    // Создание комнаты (только для администратора)
    private function handleCreateRoom(ConnectionInterface $from, $data) {
        // Проверка административного пароля (в примере пароль: admin123)
        $adminPassword = $data['adminPassword'] ?? '';
        $validAdminPassword = 'root';
        if ($adminPassword !== $validAdminPassword) {
            $this->logger->error("Попытка создания комнаты с неверным паролем", ['resourceId' => $from->resourceId]);
            $from->send(json_encode(['error' => 'Неверный пароль администратора']));
            return;
        }
        // Генерация уникального roomId и секретного ключа
        $roomId = bin2hex(random_bytes(4)); // например, 8 символов
        $secretKey = bin2hex(random_bytes(16)); // например, 32 символа
        $this->rooms[$roomId] = [
            'secretKey' => $secretKey,
            'connections' => [$from->resourceId => $from]
        ];
        $this->logger->info("Текущее состояние комнат", ['rooms' => $this->rooms]);
        $this->logger->info("Комната создана администратором", [
            'roomId' => $roomId,
            'secretKey' => $secretKey,
            'resourceId' => $from->resourceId
        ]);
        // Формируем URL для подключения (замените yourdomain на домен вашего сервера)
        $roomUrl = Config::get('APP_URL') . "?roomId=".$roomId."&key=".$secretKey;
        $from->send(json_encode([
            'type' => 'room-created',
            'roomId' => $roomId,
            'secretKey' => $secretKey,
            'url' => $roomUrl
        ]));
    }

    // Присоединение к комнате с проверкой секретного ключа
    private function handleJoinRoom(ConnectionInterface $from, $roomId, $key) {
        if (!$roomId || !$key) {
            $this->logger->error("roomId или ключ отсутствует", ['resourceId' => $from->resourceId]);
            $from->send(json_encode(['error' => 'roomId и ключ обязательны']));
            return;
        }
        if (!isset($this->rooms[$roomId])) {
            $this->logger->error("Комната не существует", ['roomId' => $roomId]);
            $from->send(json_encode(['error' => 'Комната не существует']));
            return;
        }
        if ($this->rooms[$roomId]['secretKey'] !== $key) {
            $this->logger->error("Неверный ключ для комнаты", ['roomId' => $roomId, 'providedKey' => $key]);
            $from->send(json_encode(['error' => 'Неверный ключ для комнаты']));
            return;
        }
        $this->rooms[$roomId]['connections'][$from->resourceId] = $from;
        $this->logger->info("Пользователь присоединился к комнате", ['roomId' => $roomId, 'resourceId' => $from->resourceId]);        
        $this->logger->info("Текущее состояние комнат", ['rooms' => $this->rooms]);
        // Уведомляем остальных участников
        foreach ($this->rooms[$roomId]['connections'] as $connection) {
            if ($connection !== $from) {
                $connection->send(json_encode([
                    'type' => 'new-peer',
                    'peerId' => $from->resourceId
                ]));
            }
        }
    }

    // Передача сигналов между участниками
    private function handleSignal(ConnectionInterface $from, $roomId, $targetId, $signalData) {
        if (!$roomId || !$targetId || !$signalData) {
            $this->logger->error("Некорректные данные сигналинга", [
                'roomId' => $roomId,
                'targetId' => $targetId,
                'signalData' => $signalData
            ]);
            return;
        }
        if (!isset($this->rooms[$roomId]['connections'][$targetId])) {
            $this->logger->warning("Целевой пользователь не найден", ['targetId' => $targetId, 'roomId' => $roomId]);
            return;
        }
        $this->rooms[$roomId]['connections'][$targetId]->send(json_encode([
            'type' => 'signal',
            'peerId' => $from->resourceId,
            'signalData' => $signalData
        ]));
        $this->logger->info("Сигнал отправлен", [
            'from' => $from->resourceId,
            'to' => $targetId,
            'roomId' => $roomId,
            'signalType' => $signalData['type'] ?? 'candidate'
        ]);
    }

    // Отключение от комнаты
    private function handleLeaveRoom(ConnectionInterface $from, $roomId) {
        if (!isset($this->rooms[$roomId]['connections'][$from->resourceId])) {
            $this->logger->warning("Пользователь пытается покинуть несуществующую комнату", [
                'resourceId' => $from->resourceId,
                'roomId' => $roomId
            ]);
            return;
        }
        $this->logger->info("Текущее состояние комнат", ['rooms' => $this->rooms]);
        unset($this->rooms[$roomId]['connections'][$from->resourceId]);
        $this->logger->info("Пользователь покинул комнату", ['roomId' => $roomId, 'resourceId' => $from->resourceId]);
        foreach ($this->rooms[$roomId]['connections'] as $connection) {
            $connection->send(json_encode([
                'type' => 'peer-left',
                'peerId' => $from->resourceId
            ]));
        }
        if (empty($this->rooms[$roomId]['connections'])) {
            //unset($this->rooms[$roomId]);
        }
    }

    // Удаление подключения из всех комнат
    private function removeConnectionFromAllRooms($resourceId) {        
        $this->logger->info("Текущее состояние комнат", ['rooms' => $this->rooms]);
        foreach ($this->rooms as $roomId => &$room) {
            if (isset($room['connections'][$resourceId])) {
                unset($room['connections'][$resourceId]);
                $this->logger->info("Пользователь удален из комнаты", ['roomId' => $roomId, 'resourceId' => $resourceId]);
                foreach ($room['connections'] as $connection) {
                    $connection->send(json_encode([
                        'type' => 'peer-left',
                        'peerId' => $resourceId
                    ]));
                }
                if (empty($room['connections'])) {
                    // unset($this->rooms[$roomId]);
                }
            }
        }
    }
}

define('PROJECT_ROOT', __DIR__);

// Инициализация логгера
$logger = new FileLogger(PROJECT_ROOT.'/log.txt');

$server = Ratchet\Server\IoServer::factory(
    new Ratchet\Http\HttpServer(
        new Ratchet\WebSocket\WsServer(
            new WebSocketServer($logger)
        )
    ),
    8080
);

$server->run();
