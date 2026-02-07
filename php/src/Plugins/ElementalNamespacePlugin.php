<?php

declare(strict_types=1);

namespace SilverstripeMCP\Plugins;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\Contracts\ValidatorPluginInterface;
use SilverstripeMCP\Issue;

/**
 * Detects outdated Elemental module namespaces.
 *
 * For projects using dnadesign/silverstripe-elemental, this plugin detects
 * namespace changes between SS5 and SS6 versions of the module.
 *
 * Detects:
 * - TopPage classes that moved to Extensions namespace
 * - ElementSiteTreeFilterSearch -> ElementalSiteTreeSearchContext
 * - Removed classes (GraphQL, ElementalLeftAndMainExtension, etc.)
 *
 * Sources:
 * - https://github.com/dnadesign/silverstripe-elemental/releases/tag/6.0.0
 */
class ElementalNamespacePlugin implements ValidatorPluginInterface
{
    private const DOCS_URL = 'https://github.com/dnadesign/silverstripe-elemental/releases/tag/6.0.0';

    /** @var array<string, string|null> Maps old class -> new class (null = removed) */
    private array $mappings;

    public function __construct()
    {
        $this->mappings = require __DIR__ . '/../Config/elemental-mappings.php';
    }

    public function getName(): string
    {
        return 'elemental-namespace';
    }

    public function getDescription(): string
    {
        return 'Detects outdated Elemental module namespaces';
    }

    public function getTargetVersions(): array
    {
        return ['6.*'];
    }

    public function configure(array $options): void
    {
        // Allow users to add custom mappings via config
        if (isset($options['additionalMappings']) && is_array($options['additionalMappings'])) {
            $this->mappings = array_merge($this->mappings, $options['additionalMappings']);
        }

        // Legacy support for 'mappings' key
        if (isset($options['mappings']) && is_array($options['mappings'])) {
            $this->mappings = array_merge($this->mappings, $options['mappings']);
        }
    }

    public function getVisitor(AnalysisContext $context): NodeVisitor
    {
        $docsUrl = self::DOCS_URL;

        return new class($context, $this->mappings, $docsUrl) extends NodeVisitorAbstract {
            /**
             * @param array<string, string|null> $mappings
             */
            public function __construct(
                private AnalysisContext $context,
                private array $mappings,
                private string $docsUrl,
            ) {}

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Node\Stmt\Use_) {
                    foreach ($node->uses as $use) {
                        $fqcn = $use->name->toString();

                        // Check if this class is in our mappings
                        if (!array_key_exists($fqcn, $this->mappings)) {
                            continue;
                        }

                        $newClass = $this->mappings[$fqcn];

                        // null means the class was removed without replacement
                        if ($newClass === null) {
                            $this->context->addIssue(new Issue(
                                type: 'removed_elemental_class',
                                message: "'{$fqcn}' has been removed in Elemental for SS6 without a direct replacement",
                                line: $node->getStartLine(),
                                code: "use {$fqcn};",
                                suggestion: "// Remove this import - class has been removed",
                                docsUrl: $this->docsUrl,
                            ));
                        } else {
                            // Class was renamed/moved
                            $this->context->addIssue(new Issue(
                                type: 'deprecated_elemental_import',
                                message: "'{$fqcn}' has moved to '{$newClass}' in Elemental for SS6",
                                line: $node->getStartLine(),
                                code: "use {$fqcn};",
                                suggestion: "use {$newClass};",
                                docsUrl: $this->docsUrl,
                            ));
                        }
                    }
                }

                return null;
            }
        };
    }
}
