<?php

declare(strict_types=1);

namespace SilverstripeMCP\Tests\Plugins;

use PHPUnit\Framework\TestCase;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\AnalysisResult;
use SilverstripeMCP\Issue;
use SilverstripeMCP\Plugins\BuildTaskValidatorPlugin;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class BuildTaskValidatorPluginTest extends TestCase
{
    private BuildTaskValidatorPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new BuildTaskValidatorPlugin();
        $this->plugin->configure([]);
    }

    public function testDetectsOldRunMethod(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;

class MyTask extends BuildTask {
    public function run(HTTPRequest $request) {
    }
}
PHP;
        $result = $this->analyze($code);

        $issues = $this->filterByType($result, 'deprecated_buildtask_signature');
        $this->assertCount(1, $issues);
        $this->assertStringContainsString('execute(InputInterface', $issues[0]->suggestion);
    }

    public function testDetectsMissingCommandName(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;

class MyTask extends BuildTask {
    protected function execute($input, $output): int {
        return 0;
    }
}
PHP;
        $result = $this->analyze($code);

        $issues = $this->filterByType($result, 'missing_command_name');
        $this->assertCount(1, $issues);
        $this->assertStringContainsString('$commandName', $issues[0]->message);
    }

    public function testDetectsEchoStatements(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;

class MyTask extends BuildTask {
    protected function execute($input, $output): int {
        echo "Hello";
        return 0;
    }
}
PHP;
        $result = $this->analyze($code);

        $issues = $this->filterByType($result, 'deprecated_echo');
        $this->assertCount(1, $issues);
        $this->assertStringContainsString('$output->writeln', $issues[0]->suggestion);
    }

    public function testDetectsPrintStatements(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;

class MyTask extends BuildTask {
    protected function execute($input, $output): int {
        print "Hello";
        return 0;
    }
}
PHP;
        $result = $this->analyze($code);

        $issues = $this->filterByType($result, 'deprecated_print');
        $this->assertCount(1, $issues);
        $this->assertStringContainsString('$output->writeln', $issues[0]->suggestion);
    }

    public function testPassesValidSS6BuildTask(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class MyTask extends BuildTask {
    protected static string $commandName = 'my-task';

    protected function execute(InputInterface $input, PolyOutput $output): int {
        $output->writeln('Running...');
        return Command::SUCCESS;
    }
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(0, $result->issues);
    }

    public function testIgnoresNonBuildTaskClasses(): void
    {
        $code = <<<'PHP'
<?php
class MyController {
    public function run($request) {
        echo "Hello";
    }
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(0, $result->issues);
    }

    public function testDetectsMultipleIssuesInSameClass(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;

class MyTask extends BuildTask {
    public function run(HTTPRequest $request) {
        echo "Hello";
        echo "World";
    }
}
PHP;
        $result = $this->analyze($code);

        // Should detect: run(HTTPRequest), missing $commandName, 2x echo
        $this->assertGreaterThanOrEqual(3, count($result->issues));
    }

    public function testDetectsMultipleEchoStatements(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;

class MyTask extends BuildTask {
    protected static string $commandName = 'my-task';

    protected function execute($input, $output): int {
        echo "Line 1";
        echo "Line 2";
        echo "Line 3";
        return 0;
    }
}
PHP;
        $result = $this->analyze($code);

        $echoIssues = $this->filterByType($result, 'deprecated_echo');
        $this->assertCount(3, $echoIssues);
    }

    public function testReportsCorrectLineNumbers(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;

class MyTask extends BuildTask {
    protected static string $commandName = 'my-task';

    protected function execute($input, $output): int {
        echo "Test";
        return 0;
    }
}
PHP;
        $result = $this->analyze($code);

        $echoIssues = $this->filterByType($result, 'deprecated_echo');
        $this->assertCount(1, $echoIssues);
        $this->assertEquals(8, $echoIssues[0]->line);
    }

    public function testDetectsInvalidCommandNameWithColon(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;

class MyTask extends BuildTask {
    protected static string $commandName = 'app:my-task';

    protected function execute($input, $output): int {
        return 0;
    }
}
PHP;
        $result = $this->analyze($code);

        $issues = $this->filterByType($result, 'invalid_command_name');
        $this->assertCount(1, $issues);
        $this->assertStringContainsString("must not contain", $issues[0]->message);
        $this->assertStringContainsString("my-task", $issues[0]->suggestion);
    }

    public function testDetectsInvalidCommandNameWithSlash(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;

class MyTask extends BuildTask {
    protected static string $commandName = 'app/my-task';

    protected function execute($input, $output): int {
        return 0;
    }
}
PHP;
        $result = $this->analyze($code);

        $issues = $this->filterByType($result, 'invalid_command_name');
        $this->assertCount(1, $issues);
        $this->assertStringContainsString("must not contain", $issues[0]->message);
        $this->assertStringContainsString("my-task", $issues[0]->suggestion);
    }

    public function testValidCommandNamePasses(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;

class MyTask extends BuildTask {
    protected static string $commandName = 'my-task';

    protected function execute($input, $output): int {
        return 0;
    }
}
PHP;
        $result = $this->analyze($code);

        $issues = $this->filterByType($result, 'invalid_command_name');
        $this->assertCount(0, $issues);
    }

    public function testIncludesDocsUrl(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Dev\BuildTask;

class MyTask extends BuildTask {
}
PHP;
        $result = $this->analyze($code);

        $issues = $this->filterByType($result, 'missing_command_name');
        $this->assertStringContainsString('docs.silverstripe.org', $issues[0]->docsUrl);
    }

    private function analyze(string $code): AnalysisResult
    {
        $context = new AnalysisContext($code, 'MyTask.php', '6.0');

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($this->plugin->getVisitor($context));
        $traverser->traverse($ast);

        return $context->getResult();
    }

    /**
     * @return Issue[]
     */
    private function filterByType(AnalysisResult $result, string $type): array
    {
        return array_values(array_filter(
            $result->issues,
            fn(Issue $issue) => $issue->type === $type
        ));
    }
}
