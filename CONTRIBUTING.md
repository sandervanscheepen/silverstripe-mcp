# Contributing

Contributions are welcome! Here are some ways to help:

1. **Add namespace mappings** - Submit PRs to expand `php/src/Config/namespace-mappings.php`
2. **Create new validators** - Build plugins for other common SS5→SS6 migration issues
3. **Report issues** - Found a false positive or missed pattern? Open an issue
4. **Improve documentation** - Help others get started

## Development Setup

```bash
git clone https://github.com/sandervanscheepen/silverstripe-mcp
cd silverstripe-mcp

# Install dependencies
npm install
cd php && composer install && cd ..

# Build TypeScript
npm run build
```

## Development Commands

```bash
# Build and watch for changes
npm run dev

# Run PHP tests (PHPUnit)
cd php && composer test

# Run TypeScript tests (Vitest)
npm test

# Run all tests
npm run test:all

# Watch mode for development
npm run test:watch

# Test PHP analyzer directly
cd php && php bin/analyze '{"code": "<?php use SilverStripe\\ORM\\ArrayList;"}'
```

## Architecture

See [docs/architecture.md](./docs/architecture.md) for the full design. Key points:

- **TypeScript MCP server** using stdio transport
- **PHP analyzer subprocess** using nikic/php-parser for AST analysis
- **Plugin-based validators** - each concern is a separate plugin implementing `ValidatorPluginInterface`
- **JSON configuration** via `silverstripe-mcp.json` in project root

## Project Structure

```
silverstripe-mcp/
├── package.json
├── tsconfig.json
├── src/                            # TypeScript MCP server
│   ├── index.ts                    # Entry point, stdio transport
│   ├── tools/
│   │   └── ss-validator.ts         # Main validation tool
│   └── lib/
│       └── php-bridge.ts           # Spawns PHP subprocess
│
├── php/                            # PHP analyzer
│   ├── composer.json
│   ├── bin/
│   │   └── analyze                 # CLI entry: php bin/analyze
│   └── src/
│       ├── AnalyzerRunner.php      # Loads plugins, runs analysis
│       ├── AnalysisContext.php     # Shared state for plugins
│       ├── AnalysisResult.php      # Return structure
│       ├── Issue.php               # Single issue found
│       ├── Suggestion.php          # Optional improvement suggestion
│       ├── Contracts/
│       │   └── ValidatorPluginInterface.php
│       ├── Plugins/
│       │   ├── NamespaceValidatorPlugin.php      # Core: SS5→SS6 namespace imports
│       │   ├── BuildTaskValidatorPlugin.php      # Core: BuildTask patterns
│       │   ├── FormFieldValuePlugin.php          # Core: FormField::Value() deprecation
│       │   ├── RemovedMethodPlugin.php           # Core: Removed method calls
│       │   ├── HookRenamePlugin.php              # Core: Renamed extension hooks
│       │   ├── DeprecatedConfigPlugin.php        # Core: Config::inst()->get()
│       │   ├── ExtensionHookVisibilityPlugin.php # Auto: Extension hook visibility
│       │   └── ElementalNamespacePlugin.php      # Auto: Elemental module
│       └── Config/
│           ├── namespace-mappings.php    # SS5→SS6 class mappings
│           ├── removed-methods.php       # Removed method definitions
│           ├── hook-renames.php          # Renamed hook definitions
│           └── elemental-mappings.php    # Elemental module mappings
│
├── docs/
│   └── architecture.md
├── tests/                        # Vitest tests
├── CLAUDE.md                     # AI agent instructions
├── CONTRIBUTING.md               # This file
└── README.md
```

## Key Implementation Details

### TypeScript MCP Server (src/index.ts)

Uses the `@modelcontextprotocol/sdk` package. Registers one tool:

- **ss-validator**: Receives PHP code, calls PHP subprocess, returns issues

```typescript
// Tool schema
{
  name: "ss-validator",
  description: "Analyzes PHP code for Silverstripe 6 compatibility issues.",
  inputSchema: {
    type: "object",
    properties: {
      code: { type: "string", description: "The PHP code to analyze" },
      filename: { type: "string", description: "Optional filename for context" },
      targetVersion: { type: "string", default: "6.0" },
      enableAllPlugins: { type: "boolean", default: false }
    },
    required: ["code"]
  }
}
```

