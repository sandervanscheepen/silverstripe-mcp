<?php

declare(strict_types=1);

namespace SilverstripeMCP\Tests\Plugins;

use PHPUnit\Framework\TestCase;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\AnalysisResult;
use SilverstripeMCP\Plugins\ElementalNamespacePlugin;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class ElementalNamespacePluginTest extends TestCase
{
    private ElementalNamespacePlugin $plugin;

    protected function setUp(): void
    {
        $this->plugin = new ElementalNamespacePlugin();
        $this->plugin->configure([]);
    }

    public function testNoIssuesWithCurrentNamespaces(): void
    {
        $code = <<<'PHP'
<?php
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;

class MyElement extends BaseElement {
}
PHP;
        $result = $this->analyze($code);

        // Currently no deprecated namespaces configured
        $this->assertCount(0, $result->issues);
    }

    public function testDetectsConfiguredMappings(): void
    {
        $this->plugin->configure([
            'mappings' => [
                'Old\\Elemental\\Block' => 'DNADesign\\Elemental\\Models\\BaseElement',
            ],
        ]);

        $code = <<<'PHP'
<?php
use Old\Elemental\Block;

class MyElement extends Block {
}
PHP;
        $result = $this->analyze($code);

        $this->assertCount(1, $result->issues);
        $this->assertEquals('deprecated_elemental_import', $result->issues[0]->type);
        $this->assertStringContainsString('DNADesign\\Elemental\\Models\\BaseElement', $result->issues[0]->suggestion);
    }

    public function testIgnoresUnrelatedImports(): void
    {
        $code = <<<'PHP'
<?php
use App\MyClass;
use SilverStripe\ORM\DataObject;
PHP;
        $result = $this->analyze($code);

        $this->assertCount(0, $result->issues);
    }

    public function testDetectsMultipleDeprecatedImports(): void
    {
        $this->plugin->configure([
            'mappings' => [
                'Old\\Elemental\\Block' => 'DNADesign\\Elemental\\Models\\BaseElement',
                'Old\\Elemental\\Area' => 'DNADesign\\Elemental\\Models\\ElementalArea',
            ],
        ]);

        $code = <<<'PHP'
<?php
use Old\Elemental\Block;
use Old\Elemental\Area;
PHP;
        $result = $this->analyze($code);

        $this->assertCount(2, $result->issues);
    }

    public function testIncludesDocsUrl(): void
    {
        $this->plugin->configure([
            'mappings' => [
                'Old\\Elemental\\Block' => 'DNADesign\\Elemental\\Models\\BaseElement',
            ],
        ]);

        $code = '<?php use Old\Elemental\Block;';
        $result = $this->analyze($code);

        $this->assertStringContainsString('github.com', $result->issues[0]->docsUrl);
    }

    public function testPluginMetadata(): void
    {
        $this->assertEquals('elemental-namespace', $this->plugin->getName());
        $this->assertContains('6.*', $this->plugin->getTargetVersions());
    }

    private function analyze(string $code): AnalysisResult
    {
        $context = new AnalysisContext($code, 'MyElement.php', '6.0');

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($this->plugin->getVisitor($context));
        $traverser->traverse($ast);

        return $context->getResult();
    }
}
