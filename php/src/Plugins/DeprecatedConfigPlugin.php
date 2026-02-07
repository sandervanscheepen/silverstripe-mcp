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
 * Detects usage of the deprecated Config::inst()->get() pattern.
 *
 * The old pattern Config::inst()->get('ClassName', 'property') should be
 * replaced with the modern ClassName::config()->get('property') syntax.
 */
class DeprecatedConfigPlugin implements ValidatorPluginInterface
{
    private const DOCS_URL = 'https://docs.silverstripe.org/en/6/developer_guides/configuration/configuration/';

    public function getName(): string
    {
        return 'deprecated-config';
    }

    public function getDescription(): string
    {
        return 'Detects deprecated Config::inst()->get() usage';
    }

    public function getTargetVersions(): array
    {
        return ['5.*', '6.*'];
    }

    public function configure(array $options): void
    {
        // No configuration needed
    }

    public function getVisitor(AnalysisContext $context): NodeVisitor
    {
        return new class($context) extends NodeVisitorAbstract {
            public function __construct(private AnalysisContext $context) {}

            public function enterNode(Node $node): ?int
            {
                // Detect Config::inst()->get('ClassName', 'property')
                // AST structure: MethodCall[get] -> var=StaticCall[inst, class=Config]
                if ($node instanceof Node\Expr\MethodCall
                    && $node->name instanceof Node\Identifier
                    && $node->name->name === 'get'
                    && $node->var instanceof Node\Expr\StaticCall
                    && $node->var->name instanceof Node\Identifier
                    && $node->var->name->name === 'inst'
                    && $node->var->class instanceof Node\Name
                ) {
                    $className = $node->var->class->toString();

                    // Check for Config class (with or without full namespace)
                    if ($className === 'Config' || $className === 'SilverStripe\\Core\\Config\\Config') {
                        // Try to extract the class name for a better suggestion
                        $suggestion = 'ClassName::config()->get(\'property\')';
                        if (count($node->args) >= 2) {
                            $classArg = $node->args[0]->value ?? null;
                            $propArg = $node->args[1]->value ?? null;
                            if ($classArg instanceof Node\Scalar\String_
                                && $propArg instanceof Node\Scalar\String_) {
                                // Extract just the class name from FQCN
                                $parts = explode('\\', $classArg->value);
                                $shortClass = end($parts);
                                $suggestion = "{$shortClass}::config()->get('{$propArg->value}')";
                            }
                        }

                        $this->context->addIssue(new Issue(
                            type: 'deprecated_config_api',
                            message: 'Config::inst()->get() is deprecated. Use static config access instead.',
                            line: $node->getStartLine(),
                            code: 'Config::inst()->get(...)',
                            suggestion: $suggestion,
                            docsUrl: 'https://docs.silverstripe.org/en/6/developer_guides/configuration/configuration/',
                        ));
                    }
                }

                return null;
            }
        };
    }
}
