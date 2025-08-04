<?php

namespace App\Logger;

interface LoggerInterface {
    /**
     * Записывает сообщение в лог с заданным уровнем.
     *
     * @param string $level Уровень логирования (info, warning, error, etc.).
     * @param string $message Сообщение для записи в лог.
     * @param array $context Дополнительный контекст (опционально).
     */
    public function log(string $level, string $message, array $context = []): void;

    /**
     * Удобный метод для записи информационных сообщений.
     *
     * @param string $message Сообщение.
     * @param array $context Дополнительный контекст.
     */
    public function info(string $message, array $context = []): void;

    /**
     * Удобный метод для записи предупреждений.
     *
     * @param string $message Сообщение.
     * @param array $context Дополнительный контекст.
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Удобный метод для записи ошибок.
     *
     * @param string $message Сообщение.
     * @param array $context Дополнительный контекст.
     */
    public function error(string $message, array $context = []): void;
}