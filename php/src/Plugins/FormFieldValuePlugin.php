<?php

declare(strict_types=1);

namespace SilverstripeMCP\Plugins;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\Contracts\ValidatorPluginInterface;
use SilverstripeMCP\Issue;

/**
 * Detects usage of FormField::Value() which has been split into three methods in SS6.
 *
 * In Silverstripe 6, FormField::Value() has been replaced with:
 * - dataValue(): Returns the raw data value
 * - presentedValue(): Returns the value for display
 * - processedValue(): Returns the value after form processing
 */
class FormFieldValuePlugin implements ValidatorPluginInterface
{
    private const DOCS_URL = 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/#formfield-value';

    public function getName(): string
    {
        return 'formfield-value-validator';
    }

    public function getDescription(): string
    {
        return 'Detects usage of deprecated FormField::Value() method';
    }

    public function getTargetVersions(): array
    {
        return ['6.*'];
    }

    public function configure(array $options): void
    {
        // No options for this plugin currently
    }

    public function getVisitor(AnalysisContext $context): NodeVisitor
    {
        $docsUrl = self::DOCS_URL;

        return new class($context, $docsUrl) extends NodeVisitorAbstract {
            public function __construct(
                private AnalysisContext $context,
                private string $docsUrl,
            ) {}

            public function enterNode(Node $node): ?int
            {
                // Look for method calls to ->Value()
                if ($node instanceof MethodCall) {
                    $this->checkMethodCall($node);
                }

                return null;
            }

            private function checkMethodCall(MethodCall $node): void
            {
                // Get method name - can be Identifier or Expr (for dynamic method names)
                if (!$node->name instanceof Node\Identifier) {
                    return;
                }

                $methodName = $node->name->toString();

                // Check for Value() method call
                if ($methodName === 'Value') {
                    // Try to determine if this is likely a FormField
                    if ($this->isLikelyFormFieldCall($node)) {
                        $this->context->addIssue(new Issue(
                            type: 'deprecated_formfield_value',
                            message: 'FormField::Value() has been split into three methods in SS6: dataValue(), presentedValue(), and processedValue()',
                            line: $node->getLine(),
                            code: '->Value()',
                            suggestion: 'Replace ->Value() with the appropriate method: dataValue() for raw data, presentedValue() for display, processedValue() for form handling',
                            docsUrl: $this->docsUrl,
                        ));
                    }
                }
            }

            /**
             * Attempt to determine if a method call is likely on a FormField.
             *
             * This uses heuristics since we can't do full type inference:
             * - Check if the variable is named something like $field
             * - Check if it's from common FormField-returning methods
             */
            private function isLikelyFormFieldCall(MethodCall $node): bool
            {
                $var = $node->var;

                // Check variable name hints
                if ($var instanceof Node\Expr\Variable && is_string($var->name)) {
                    $varName = strtolower($var->name);
                    $fieldHints = ['field', 'formfield', 'textfield', 'input'];
                    foreach ($fieldHints as $hint) {
                        if (str_contains($varName, $hint)) {
                            return true;
                        }
                    }
                }

                // Check if it's a chained call from common FormField-returning methods
                if ($var instanceof MethodCall) {
                    if ($var->name instanceof Node\Identifier) {
                        $chainedMethod = $var->name->toString();
                        $formFieldMethods = [
                            'dataFieldByName',
                            'fieldByName',
                            'Fields',
                            'push',
                            'first',
                            'last',
                        ];

                        if (in_array($chainedMethod, $formFieldMethods, true)) {
                            return true;
                        }
                    }
                }

                // Check if it's accessing an array element that might be a field
                if ($var instanceof Node\Expr\ArrayDimFetch) {
                    return true;
                }

                // Conservative approach: flag all ->Value() calls for review
                // since Value() is not a common method name outside FormField context
                return true;
            }
        };
    }
}
