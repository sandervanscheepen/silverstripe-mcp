<?php

declare(strict_types=1);

namespace SilverstripeMCP\Tests;

use PHPUnit\Framework\TestCase;
use SilverstripeMCP\AnalyzerRunner;

class AnalyzerRunnerTest extends TestCase
{
    private AnalyzerRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new AnalyzerRunner();
    }

    public function testReturnsEmptyIssuesForValidCode(): void
    {
        $result = $this->runner->analyze('<?php echo 1;', 'test.php', '6.0');

        $this->assertCount(0, $result->issues);
        $this->assertFalse($result->rerun);
    }

    public function testReturnsParseErrorForInvalidPhp(): void
    {
        $result = $this->runner->analyze('<?php function {', 'test.php', '6.0');

        $this->assertCount(1, $result->issues);
        $this->assertEquals('parse_error', $result->issues[0]->type);
    }

    public function testRunsAllPlugins(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\ORM\ArrayList;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;

class MyTask extends BuildTask {
    public function run(HTTPRequest $request) {
        $list = new ArrayList();
    }
}
PHP;
        $result = $this->runner->analyze($code, 'MyTask.php', '6.0');

        // Should have issues from both plugins
        $types = array_map(fn($i) => $i->type, $result->issues);
        $this->assertContains('deprecated_import', $types);
        $this->assertContains('deprecated_buildtask_signature', $types);
    }

    public function testJsonSerialization(): void
    {
        $result = $this->runner->analyze('<?php use SilverStripe\ORM\ArrayList;', 'test.php', '6.0');

        $array = $result->toArray();
        $this->assertArrayHasKey('issues', $array);
        $this->assertArrayHasKey('suggestions', $array);
        $this->assertArrayHasKey('rerun', $array);

        $json = json_encode($result->toArray());
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('issues', $decoded);
        $this->assertArrayHasKey('suggestions', $decoded);
        $this->assertArrayHasKey('rerun', $decoded);
    }

    public function testRegistersBuiltinPlugins(): void
    {
        $pluginNames = $this->runner->getPluginNames();

        $this->assertContains('namespace-validator', $pluginNames);
        $this->assertContains('buildtask-validator', $pluginNames);
    }

    public function testLoadConfigDisablesPlugins(): void
    {
        $this->runner->loadConfig([
            'plugins' => [
                'namespace-validator' => [
                    'enabled' => false,
                ],
            ],
        ]);

        $pluginNames = $this->runner->getPluginNames();
        $this->assertNotContains('namespace-validator', $pluginNames);
        $this->assertContains('buildtask-validator', $pluginNames);
    }

    public function testRerunIsTrueWhenIssuesExist(): void
    {
        $result = $this->runner->analyze('<?php use SilverStripe\ORM\ArrayList;', 'test.php', '6.0');

        $this->assertTrue($result->rerun);
    }

    public function testRerunIsFalseWhenNoIssues(): void
    {
        $result = $this->runner->analyze('<?php echo 1;', 'test.php', '6.0');

        $this->assertFalse($result->rerun);
    }

    public function testHandlesEmptyCode(): void
    {
        $result = $this->runner->analyze('<?php', 'test.php', '6.0');

        $this->assertCount(0, $result->issues);
    }
}
