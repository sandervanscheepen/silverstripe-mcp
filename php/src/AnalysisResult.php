<?php

declare(strict_types=1);

namespace SilverstripeMCP;

/**
 * Aggregates issues and suggestions from all plugins.
 */
class AnalysisResult
{
    /**
     * @param Issue[] $issues
     * @param Suggestion[] $suggestions
     * @param bool $rerun Whether the LLM should fix issues and call the validator again
     */
    public function __construct(
        public readonly array $issues = [],
        public readonly array $suggestions = [],
        public readonly bool $rerun = false,
    ) {}

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'issues' => array_map(fn(Issue $issue) => $issue->toArray(), $this->issues),
            'suggestions' => array_map(fn(Suggestion $suggestion) => $suggestion->toArray(), $this->suggestions),
            'rerun' => $this->rerun,
        ];
    }

    /**
     * Create result from issues and suggestions arrays.
     *
     * @param Issue[] $issues
     * @param Suggestion[] $suggestions
     */
    public static function create(array $issues, array $suggestions): self
    {
        // Suggest rerun if there are fixable issues
        $rerun = count($issues) > 0;
        return new self($issues, $suggestions, $rerun);
    }

    /**
     * Create an error result for parse failures.
     */
    public static function parseError(string $message, int $line = 1): self
    {
        $issue = new Issue(
            type: 'parse_error',
            message: $message,
            line: $line,
        );
        return new self([$issue], [], false);
    }
}
