<?php

namespace App\Exceptions;

use RuntimeException;

class DodoVroumApiException extends RuntimeException
{
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function authenticationFailed(string $message = 'Échec d\'authentification API'): self
    {
        return new self($message, 401);
    }

    public static function requestFailed(string $message = 'Erreur lors de la requête API', array $context = []): self
    {
        return new self($message, 500, null, $context);
    }

    public static function notFound(string $resource = 'Ressource'): self
    {
        return new self("{$resource} non trouvée", 404);
    }

    public static function forbidden(string $message = 'Cette action n\'est pas autorisée', array $context = []): self
    {
        return new self($message, 403, null, $context);
    }
}

