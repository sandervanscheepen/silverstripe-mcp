# Silverstripe MCP Server

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![Node.js](https://img.shields.io/badge/Node.js-18%2B-green.svg)](https://nodejs.org/)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-purple.svg)](https://php.net/)

A [Model Context Protocol](https://modelcontextprotocol.io/) server that provides real-time validation feedback when AI assistants generate Silverstripe 6 PHP code. Catches common migration issues from Silverstripe 5 to 6 before they reach your codebase.

**Documentation:** [Architecture](./docs/architecture.md) | [Agent Instructions Setup](./docs/recommended-agent-instructions.md)

## The Problem

When using AI coding assistants like Claude Code with Silverstripe 6 projects, they often generate code with outdated patterns:

```php
// AI generates this (SS5 style):
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;

class MyTask extends BuildTask {
    public function run(HTTPRequest $request) {
        echo "Processing...";
    }
}
```

This MCP server catches these issues immediately, allowing the AI to self-correct:

```php
// After validation, AI generates this (SS6 style):
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;

class MyTask extends BuildTask {
    protected static string $commandName = 'my-task';

    protected function execute(InputInterface $input, PolyOutput $output): int {
        $output->writeln('Processing...');
        return Command::SUCCESS;
    }
}
```

## How It Works

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  AI generates   │────▶│  MCP validates  │────▶│  AI fixes and   │
│  PHP code       │     │  against SS6    │     │  re-validates   │
└─────────────────┘     └─────────────────┘     └─────────────────┘
                               │
                               ▼
                        ┌─────────────────┐
                        │  PHP Analyzer   │
                        │  (AST-based)    │
                        └─────────────────┘
                               │
                   ┌───────────┴───────────┐
                   ▼                       ▼
            ┌─────────────┐         ┌─────────────┐
            │  Namespace  │         │  BuildTask  │
            │  Validator  │         │  Validator  │
            └─────────────┘         └─────────────┘
```

The server exposes an `ss-validator` tool that:
1. Parses PHP code into an Abstract Syntax Tree (AST)
2. Runs plugin-based validators against the code
3. Returns issues with line numbers and suggested fixes
4. The AI iterates until no issues remain

## Quick Start

### Installation

```bash
git clone https://github.com/sandervanscheepen/silverstripe-mcp
cd silverstripe-mcp

# Install dependencies
npm install
cd php && composer install && cd ..

# Build
npm run build
```

Then add to your MCP client (e.g., Claude Code):

```bash
claude mcp add silverstripe-mcp -- node /path/to/silverstripe-mcp/dist/index.js
```

Or add to your MCP client configuration file:

```json
{
  "mcpServers": {
    "silverstripe": {
      "command": "node",
      "args": ["/path/to/silverstripe-mcp/dist/index.js"]
    }
  }
}
```

## Agent Instructions Setup

To get the most out of this MCP server, add instructions to your AI agent's project instructions file (e.g., `CLAUDE.md`, `.cursorrules`, `.github/copilot-instructions.md`) that tell it to use the `ss-validator` tool on all generated PHP code. See [Recommended Agent Instructions](./docs/recommended-agent-instructions.md) for full, minimal, and project-specific templates you can copy into your project.

## Built-in Validators

### Namespace Validator

Detects outdated Silverstripe 5 imports and suggests their Silverstripe 6 equivalents:

| SS5 Namespace | SS6 Namespace |
|---------------|---------------|
| `SilverStripe\ORM\ArrayList` | `SilverStripe\Model\List\ArrayList` |
| `SilverStripe\ORM\PaginatedList` | `SilverStripe\Model\List\PaginatedList` |
| `SilverStripe\ORM\Map` | `SilverStripe\Model\List\Map` |
| `SilverStripe\ORM\GroupedList` | `SilverStripe\Model\List\GroupedList` |
| `SilverStripe\View\ArrayData` | `SilverStripe\Model\ArrayData` |
| `SilverStripe\View\ViewableData` | `SilverStripe\Model\ModelData` |
| `SilverStripe\ORM\ValidationResult` | `SilverStripe\Core\Validation\ValidationResult` |
| `SilverStripe\ORM\ValidationException` | `SilverStripe\Core\Validation\ValidationException` |

### BuildTask Validator

Detects old BuildTask patterns that need migration to PolyCommand:

| Issue | Detection | Suggestion |
|-------|-----------|------------|
| Deprecated method signature | `run(HTTPRequest $request)` | `execute(InputInterface $input, PolyOutput $output): int` |
| Missing command name | No `$commandName` property | `protected static string $commandName = 'my-task';` |
| Output via echo | `echo "..."` | `$output->writeln('...')` |
| Output via print | `print "..."` | `$output->writeln('...')` |

### FormField Value Validator

Detects usage of `FormField::Value()` which was split into three methods in SS6:

```php
// Detects this:
$value = $field->Value();

// Suggests using one of:
$value = $field->dataValue();       // Raw data value
$value = $field->presentedValue();  // Value for display
$value = $field->processedValue();  // Value after form processing
```

### Removed Method Validator

Detects calls to methods that were removed in Silverstripe 6:

| Removed Method | Suggestion |
|----------------|------------|
| `Controller::has_curr()` | Use `Controller::curr()` with try/catch |
| `DataObject::getCMSValidator()` | Use `getCMSCompositeValidator()` instead |
| `Requirements::themedCSS()` | Use `Requirements::css()` with ThemeResourceLoader |
| `Requirements::themedJavascript()` | Use `Requirements::javascript()` with ThemeResourceLoader |
| `Object::useCustomClass()` | Use Injector configuration instead |

### Deprecated Config API (`deprecated-config`)

Detects usage of the deprecated `Config::inst()->get()` pattern:

```php
// Detects this:
$value = Config::inst()->get('SilverStripe\CMS\Model\SiteTree', 'allowed_children');

// Suggests this:
$value = SiteTree::config()->get('allowed_children');
```

## Context-Aware Validators

The following validators auto-enable based on code context, minimizing overhead when analyzing code that doesn't need them:

### Extension Hook Visibility (`extension-hook-visibility`)

**Auto-enabled when:** Class extends `Extension`, `DataExtension`, or `SiteTreeExtension`

SS6 changed many extension hook methods to `protected`. Detects public hooks in Extension classes:

```php
class MyExtension extends DataExtension {
    // Detects: should be protected
    public function onBeforeWrite() { }
    public function updateCMSFields($fields) { }
}
```

Configurable prefixes: `onBefore`, `onAfter`, `update`, `augment` (add more via `additionalPrefixes`).

### Elemental Namespace (`elemental-namespace`)

**Auto-enabled when:** Any import starts with `DNADesign\Elemental`

For projects using `dnadesign/silverstripe-elemental`. Detects namespace changes in Elemental 6:

| SS5 Namespace | SS6 Namespace |
|---------------|---------------|
| `DNADesign\Elemental\TopPage\DataExtension` | `DNADesign\Elemental\Extensions\TopPageElementExtension` |
| `DNADesign\Elemental\TopPage\FluentExtension` | `DNADesign\Elemental\Extensions\TopPageFluentElementExtension` |
| `DNADesign\Elemental\TopPage\SiteTreeExtension` | `DNADesign\Elemental\Extensions\TopPageSiteTreeExtension` |
| `DNADesign\Elemental\Controllers\ElementSiteTreeFilterSearch` | `DNADesign\Elemental\ORM\Search\ElementalSiteTreeSearchContext` |

Also detects removed classes (GraphQL, ElementalLeftAndMainExtension, etc.).

### Forcing All Plugins

To force-enable all validators regardless of context:

**Via config (`silverstripe-mcp.json`):**
```json
{
  "enableAllPlugins": true
}
```

**Via tool argument:**
```json
{
  "code": "<?php ...",
  "enableAllPlugins": true
}
```

You can also explicitly enable individual auto-plugins to always run:

```json
{
  "plugins": {
    "elemental-namespace": {
      "enabled": true,
      "additionalMappings": {
        "Custom\\Old\\Class": "Custom\\New\\Class"
      }
    }
  }
}
```

## Configuration

Create `silverstripe-mcp.json` in your project root to customize behavior:

```json
{
  "phpBinary": "/path/to/php",
  "targetVersion": "6.0",
  "plugins": {
    "namespace-validator": {
      "enabled": true,
      "additionalMappings": {
        "App\\Legacy\\MyClass": "App\\Modern\\MyClass"
      }
    },
    "buildtask-validator": {
      "enabled": true
    },
    "deprecated-config": {
      "enabled": true
    },
    "extension-hook-visibility": {
      "enabled": true,
      "additionalPrefixes": ["can", "provide"]
    }
  },
  "customPlugins": [
    "./my-plugins/CustomValidator.php"
  ]
}
```

See [silverstripe-mcp.example.json](./silverstripe-mcp.example.json) for a complete example.

### Configuration Options

| Option | Type | Description |
|--------|------|-------------|
| `phpBinary` | `string` | Path to PHP 8.3+ executable (auto-detected if not specified) |
| `targetVersion` | `string` | Silverstripe version to validate against (default: `"6.0"`) |
| `enableAllPlugins` | `boolean` | Force-enable all plugins including context-aware ones (default: `false`) |
| `plugins` | `object` | Per-plugin configuration |
| `plugins.*.enabled` | `boolean` | Enable/disable a specific plugin |
| `plugins.namespace-validator.additionalMappings` | `object` | Custom namespace migrations |
| `customPlugins` | `string[]` | Paths to custom validator plugins |

### PHP Binary Resolution

The server requires PHP 8.3+ (Silverstripe 6's minimum version). It resolves the PHP binary in this order:

1. **Config file**: `phpBinary` in `silverstripe-mcp.json`
2. **Environment variable**: `PHP_BINARY` (if version >= 8.3)
3. **Auto-detect**: Common locations (Laragon, XAMPP, Homebrew, system paths)
4. **System PHP**: Falls back to `php` command (if version >= 8.3)

If your system PHP is below 8.3, specify the path in your config:

```json
{
  "phpBinary": "C:/laragon/bin/php/php-8.3.22-Win32-vs16-x64/php.exe"
}
```

Or set the `PHP_BINARY` environment variable in your MCP client config:

```json
{
  "mcpServers": {
    "silverstripe": {
      "command": "node",
      "args": ["/path/to/silverstripe-mcp/dist/index.js"],
      "env": {
        "PHP_BINARY": "/usr/local/bin/php8.3"
      }
    }
  }
}
```

## Writing Custom Plugins

Create a PHP class implementing `ValidatorPluginInterface`:

```php
<?php

namespace MyOrg\MCPPlugins;

use SilverstripeMCP\Contracts\ValidatorPluginInterface;
use SilverstripeMCP\AnalysisContext;
use SilverstripeMCP\Issue;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

class DeprecatedMethodPlugin implements ValidatorPluginInterface
{
    public function getName(): string
    {
        return 'deprecated-method-validator';
    }

    public function getDescription(): string
    {
        return 'Detects usage of deprecated methods';
    }

    public function getTargetVersions(): array
    {
        return ['6.*']; // Applies to all SS6.x versions
    }

    public function configure(array $options): void
    {
        // Handle configuration options
    }

    public function getVisitor(AnalysisContext $context): \PhpParser\NodeVisitor
    {
        return new class($context) extends NodeVisitorAbstract {
            public function __construct(private AnalysisContext $context) {}

            public function enterNode(Node $node): ?int
            {
                // Detect deprecated method calls
                if ($node instanceof Node\Expr\MethodCall) {
                    $methodName = $node->name->toString();

                    if ($methodName === 'deprecatedMethod') {
                        $this->context->addIssue(new Issue(
                            type: 'deprecated_method',
                            message: 'deprecatedMethod() is deprecated in SS6',
                            line: $node->getLine(),
                            suggestion: 'Use newMethod() instead',
                            docsUrl: 'https://docs.silverstripe.org/...'
                        ));
                    }
                }
                return null;
            }
        };
    }
}

// Return the class name for auto-loading
return DeprecatedMethodPlugin::class;
```

Register in your configuration:

```json
{
  "customPlugins": [
    "./plugins/DeprecatedMethodPlugin.php"
  ],
  "plugins": {
    "deprecated-method-validator": {
      "enabled": true
    }
  }
}
```

## Testing

The project includes comprehensive test suites for both PHP and TypeScript:

```bash
# Run PHP tests (PHPUnit)
cd php && composer test

# Run TypeScript tests (Vitest)
npm test

# Run all tests
npm run test:all

# Watch mode for development
npm run test:watch
```

## Project Structure

```
silverstripe-mcp/
├── src/                          # TypeScript MCP server
│   ├── index.ts                  # Entry point, stdio transport
│   ├── tools/
│   │   └── ss-validator.ts       # Main validation tool
│   └── lib/
│       └── php-bridge.ts         # PHP subprocess communication
│
├── php/                          # PHP analyzer
│   ├── bin/
│   │   └── analyze               # CLI entry point
│   ├── src/
│   │   ├── AnalyzerRunner.php    # Plugin orchestration
│   │   ├── AnalysisContext.php   # Shared analysis state
│   │   ├── Issue.php             # Issue data structure
│   │   ├── Contracts/
│   │   │   └── ValidatorPluginInterface.php
│   │   ├── Plugins/              # Validator plugins
│   │   │   ├── NamespaceValidatorPlugin.php      (core)
│   │   │   ├── BuildTaskValidatorPlugin.php      (core)
│   │   │   ├── FormFieldValuePlugin.php          (core)
│   │   │   ├── RemovedMethodPlugin.php           (core)
│   │   │   ├── HookRenamePlugin.php              (core)
│   │   │   ├── DeprecatedConfigPlugin.php        (core)
│   │   │   ├── ExtensionHookVisibilityPlugin.php (auto: Extension classes)
│   │   │   └── ElementalNamespacePlugin.php      (auto: Elemental imports)
│   │   └── Config/
│   │       ├── namespace-mappings.php
│   │       ├── removed-methods.php
│   │       ├── hook-renames.php
│   │       └── elemental-mappings.php
│   └── tests/                    # PHPUnit tests
│
├── tests/                        # Vitest tests
│   ├── php-bridge.test.ts
│   ├── ss-validator.test.ts
│   └── fixtures/
│
├── docs/
│   ├── architecture.md                    # Technical architecture
│   └── recommended-agent-instructions.md  # Setup for AI agents
├── silverstripe-mcp.example.json # Example configuration
├── CLAUDE.md                     # Development instructions
└── README.md
```

## Development

```bash
# Build and watch for changes
npm run dev

# Test PHP analyzer directly
cd php && php bin/analyze '{"code": "<?php use SilverStripe\\ORM\\ArrayList;"}'

# Example output:
{
  "issues": [{
    "type": "deprecated_import",
    "message": "'SilverStripe\\ORM\\ArrayList' has moved to 'SilverStripe\\Model\\List\\ArrayList' in Silverstripe 6",
    "line": 1,
    "suggestion": "use SilverStripe\\Model\\List\\ArrayList;",
    "docsUrl": "https://docs.silverstripe.org/en/6/changelogs/6.0.0/#renamed-classes"
  }],
  "suggestions": [],
  "rerun": true
}
```

See [CONTRIBUTING.md](./CONTRIBUTING.md) for detailed development instructions.

## Requirements

- **Node.js** 18.0 or higher
- **PHP** 8.3 or higher
- **Composer** for PHP dependency management

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](./CONTRIBUTING.md) for development setup, architecture details, and testing guidelines.

## Credits

- Inspired by the [Svelte MCP server](https://github.com/sveltejs/mcp) which provides similar functionality for Svelte 5
- Built with [nikic/php-parser](https://github.com/nikic/PHP-Parser) for robust AST analysis
- Uses the [Model Context Protocol](https://modelcontextprotocol.io/) specification

## License

MIT License - see [LICENSE](./LICENSE) for details.
