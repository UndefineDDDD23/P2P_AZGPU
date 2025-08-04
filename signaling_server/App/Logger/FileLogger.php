<?php

namespace App\Logger;

use App\Logger\LoggerInterface;

class FileLogger implements LoggerInterface {
    private string $logFile;

    /**
     * Конструктор.
     *
     * @param string $logFile Путь к файлу логов.
     */
    public function __construct(string $logFile) {
        $this->logFile = $logFile;

        // Создаем файл, если его нет
        if (!file_exists($logFile)) {
            file_put_contents($logFile, '');
        }
    }

    public function log(string $level, string $message, array $context = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        $logMessage = sprintf("[%s] %s: %s %s\n", $timestamp, strtoupper($level), $message, $contextString);
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    public function info(string $message, array $context = []): void {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->log('error', $message, $context);
    }
}