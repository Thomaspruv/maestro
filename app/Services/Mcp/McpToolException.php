<?php

namespace App\Services\Mcp;

use RuntimeException;

class McpToolException extends RuntimeException
{
    public static function missing(string $field): self
    {
        return new self("Paramètre requis manquant : {$field}");
    }

    public static function notFound(string $resource): self
    {
        return new self("Ressource introuvable : {$resource}");
    }

    public static function invalid(string $message): self
    {
        return new self($message);
    }
}
