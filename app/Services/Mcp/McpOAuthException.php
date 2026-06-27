<?php

namespace App\Services\Mcp;

use Exception;

class McpOAuthException extends Exception
{
    public function __construct(
        public readonly string $error,
        string $message,
    ) {
        parent::__construct($message);
    }
}