### PHP Bridge (src/lib/php-bridge.ts)

Spawns PHP analyzer subprocess with JSON input via argv, reads JSON output from stdout.

**PHP Binary Resolution** (in priority order):
1. `phpBinary` from `silverstripe-mcp.json` config
2. `PHP_BINARY` environment variable (if version >= 8.3)
3. Auto-detect from common locations (Laragon, XAMPP, Homebrew, system paths)
4. Fall back to `php` command (if version >= 8.3)
5. Throw error with helpful suggestions if no suitable PHP found

Input format:
```json
{
  "code": "<?php ...",
  "filename": "MyTask.php",
  "targetVersion": "6.0",
  "enableAllPlugins": false,
  "configPath": "./silverstripe-mcp.json"
}
```

Output format:
```json
{
  "issues": [
    {
      "type": "deprecated_import",
      "message": "...",
      "line": 5,
      "column": null,
      "code": "use SilverStripe\\ORM\\ArrayList;",
      "suggestion": "use SilverStripe\\Model\\List\\ArrayList;",
      "docsUrl": "https://docs.silverstripe.org/en/6/changelogs/6.0.0/#renamed-classes"
    }
  ],
  "suggestions": [],
  "rerun": false
}
```

### Plugin Interface

All plugins implement `ValidatorPluginInterface`:

```php
interface ValidatorPluginInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function getTargetVersions(): array;
    public function getVisitor(AnalysisContext $context): \PhpParser\NodeVisitor;
    public function configure(array $options): void;
}
```

### Plugin Categories

**Core plugins** (always run):
- `namespace-validator` - SS5→SS6 namespace imports
- `buildtask-validator` - BuildTask patterns
- `formfield-value-validator` - FormField::Value() deprecation
- `removed-method-validator` - Removed method calls
- `hook-rename-validator` - Renamed extension hooks
- `deprecated-config` - Config::inst()->get() deprecation

**Auto plugins** (context-aware, run when relevant code detected):
- `extension-hook-visibility` - Triggers when class extends Extension/DataExtension/SiteTreeExtension
- `elemental-namespace` - Triggers when any import starts with `DNADesign\Elemental`

Auto plugins can be force-enabled via `enableAllPlugins: true` or by setting `enabled: true` for the specific plugin.

## Testing

When implementing, test with these scenarios:

### Test 1: Wrong ArrayList import
```php
<?php
use SilverStripe\ORM\ArrayList;

class MyClass {
    public function getData(): ArrayList {
        return ArrayList::create();
    }
}
```
Expected: Issue on line 2, suggesting `SilverStripe\Model\List\ArrayList`

### Test 2: Old BuildTask pattern
```php
<?php
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;

class MyTask extends BuildTask {
    private static $segment = 'my-task';

    public function run(HTTPRequest $request) {
        echo "Running task...\n";
    }
}
```
Expected: Multiple issues:
- `run(HTTPRequest)` is deprecated
- Missing `$commandName` static property
- `echo` should be `$output->writeln()`

### Test 3: Correct SS6 BuildTask
```php
<?php
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class MyTask extends BuildTask {
    protected static string $commandName = 'my-task';

    protected function execute(InputInterface $input, PolyOutput $output): int {
        $output->writeln('Running task...');
        return Command::SUCCESS;
    }
}
```
Expected: No issues

### Test 4: FormField::Value() deprecation
```php
<?php
$value = $field->Value();
```
Expected: Issue suggesting `dataValue()`, `presentedValue()`, or `processedValue()`

### Test 5: Removed methods
```php
<?php
if (Controller::has_curr()) {
    $controller = Controller::curr();
}
```
Expected: Issue for `Controller::has_curr()` being removed

### Test 6: Elemental namespace (requires enabling plugin)
```php
<?php
use DNADesign\Elemental\TopPage\DataExtension;
```
Expected: Issue suggesting `DNADesign\Elemental\Extensions\TopPageElementExtension`

## Dependencies

### TypeScript (package.json)
- `@modelcontextprotocol/sdk` - MCP server implementation
- `typescript` - Build tooling

### PHP (composer.json)
- `nikic/php-parser` ^5.0 - AST parsing
- PHP 8.3+ (matches SS6 requirements)

## Error Handling

- If PHP subprocess fails, return error in MCP response
- If code can't be parsed, return parse error with line number
- If config file is invalid, use defaults and log warning
