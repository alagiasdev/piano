<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Incapsula la richiesta HTTP corrente.
 *
 * Il "base path" e la sottocartella in cui vive public/: vuoto su cPanel
 * (document root = public/), "/crm_social1.0/public" in locale su XAMPP.
 * Viene dedotto da SCRIPT_NAME, cosi non c'e nulla da configurare.
 */
final class Request
{
    /**
     * Copia statica del base path, usata dall'helper globale url().
     * Resta vuota negli script CLI, dove non esiste una richiesta.
     */
    public static string $base = '';

    public readonly string $method;
    public readonly string $path;
    public readonly string $basePath;

    /** @var array<string,mixed> */
    public readonly array $query;

    /** @var array<string,mixed> */
    public readonly array $post;

    public function __construct()
    {
        $this->basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        self::$base = $this->basePath;

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);
        if ($this->basePath !== '' && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }
        $path = '/' . trim($uri, '/');

        $this->path   = $path;
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->query  = $_GET;
        $this->post   = $_POST;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function has(string $key): bool
    {
        return isset($this->post[$key]) || isset($this->query[$key]);
    }

    /** Checkbox e select multipli: sempre un array di stringhe. */
    public function inputArray(string $key): array
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? [];

        return is_array($value) ? array_values(array_filter(array_map('strval', $value), 'strlen')) : [];
    }

    /** Corpo JSON delle chiamate fetch dell'editor. */
    public function json(): array
    {
        static $decoded = null;
        if ($decoded === null) {
            $raw = file_get_contents('php://input') ?: '';
            $data = json_decode($raw, true);
            $decoded = is_array($data) ? $data : [];
        }

        return $decoded;
    }

    public function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $type   = $_SERVER['CONTENT_TYPE'] ?? '';

        return str_contains($accept, 'application/json') || str_contains($type, 'application/json');
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
