<?php

declare(strict_types=1);

namespace SilverstripeMCP\Tests\Plugins;

use PHPUnit\Framework\TestCase;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\AnalysisResult;
use SilverstripeMCP\Plugins\DeprecatedConfigPlugin;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class DeprecatedConfigPluginTest extends TestCase
{
    private DeprecatedConfigPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new DeprecatedConfigPlugin();
        $this->plugin->configure([]);
    }

    public function testDetectsConfigInstGet(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Core\Config\Config;

$value = Config::inst()->get('SilverStripe\CMS\Model\SiteTree', 'allowed_children');
PHP;
        $result = $this->analyze($code);

        $this->assertCount(1, $result->issues);
        $this->assertEquals('deprecated_config_api', $result->issues[0]->type);
        $this->assertStringContainsString('SiteTree::config()->get', $result->issues[0]->suggestion);
    }

    public function testDetectsConfigInstGetWithShortName(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Core\Config\Config;

$value = Config::inst()->get('MyClass', 'my_property');
PHP;
        $result = $this->analyze($code);

        $this->assertCount(1, $result->issues);
        $this->assertStringContainsString("MyClass::config()->get('my_property')", $result->issues[0]->suggestion);
    }

    public function testIgnoresStaticConfigAccess(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\CMS\Model\SiteTree;

$value = SiteTree::config()->get('allowed_children');
PHP;
        $result = $this->analyze($code);

        $this->assertCount(0, $result->issues);
    }

    public function testIgnoresUnrelatedMethodCalls(): void
    {
        $code = <<<'PHP'
<?php
class MyClass {
    public function inst() {
        return $this;
    }

    public function get($key) {
        return $this->data[$key];
    }
}

$obj = new MyClass();
$value = $obj->inst()->get('key');
PHP;
        $result = $this->analyze($code);

        $this->assertCount(0, $result->issues);
    }

    public function testReportsCorrectLineNumber(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Core\Config\Config;

// Some comment
// Another comment

$value = Config::inst()->get('MyClass', 'prop');
PHP;
        $result = $this->analyze($code);

        $this->assertEquals(7, $result->issues[0]->line);
    }

    public function testIncludesDocsUrl(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\Core\Config\Config;
$value = Config::inst()->get('MyClass', 'prop');
PHP;
        $result = $this->analyze($code);

        $this->assertStringContainsString('docs.silverstripe.org', $result->issues[0]->docsUrl);
    }

    public function testPluginMetadata(): void
    {
        $this->assertEquals('deprecated-config', $this->plugin->getName());
        $this->assertContains('5.*', $this->plugin->getTargetVersions());
        $this->assertContains('6.*', $this->plugin->getTargetVersions());
    }

    private function analyze(string $code): AnalysisResult
    {
        $context = new AnalysisContext($code, 'test.php', '6.0');

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($this->plugin->getVisitor($context));
        $traverser->traverse($ast);

        return $context->getResult();
    }
}
