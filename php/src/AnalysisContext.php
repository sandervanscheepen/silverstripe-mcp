<?php

declare(strict_types=1);

namespace SilverstripeMCP;

/**
 * Shared context passed to all plugins during analysis.
 */
class AnalysisContext
{
    /** @var Issue[] */
    private array $issues = [];

    /** @var Suggestion[] */
    private array $suggestions = [];

    public function __construct(
        public readonly string $code,
        public readonly string $filename,
        public readonly string $targetVersion,
        public readonly ?string $sourceVersion = null,
    ) {}

    /**
     * Add an issue found during analysis.
     */
    public function addIssue(Issue $issue): void
    {
        $this->issues[] = $issue;
    }

    /**
     * Add a suggestion for improvement.
     */
    public function addSuggestion(Suggestion $suggestion): void
    {
        $this->suggestions[] = $suggestion;
    }

    /**
     * Get all collected issues.
     *
     * @return Issue[]
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * Get all collected suggestions.
     *
     * @return Suggestion[]
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    /**
     * Get the analysis result with all issues and suggestions.
     */
    public function getResult(): AnalysisResult
    {
        return AnalysisResult::create($this->issues, $this->suggestions);
    }
}
