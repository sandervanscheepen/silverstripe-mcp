<?php

declare(strict_types=1);

namespace SilverstripeMCP\Plugins;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\Contracts\ValidatorPluginInterface;
use SilverstripeMCP\Issue;

/**
 * Detects calls to methods that were removed in Silverstripe 6.
 *
 * This is a generic plugin that reads method definitions from a config file
 * and detects both static and instance method calls.
 */
class RemovedMethodPlugin implements ValidatorPluginInterface
{
    /** @var array<string, array{message: string, suggestion: string, docsUrl: string, isStatic?: bool}> */
    private array $removedMethods = [];

    /** @var array<string, array{message: string, suggestion: string, docsUrl: string, isStatic?: bool}> */
    private array $additionalMethods = [];

    public function __construct()
    {
        $this->loadDefaultMethods();
    }

    /**
     * Load default removed methods from config file.
     */
    private function loadDefaultMethods(): void
    {
        $configFile = __DIR__ . '/../Config/removed-methods.php';
        if (file_exists($configFile)) {
            $this->removedMethods = require $configFile;
        }
    }

    public function getName(): string
    {
        return 'removed-method-validator';
    }

    public function getDescription(): string
    {
        return 'Detects calls to methods that were removed in Silverstripe 6';
    }

    public function getTargetVersions(): array
    {
        return ['6.*'];
    }

    public function configure(array $options): void
    {
        // Merge additional methods from config
        if (isset($options['additionalMethods']) && is_array($options['additionalMethods'])) {
            $this->additionalMethods = $options['additionalMethods'];
        }
    }

    public function getVisitor(AnalysisContext $context): NodeVisitor
    {
        $methods = $this->getMethods();

        return new class($context, $methods) extends NodeVisitorAbstract {
            private ?Class_ $currentClass = null;

            /** @var array<string, array{message: string, suggestion: string, docsUrl: string, isStatic?: bool}> */
            private array $removedMethods;

            /** @var array<string, string[]> Method name => list of class contexts it applies to */
            private array $methodToClasses = [];

            /** @var array<string, array{message: string, suggestion: string, docsUrl: string}> Static method key => config */
            private array $staticMethods = [];

            public function __construct(
                private AnalysisContext $context,
                array $methods,
            ) {
                $this->removedMethods = $methods;
                $this->buildLookupMaps();
            }

            /**
             * Build lookup maps for efficient checking.
             */
            private function buildLookupMaps(): void
            {
                foreach ($this->removedMethods as $key => $config) {
                    [$className, $methodName] = explode('::', $key);

                    if ($config['isStatic'] ?? false) {
                        // Static methods: key by full path for static call detection
                        $this->staticMethods[$key] = $config;
                    } else {
                        // Instance methods: group by method name for $this-> detection
                        if (!isset($this->methodToClasses[$methodName])) {
                            $this->methodToClasses[$methodName] = [];
                        }
                        $this->methodToClasses[$methodName][] = $className;
                    }
                }
            }

            public function enterNode(Node $node): ?int
            {
                // Track current class for context-aware detection
                if ($node instanceof Class_) {
                    $this->currentClass = $node;
                    return null;
                }

                // Check static method calls (e.g., Controller::has_curr())
                if ($node instanceof StaticCall) {
                    $this->checkStaticCall($node);
                }

                // Check instance method calls (e.g., $this->getCMSValidator())
                if ($node instanceof MethodCall) {
                    $this->checkMethodCall($node);
                }

                return null;
            }

            public function leaveNode(Node $node): ?int
            {
                if ($node instanceof Class_ && $node === $this->currentClass) {
                    $this->currentClass = null;
                }

                return null;
            }

            private function checkStaticCall(StaticCall $node): void
            {
                // Get class name
                if (!$node->class instanceof Node\Name) {
                    return;
                }

                // Get method name
                if (!$node->name instanceof Node\Identifier) {
                    return;
                }

                $className = $node->class->toString();
                $methodName = $node->name->toString();
                $key = $className . '::' . $methodName;

                // Check for exact match
                if (isset($this->staticMethods[$key])) {
                    $config = $this->staticMethods[$key];
                    $this->context->addIssue(new Issue(
                        type: 'removed_method',
                        message: $config['message'],
                        line: $node->getLine(),
                        code: "{$className}::{$methodName}()",
                        suggestion: $config['suggestion'],
                        docsUrl: $config['docsUrl'],
                    ));
                    return;
                }

                // Check for partial class name match (e.g., 'Controller' matches 'SilverStripe\Control\Controller')
                foreach ($this->staticMethods as $configKey => $config) {
                    [$configClass, $configMethod] = explode('::', $configKey);
                    if ($configMethod === $methodName) {
                        // Check if class name ends with the configured class name
                        if ($className === $configClass || str_ends_with($className, '\\' . $configClass)) {
                            $this->context->addIssue(new Issue(
                                type: 'removed_method',
                                message: $config['message'],
                                line: $node->getLine(),
                                code: "{$className}::{$methodName}()",
                                suggestion: $config['suggestion'],
                                docsUrl: $config['docsUrl'],
                            ));
                            return;
                        }
                    }
                }
            }

            private function checkMethodCall(MethodCall $node): void
            {
                // Get method name
                if (!$node->name instanceof Node\Identifier) {
                    return;
                }

                $methodName = $node->name->toString();

                // Check if this method is in our removed methods list
                if (!isset($this->methodToClasses[$methodName])) {
                    return;
                }

                // Check if it's a $this-> call
                $isThisCall = $node->var instanceof Node\Expr\Variable
                    && is_string($node->var->name)
                    && $node->var->name === 'this';

                if ($isThisCall && $this->currentClass !== null) {
                    // Check if current class context matches any of the target classes
                    $applicableClasses = $this->methodToClasses[$methodName];

                    if ($this->classMatchesAny($this->currentClass, $applicableClasses)) {
                        $key = $applicableClasses[0] . '::' . $methodName;
                        $config = $this->removedMethods[$key];

                        $this->context->addIssue(new Issue(
                            type: 'removed_method',
                            message: $config['message'],
                            line: $node->getLine(),
                            code: "\$this->{$methodName}()",
                            suggestion: $config['suggestion'],
                            docsUrl: $config['docsUrl'],
                        ));
                    }
                }
            }

            /**
             * Check if a class matches any of the target class names.
             */
            private function classMatchesAny(Class_ $class, array $targetClasses): bool
            {
                // Check extends clause
                if ($class->extends !== null) {
                    $parentClass = $class->extends->toString();

                    foreach ($targetClasses as $target) {
                        if ($parentClass === $target || str_ends_with($parentClass, '\\' . $target)) {
                            return true;
                        }
                    }
                }

                // Could also check interfaces/traits in the future
                return false;
            }
        };
    }

    /**
     * Get all removed methods (default + additional).
     *
     * @return array<string, array{message: string, suggestion: string, docsUrl: string, isStatic?: bool}>
     */
    private function getMethods(): array
    {
        return array_merge($this->removedMethods, $this->additionalMethods);
    }
}
