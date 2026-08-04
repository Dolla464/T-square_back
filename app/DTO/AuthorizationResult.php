<?php

namespace App\DTO;

final readonly class AuthorizationResult
{
    private function __construct(
        private bool $allowed,
        private ?string $message,
        private int $statusCode,
    ) {}

    public static function allowed(): self
    {
        return new self(true, null, 200);
    }

    public static function notFound(string $message = 'Resource not found.'): self
    {
        return new self(false, $message, 404);
    }

    public static function forbidden(string $message): self
    {
        return new self(false, $message, 403);
    }

    public static function expired(string $message = 'Exam window has closed.'): self
    {
        return new self(false, $message, 422);
    }

    public static function unprocessable(string $message): self
    {
        return new self(false, $message, 422);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getCode(): string
    {
        return match ($this->statusCode) {
            404 => 'NOT_FOUND',
            403 => 'FORBIDDEN',
            422 => 'UNPROCESSABLE',
            default => 'ERROR',
        };
    }
}
