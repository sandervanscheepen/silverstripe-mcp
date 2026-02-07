<?php

declare(strict_types=1);

namespace SilverstripeMCP\Contracts;

use PhpParser\NodeVisitor;
use SilverstripeMCP\AnalysisContext;

/**
 * Interface for validator plugins.
 *
 * Each plugin implements a specific type of validation (e.g., namespace checks,
 * BuildTask patterns). Plugins return a NodeVisitor that traverses the AST and
 * reports issues via the AnalysisContext.
 */
interface ValidatorPluginInterface
{
    /**
     * Unique identifier for this plugin.
     *
     * Used for configuration and logging.
     * Example: 'namespace-validator', 'buildtask-validator'
     */
    public function getName(): string;

    /**
     * Human-readable description shown in logs and documentation.
     */
    public function getDescription(): string;

    /**
     * Which Silverstripe versions this plugin applies to.
     *
     * Examples:
     * - ['6.0'] - Only Silverstripe 6.0
     * - ['6.*'] - Any Silverstripe 6.x
     * - ['*'] - All versions
     *
     * @return string[]
     */
    public function getTargetVersions(): array;

    /**
     * Return the NodeVisitor that performs the actual analysis.
     *
     * The visitor should call $context->addIssue() when problems are found.
     * Use $context->addSuggestion() for non-blocking improvements.
     */
    public function getVisitor(AnalysisContext $context): NodeVisitor;

    /**
     * Configure plugin with user-provided options.
     *
     * Called before getVisitor() to allow customization.
     * Options come from silverstripe-mcp.json config file.
     *
     * @param array<string, mixed> $options Plugin-specific options
     */
    public function configure(array $options): void;
}
