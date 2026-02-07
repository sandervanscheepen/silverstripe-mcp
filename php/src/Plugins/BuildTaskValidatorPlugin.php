<?php

declare(strict_types=1);

namespace SilverstripeMCP\Plugins;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Expr\Print_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\Contracts\ValidatorPluginInterface;
use SilverstripeMCP\Issue;

/**
 * Validates BuildTask classes for Silverstripe 6 compatibility.
 *
 * Detects old BuildTask patterns that need migration to the new PolyCommand pattern:
 * - run(HTTPRequest $request) method (deprecated)
 * - Missing $commandName static property
 * - Usage of echo statements (should use $output->writeln())
 */
class BuildTaskValidatorPlugin implements ValidatorPluginInterface
{
    private const DOCS_URL = 'https://docs.silverstripe.org/en/6/developer_guides/cli/polycommand/#buildtask';

    public function getName(): string
    {
        return 'buildtask-validator';
    }

    public function getDescription(): string
    {
        return 'Validates BuildTask classes for Silverstripe 6 compatibility';
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
        return new class($context) extends NodeVisitorAbstract {
            private ?Class_ $currentBuildTaskClass = null;
            private bool $hasCommandName = false;
            private bool $hasRunMethod = false;
            private bool $hasExecuteMethod = false;

            public function __construct(
                private AnalysisContext $context,
            ) {}

            public function enterNode(Node $node): ?int
            {
                // Check if we're entering a class that extends BuildTask
                if ($node instanceof Class_) {
                    if ($this->extendsBuildTask($node)) {
                        $this->currentBuildTaskClass = $node;
                        $this->hasCommandName = false;
                        $this->hasRunMethod = false;
                        $this->hasExecuteMethod = false;
                    }
                    return null;
                }

                // Only analyze nodes inside a BuildTask class
                if ($this->currentBuildTaskClass === null) {
                    return null;
                }

                // Check for $commandName property
                if ($node instanceof Property) {
                    foreach ($node->props as $prop) {
                        if ($prop->name->toString() === 'commandName') {
                            $this->hasCommandName = true;
                        }
                    }
                }

                // Check for methods
                if ($node instanceof ClassMethod) {
                    $methodName = $node->name->toString();

                    if ($methodName === 'run') {
                        $this->hasRunMethod = true;
                        $this->checkRunMethod($node);
                    }

                    if ($methodName === 'execute') {
                        $this->hasExecuteMethod = true;
                    }
                }

                // Check for echo statements
                if ($node instanceof Echo_) {
                    $this->context->addIssue(new Issue(
                        type: 'deprecated_echo',
                        message: "Use \$output->writeln() instead of echo in BuildTask classes",
                        line: $node->getLine(),
                        code: 'echo ...',
                        suggestion: "\$output->writeln('...')",
                        docsUrl: 'https://docs.silverstripe.org/en/6/developer_guides/cli/polycommand/#buildtask',
                    ));
                }

                // Check for print statements
                if ($node instanceof Print_) {
                    $this->context->addIssue(new Issue(
                        type: 'deprecated_print',
                        message: "Use \$output->writeln() instead of print in BuildTask classes",
                        line: $node->getLine(),
                        code: 'print ...',
                        suggestion: "\$output->writeln('...')",
                        docsUrl: 'https://docs.silverstripe.org/en/6/developer_guides/cli/polycommand/#buildtask',
                    ));
                }

                return null;
            }

            public function leaveNode(Node $node): ?int
            {
                // When leaving a BuildTask class, check for missing required elements
                if ($node instanceof Class_ && $node === $this->currentBuildTaskClass) {
                    if (!$this->hasCommandName) {
                        $this->context->addIssue(new Issue(
                            type: 'missing_command_name',
                            message: "BuildTask classes require a static \$commandName property in Silverstripe 6",
                            line: $node->getLine(),
                            suggestion: "protected static string \$commandName = 'app:my-task';",
                            docsUrl: 'https://docs.silverstripe.org/en/6/developer_guides/cli/polycommand/#buildtask',
                        ));
                    }

                    $this->currentBuildTaskClass = null;
                }

                return null;
            }

            /**
             * Check if a class extends BuildTask.
             */
            private function extendsBuildTask(Class_ $node): bool
            {
                if ($node->extends === null) {
                    return false;
                }

                $parentClass = $node->extends->toString();

                // Check for common BuildTask class names
                $buildTaskClasses = [
                    'BuildTask',
                    'SilverStripe\\Dev\\BuildTask',
                ];

                return in_array($parentClass, $buildTaskClasses, true);
            }

            /**
             * Check the run method for deprecated patterns.
             */
            private function checkRunMethod(ClassMethod $node): void
            {
                // Check if it has HTTPRequest parameter
                foreach ($node->params as $param) {
                    $paramType = $param->type;
                    if ($paramType === null) {
                        continue;
                    }

                    $typeName = '';
                    if ($paramType instanceof Node\Identifier) {
                        $typeName = $paramType->toString();
                    } elseif ($paramType instanceof Node\Name) {
                        $typeName = $paramType->toString();
                    }

                    $httpRequestTypes = [
                        'HTTPRequest',
                        'SilverStripe\\Control\\HTTPRequest',
                    ];

                    if (in_array($typeName, $httpRequestTypes, true)) {
                        $this->context->addIssue(new Issue(
                            type: 'deprecated_buildtask_signature',
                            message: "BuildTask::run(HTTPRequest) is deprecated. Use execute(InputInterface \$input, PolyOutput \$output): int",
                            line: $node->getLine(),
                            code: 'public function run(HTTPRequest $request)',
                            suggestion: 'protected function execute(InputInterface $input, PolyOutput $output): int',
                            docsUrl: 'https://docs.silverstripe.org/en/6/developer_guides/cli/polycommand/#buildtask',
                        ));
                        break;
                    }
                }
            }
        };
    }
}
