<?php

declare(strict_types=1);

namespace SilverstripeMCP\Tests\Plugins;

use PHPUnit\Framework\TestCase;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\AnalysisResult;
use SilverstripeMCP\Plugins\NamespaceValidatorPlugin;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class NamespaceValidatorPluginTest extends TestCase
{
    private NamespaceValidatorPlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new NamespaceValidatorPlugin();
        $this->plugin->configure([]);
    }

    public function testDetectsOldArrayListImport(): void
    {
        $code = '<?php use SilverStripe\ORM\ArrayList;';
        $result = $this->analyze($code);

        $this->assertCount(1, $result->issues);
        $issue = $result->issues[0];
        $this->assertEquals('deprecated_import', $issue->type);
        $this->assertStringContainsString('SilverStripe\Model\List\ArrayList', $issue->suggestion);
    }

    public function testDetectsOldArrayDataImport(): void
    {
        $code = '<?php use SilverStripe\View\ArrayData;';
        $result = $this->analyze($code);

        $this->assertCount(1, $result->issues);
        $this->assertStringContainsString('SilverStripe\Model\ArrayData', $result->issues[0]->suggestion);
    }

    public function testDetectsOldViewableDataImport(): void
    {
        $code = '<?php use SilverStripe\View\ViewableData;';
        $result = $this->analyze($code);

        $this->assertCount(1, $result->issues);
        $this->assertStringContainsString('SilverStripe\Model\ModelData', $result->issues[0]->suggestion);
    }

    public function testIgnoresCorrectSS6Imports(): void
    {
        $code = '<?php use SilverStripe\Model\List\ArrayList;';
        $result = $this->analyze($code);

        $this->assertCount(0, $result->issues);
    }

    public function testIgnoresUnrelatedImports(): void
    {
        $code = '<?php use App\MyClass;';
        $result = $this->analyze($code);

        $this->assertCount(0, $result->issues);
    }

    public function testDetectsMultipleWrongImports(): void
    {
        $code = <<<'PHP'
<?php
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\PaginatedList;
PHP;
        $result = $this->analyze($code);

        $this->assertCount(3, $result->issues);
    }

    public function testAdditionalMappingsFromConfig(): void
    {
        $this->plugin->configure([
            'additionalMappings' => [
                'App\\Legacy\\OldClass' => 'App\\Modern\\NewClass',
            ],
        ]);

        $code = '<?php use App\Legacy\OldClass;';
        $result = $this->analyze($code);

        $this->assertCount(1, $result->issues);
        $this->assertStringContainsString('App\Modern\NewClass', $result->issues[0]->suggestion);
    }

    public function testReportsCorrectLineNumber(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use SilverStripe\ORM\ArrayList;
PHP;
        $result = $this->analyze($code);

        $this->assertEquals(5, $result->issues[0]->line);
    }

    public function testHandlesGroupedUseStatements(): void
    {
        $code = '<?php use SilverStripe\ORM\{ArrayList, DataList};';
        $result = $this->analyze($code);

        // Should detect ArrayList as deprecated
        $this->assertGreaterThanOrEqual(1, count($result->issues));
        $this->assertEquals('deprecated_import', $result->issues[0]->type);
    }

    public function testIncludesDocsUrl(): void
    {
        $code = '<?php use SilverStripe\ORM\ArrayList;';
        $result = $this->analyze($code);

        $this->assertStringContainsString('docs.silverstripe.org', $result->issues[0]->docsUrl);
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
