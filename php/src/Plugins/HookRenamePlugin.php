<?php

declare(strict_types=1);

namespace SilverstripeMCP\Plugins;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\Contracts\ValidatorPluginInterface;
use SilverstripeMCP\Issue;

/**
 * Detects usage of extension hooks that were renamed in Silverstripe 6.
 *
 * This plugin checks for:
 * - Extension methods with old hook names
 * - Calls to extend() with old hook names
 *
 * Note: The SS6 changelog primarily documents hooks being changed to protected
 * (handled by ExtensionHookVisibilityPlugin). This plugin handles actual renames.
 */
class HookRenamePlugin implements ValidatorPluginInterface
{
    private const DOCS_URL = 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/';

    /** @var array<string, array{newName: string, message: string, docsUrl: string}> */
    private array $hookRenames = [];

    /** @var array<string, array{newName: string, message: string, docsUrl: string}> */
    private array $additionalRenames = [];

    public function __construct()
    {
        $this->loadDefaultRenames();
    }

    /**
     * Load default hook renames from config file.
     */
    private function loadDefaultRenames(): void
    {
        $configFile = __DIR__ . '/../Config/hook-renames.php';
        if (file_exists($configFile)) {
            $this->hookRenames = require $configFile;
        }
    }

    public function getName(): string
    {
        return 'hook-rename-validator';
    }

    public function getDescription(): string
    {
        return 'Detects usage of extension hooks that were renamed in Silverstripe 6';
    }

    public function getTargetVersions(): array
    {
        return ['6.*'];
    }

    public function configure(array $options): void
    {
        // Merge additional renames from config
        if (isset($options['additionalRenames']) && is_array($options['additionalRenames'])) {
            $this->additionalRenames = $options['additionalRenames'];
        }
    }

    public function getVisitor(AnalysisContext $context): NodeVisitor
    {
        $renames = $this->getRenames();

        // If no renames are configured, return a no-op visitor
        if (empty($renames)) {
            return new class extends NodeVisitorAbstract {};
        }

        return new class($context, $renames) extends NodeVisitorAbstract {
            /** @var array<string, array{newName: string, message: string, docsUrl: string}> */
            private array $hookRenames;

            public function __construct(
                private AnalysisContext $context,
                array $renames,
            ) {
                $this->hookRenames = $renames;
            }

            public function enterNode(Node $node): ?int
            {
                // Check for method definitions that match old hook names
                if ($node instanceof ClassMethod) {
                    $this->checkMethodDefinition($node);
                }

                // Check for $this->extend('oldHookName') or $this->invokeWithExtensions('oldHookName')
                if ($node instanceof MethodCall) {
                    $this->checkExtendCall($node);
                }

                return null;
            }

            private function checkMethodDefinition(ClassMethod $node): void
            {
                $methodName = $node->name->toString();

                if (isset($this->hookRenames[$methodName])) {
                    $config = $this->hookRenames[$methodName];

                    $this->context->addIssue(new Issue(
                        type: 'renamed_hook',
                        message: $config['message'],
                        line: $node->getLine(),
                        code: "function {$methodName}",
                        suggestion: "Rename to {$config['newName']}",
                        docsUrl: $config['docsUrl'],
                    ));
                }
            }

            private function checkExtendCall(MethodCall $node): void
            {
                // Check if it's $this->extend() or $this->invokeWithExtensions()
                if (!$node->name instanceof Node\Identifier) {
                    return;
                }

                $methodName = $node->name->toString();
                if (!in_array($methodName, ['extend', 'invokeWithExtensions'], true)) {
                    return;
                }

                // Check if first argument is a string with an old hook name
                if (empty($node->args)) {
                    return;
                }

                $firstArg = $node->args[0];
                if (!$firstArg instanceof Node\Arg) {
                    return;
                }

                if ($firstArg->value instanceof Node\Scalar\String_) {
                    $hookName = $firstArg->value->value;

                    if (isset($this->hookRenames[$hookName])) {
                        $config = $this->hookRenames[$hookName];

                        $this->context->addIssue(new Issue(
                            type: 'renamed_hook',
                            message: $config['message'],
                            line: $node->getLine(),
                            code: "\$this->{$methodName}('{$hookName}')",
                            suggestion: "Use '{$config['newName']}' instead",
                            docsUrl: $config['docsUrl'],
                        ));
                    }
                }
            }
        };
    }

    /**
     * Get all hook renames (default + additional).
     *
     * @return array<string, array{newName: string, message: string, docsUrl: string}>
     */
    private function getRenames(): array
    {
        return array_merge($this->hookRenames, $this->additionalRenames);
    }
}
