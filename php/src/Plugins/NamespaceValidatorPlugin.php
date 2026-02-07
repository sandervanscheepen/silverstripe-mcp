<?php

declare(strict_types=1);

namespace SilverstripeMCP\Plugins;

use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\Contracts\ValidatorPluginInterface;
use SilverstripeMCP\Issue;

/**
 * Validates namespace imports for Silverstripe 6 compatibility.
 *
 * Detects use statements that reference old Silverstripe 5 class paths
 * and suggests the correct Silverstripe 6 equivalents.
 */
class NamespaceValidatorPlugin implements ValidatorPluginInterface
{
    private const DOCS_URL = 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/#renamed-classes';

    /** @var array<string, string> Old namespace => New namespace */
    private array $mappings = [];

    /** @var array<string, string> Additional user-defined mappings */
    private array $additionalMappings = [];

    public function __construct()
    {
        $this->loadDefaultMappings();
    }

    /**
     * Load default namespace mappings from config file.
     */
    private function loadDefaultMappings(): void
    {
        $mappingsFile = __DIR__ . '/../Config/namespace-mappings.php';
        if (file_exists($mappingsFile)) {
            $this->mappings = require $mappingsFile;
        }
    }

    public function getName(): string
    {
        return 'namespace-validator';
    }

    public function getDescription(): string
    {
        return 'Validates namespace imports for Silverstripe 6 compatibility';
    }

    public function getTargetVersions(): array
    {
        return ['6.*'];
    }

    public function configure(array $options): void
    {
        // Merge additional mappings from config
        if (isset($options['additionalMappings']) && is_array($options['additionalMappings'])) {
            $this->additionalMappings = $options['additionalMappings'];
        }
    }

    public function getVisitor(AnalysisContext $context): NodeVisitor
    {
        return new class($context, $this->getMappings()) extends NodeVisitorAbstract {
            /** @var array<string, string> */
            private array $mappings;

            public function __construct(
                private AnalysisContext $context,
                array $mappings,
            ) {
                $this->mappings = $mappings;
            }

            public function enterNode(Node $node): ?int
            {
                // Handle regular use statements
                if ($node instanceof Use_) {
                    foreach ($node->uses as $use) {
                        $this->checkUse($use->name->toString(), $node->getLine());
                    }
                }

                // Handle grouped use statements (use SilverStripe\ORM\{ArrayList, DataList};)
                if ($node instanceof GroupUse) {
                    $prefix = $node->prefix->toString();
                    foreach ($node->uses as $use) {
                        $fullName = $prefix . '\\' . $use->name->toString();
                        $this->checkUse($fullName, $node->getLine());
                    }
                }

                return null;
            }

            private function checkUse(string $className, int $line): void
            {
                if (isset($this->mappings[$className])) {
                    $newClassName = $this->mappings[$className];

                    $this->context->addIssue(new Issue(
                        type: 'deprecated_import',
                        message: "'{$className}' has moved to '{$newClassName}' in Silverstripe 6",
                        line: $line,
                        code: "use {$className};",
                        suggestion: "use {$newClassName};",
                        docsUrl: 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/#renamed-classes',
                    ));
                }
            }
        };
    }

    /**
     * Get all mappings (default + additional).
     *
     * @return array<string, string>
     */
    private function getMappings(): array
    {
        return array_merge($this->mappings, $this->additionalMappings);
    }
}
