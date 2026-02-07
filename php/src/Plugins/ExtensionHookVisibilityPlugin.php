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
 * Detects extension hook methods that should be protected in Silverstripe 6.
 *
 * SS6 changed many extension hook methods from public to protected.
 * This plugin detects public hook methods in Extension classes.
 */
class ExtensionHookVisibilityPlugin implements ValidatorPluginInterface
{
    private const DOCS_URL = 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/';

    /** @var string[] */
    private array $hookPrefixes = [
        'onBefore',
        'onAfter',
        'update',
        'augment',
    ];

    public function getName(): string
    {
        return 'extension-hook-visibility';
    }

    public function getDescription(): string
    {
        return 'Detects extension hook methods that should be protected in SS6';
    }

    public function getTargetVersions(): array
    {
        return ['6.*'];
    }

    public function configure(array $options): void
    {
        if (isset($options['additionalPrefixes']) && is_array($options['additionalPrefixes'])) {
            $this->hookPrefixes = array_merge($this->hookPrefixes, $options['additionalPrefixes']);
        }
    }

    public function getVisitor(AnalysisContext $context): NodeVisitor
    {
        return new class($context, $this->hookPrefixes) extends NodeVisitorAbstract {
            private bool $inExtensionClass = false;

            /**
             * @param string[] $hookPrefixes
             */
            public function __construct(
                private AnalysisContext $context,
                private array $hookPrefixes,
            ) {}

            public function enterNode(Node $node): ?int
            {
                // Track if we're in an Extension class
                if ($node instanceof Node\Stmt\Class_) {
                    if ($node->extends !== null) {
                        $extends = $node->extends->toString();
                        $this->inExtensionClass = str_contains($extends, 'Extension')
                            || str_contains($extends, 'DataExtension')
                            || str_contains($extends, 'SiteTreeExtension');
                    }
                }

                // Check method visibility
                if ($node instanceof Node\Stmt\ClassMethod && $this->inExtensionClass) {
                    $methodName = $node->name->name;

                    foreach ($this->hookPrefixes as $prefix) {
                        if (str_starts_with($methodName, $prefix)) {
                            if ($node->isPublic()) {
                                $this->context->addIssue(new Issue(
                                    type: 'hook_visibility',
                                    message: "Extension hook method '{$methodName}' should be protected in Silverstripe 6",
                                    line: $node->getStartLine(),
                                    code: "public function {$methodName}",
                                    suggestion: "protected function {$methodName}",
                                    docsUrl: 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/',
                                ));
                            }
                            break;
                        }
                    }
                }

                return null;
            }

            public function leaveNode(Node $node): ?int
            {
                if ($node instanceof Node\Stmt\Class_) {
                    $this->inExtensionClass = false;
                }
                return null;
            }
        };
    }
}
