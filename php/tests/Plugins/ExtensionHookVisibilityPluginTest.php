<?php

declare(strict_types=1);

namespace SilverstripeMCP\Tests\Plugins;

use PHPUnit\Framework\TestCase;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\AnalysisResult;
use SilverstripeMCP\Issue;
use SilverstripeMCP\Plugins\ExtensionHookVisibilityPlugin;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class ExtensionHookVisibilityPluginTest extends TestCase
{
    private ExtensionHookVisibilityPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new ExtensionHookVisibilityPlugin();
        $this->plugin->configure([]);
    }

    public function testDetectsPublicOnBeforeWrite(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\ORM\DataExtension;

class MyExtension extends DataExtension {
    public function onBeforeWrite() {
    }
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(1, $result->issues);
        $this->assertEquals('hook_visibility', $result->issues[0]->type);
        $this->assertStringContainsString('protected function onBeforeWrite', $result->issues[0]->suggestion);
    }

    public function testDetectsPublicOnAfterWrite(): void
    {
        $code = <<<'PHP'
<?php
class MyExtension extends DataExtension {
    public function onAfterWrite() {
    }
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(1, $result->issues);
        $this->assertStringContainsString('onAfterWrite', $result->issues[0]->message);
    }

    public function testDetectsPublicUpdateCMSFields(): void
    {
        $code = <<<'PHP'
<?php
class MyExtension extends DataExtension {
    public function updateCMSFields($fields) {
    }
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(1, $result->issues);
        $this->assertStringContainsString('updateCMSFields', $result->issues[0]->message);
    }

    public function testDetectsPublicAugmentSQL(): void
    {
        $code = <<<'PHP'
<?php
class MyExtension extends DataExtension {
    public function augmentSQL($query) {
    }
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(1, $result->issues);
    }

    public function testIgnoresProtectedHooks(): void
    {
        $code = <<<'PHP'
<?php
class MyExtension extends DataExtension {
    protected function onBeforeWrite() {
    }

    protected function updateCMSFields($fields) {
    }
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(0, $result->issues);
    }

    public function testIgnoresNonExtensionClasses(): void
    {
        $code = <<<'PHP'
<?php
class MyController extends Controller {
    public function onBeforeInit() {
    }

    public function updateData() {
    }
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(0, $result->issues);
    }

    public function testIgnoresNonHookMethods(): void
    {
        $code = <<<'PHP'
<?php
class MyExtension extends DataExtension {
    public function doSomething() {
    }

    public function getData() {
    }
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(0, $result->issues);
    }

    public function testSupportsAdditionalPrefixes(): void
    {
        $this->plugin->configure([
            'additionalPrefixes' => ['can', 'provide'],
        ]);

        $code = <<<'PHP'
<?php
class MyExtension extends DataExtension {
    public function canEdit($member) {
        return true;
    }

    public function providePermissions() {
        return [];
    }
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(2, $result->issues);
    }

    public function testDetectsMultipleIssues(): void
    {
        $code = <<<'PHP'
<?php
class MyExtension extends DataExtension {
    public function onBeforeWrite() {
    }

    public function onAfterWrite() {
    }

    public function updateCMSFields($fields) {
    }
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(3, $result->issues);
    }

    public function testPluginMetadata(): void
    {
        $this->assertEquals('extension-hook-visibility', $this->plugin->getName());
        $this->assertContains('6.*', $this->plugin->getTargetVersions());
    }

    private function analyze(string $code): AnalysisResult
    {
        $context = new AnalysisContext($code, 'MyExtension.php', '6.0');

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($this->plugin->getVisitor($context));
        $traverser->traverse($ast);

        return $context->getResult();
    }
}
