<?php

declare(strict_types=1);

namespace SilverstripeMCP;

/**
 * Represents a single issue found during code analysis.
 */
class Issue
{
    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly int $line,
        public readonly ?int $column = null,
        public readonly ?string $code = null,
        public readonly ?string $suggestion = null,
        public readonly ?string $docsUrl = null,
    ) {}

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'line' => $this->line,
            'column' => $this->column,
            'code' => $this->code,
            'suggestion' => $this->suggestion,
            'docsUrl' => $this->docsUrl,
        ];
    }
}
