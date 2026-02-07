# Silverstripe MCP Server - Architecture

This document describes the technical architecture of the Silverstripe MCP Server.

## Overview

The Silverstripe MCP Server is a [Model Context Protocol](https://modelcontextprotocol.io/) server that provides real-time code validation feedback for Silverstripe 6 PHP code. It helps AI assistants generate correct code by catching common migration issues from Silverstripe 5 to 6.

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         MCP Server (TypeScript)                         │
│                         Transport: stdio                                │
├─────────────────────────────────────────────────────────────────────────┤
│  Tools:                                                                 │
│  └── ss-validator    → Validate PHP code for SS6 patterns              │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
                                │ JSON via stdin/stdout
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        PHP Analyzer (subprocess)                        │
├─────────────────────────────────────────────────────────────────────────┤
│  Entry: php/bin/analyze                                                 │
│                                                                         │
│  1. Receives code + options as JSON                                     │
│  2. Parses PHP → AST using nikic/php-parser                            │
│  3. Loads enabled validator plugins                                     │
│  4. Runs each plugin's NodeVisitor against the AST                     │
│  5. Aggregates issues and suggestions                                   │
│  6. Returns JSON result                                                 │
└─────────────────────────────────────────────────────────────────────────┘
```

## Data Flow

```
┌──────────────────┐
│ AI generates     │
│ PHP code         │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ ss-validator     │
│ tool called      │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ TypeScript MCP   │
│ spawns PHP       │
│ subprocess       │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│ PHP Analyzer     │
│ parses AST       │
└────────┬─────────┘
         │
         ├──────────────────┬──────────────────┐
         ▼                  ▼                  ▼
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│ Namespace        │ │ BuildTask        │ │ Custom           │
│ Plugin           │ │ Plugin           │ │ Plugins...       │
└────────┬─────────┘ └────────┬─────────┘ └────────┬─────────┘
         │                    │                    │
         └────────────────────┼────────────────────┘
                              ▼
                    ┌──────────────────┐
                    │ Aggregate        │
                    │ Results          │
                    └────────┬─────────┘
                             │
                             ▼
                    ┌──────────────────┐
                    │ Return JSON      │
                    │ to MCP server    │
                    └────────┬─────────┘
                             │
                             ▼
                    ┌──────────────────┐
                    │ AI sees issues   │
                    │ and iterates     │
                    └──────────────────┘
```

## Components

### TypeScript MCP Server (`src/`)

The MCP server is built with the `@modelcontextprotocol/sdk` package and uses stdio transport for communication.

**Key Files:**

| File | Purpose |
|------|---------|
| `src/index.ts` | Server entry point, tool registration |
| `src/tools/ss-validator.ts` | Validation tool implementation |
| `src/lib/php-bridge.ts` | PHP subprocess communication |

**Tool Definition:**

```typescript
{
  name: "ss-validator",
  description: "Analyzes PHP code for Silverstripe 6 compatibility issues.",
  inputSchema: {
    type: "object",
    properties: {
      code: { type: "string", description: "The PHP code to analyze" },
      filename: { type: "string", description: "Filename for context" },
      targetVersion: { type: "string", default: "6.0" },
      enableAllPlugins: { type: "boolean", default: false, description: "Force enable all plugins" }
    },
    required: ["code"]
  }
}
```

### PHP Analyzer (`php/`)

The PHP analyzer uses [nikic/php-parser](https://github.com/nikic/PHP-Parser) for robust AST parsing and analysis.

**Key Files:**

| File | Purpose |
|------|---------|
| `bin/analyze` | CLI entry point |
| `src/AnalyzerRunner.php` | Plugin orchestration |
| `src/AnalysisContext.php` | Shared state for plugins |
| `src/Issue.php` | Issue data structure |
| `src/Suggestion.php` | Suggestion data structure |
| `src/AnalysisResult.php` | Aggregated results |

### Plugin System

All validator plugins implement `ValidatorPluginInterface`:

```php
interface ValidatorPluginInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function getTargetVersions(): array;
    public function getVisitor(AnalysisContext $context): NodeVisitor;
    public function configure(array $options): void;
}
```

**Core Plugins (always run):**

| Plugin | Purpose |
|--------|---------|
| `NamespaceValidatorPlugin` | Detects outdated SS5 namespace imports |
| `BuildTaskValidatorPlugin` | Detects old BuildTask patterns |
| `FormFieldValuePlugin` | Detects deprecated `FormField::Value()` usage |
| `RemovedMethodPlugin` | Detects calls to methods removed in SS6 |
| `HookRenamePlugin` | Detects renamed extension hooks |
| `DeprecatedConfigPlugin` | Detects `Config::inst()->get()` usage |

**Auto Plugins (context-aware, run when relevant code detected):**

| Plugin | Trigger | Purpose |
|--------|---------|---------|
| `ExtensionHookVisibilityPlugin` | Class extends Extension/DataExtension/SiteTreeExtension | Detects public extension hooks that should be protected |
| `ElementalNamespacePlugin` | Import starts with `DNADesign\Elemental` | Detects outdated Elemental module namespaces |

Auto plugins can be force-enabled via `enableAllPlugins: true` in config or tool args, or by setting `enabled: true` for the specific plugin.

### Communication Protocol

**Input (JSON):**

```json
{
  "code": "<?php use SilverStripe\\ORM\\ArrayList;",
  "filename": "MyClass.php",
  "targetVersion": "6.0",
  "enableAllPlugins": false,
  "configPath": "/path/to/silverstripe-mcp.json"
}
```

**Output (JSON):**

```json
{
  "issues": [
    {
      "type": "deprecated_import",
      "message": "'SilverStripe\\ORM\\ArrayList' has moved to...",
      "line": 1,
      "column": null,
      "code": "use SilverStripe\\ORM\\ArrayList;",
      "suggestion": "use SilverStripe\\Model\\List\\ArrayList;",
      "docsUrl": "https://docs.silverstripe.org/..."
    }
  ],
  "suggestions": [],
  "rerun": true
}
```

## Issue Types

| Type | Description | Plugin |
|------|-------------|--------|
| `parse_error` | PHP syntax error | Core |
| `deprecated_import` | Outdated namespace import | NamespaceValidator |
| `deprecated_buildtask_signature` | Old `run(HTTPRequest)` method | BuildTaskValidator |
| `missing_command_name` | Missing `$commandName` property | BuildTaskValidator |
| `deprecated_echo` | Using `echo` in BuildTask | BuildTaskValidator |
| `deprecated_print` | Using `print` in BuildTask | BuildTaskValidator |
| `deprecated_formfield_value` | Using deprecated `FormField::Value()` | FormFieldValue |
| `removed_method` | Calling a method removed in SS6 | RemovedMethod |
| `renamed_hook` | Using an extension hook that was renamed | HookRename |
| `deprecated_config_api` | Using `Config::inst()->get()` | DeprecatedConfig |
| `hook_visibility` | Public extension hook should be protected | ExtensionHookVisibility |
| `deprecated_elemental_import` | Outdated Elemental namespace | ElementalNamespace |
| `removed_elemental_class` | Elemental class removed without replacement | ElementalNamespace |

## Configuration

The analyzer supports configuration via `silverstripe-mcp.json`:

```json
{
  "targetVersion": "6.0",
  "enableAllPlugins": false,
  "plugins": {
    "namespace-validator": {
      "enabled": true,
      "additionalMappings": {
        "Old\\Namespace": "New\\Namespace"
      }
    },
    "buildtask-validator": {
      "enabled": true
    },
    "extension-hook-visibility": {
      "enabled": true
    }
  },
  "customPlugins": [
    "./path/to/CustomPlugin.php"
  ]
}
```

**Key Options:**
- `enableAllPlugins`: Force-enable all plugins including context-aware ones
- `plugins.*.enabled`: Force-enable or disable individual plugins

## Version Matching

Plugins declare which Silverstripe versions they apply to:

```php
public function getTargetVersions(): array
{
    return ['6.*']; // Matches 6.0, 6.1, etc.
}
```

**Matching Rules:**

| Pattern | Matches |
|---------|---------|
| `6.0` | Exact match only |
| `6.*` | Any 6.x version |
| `*` | All versions |

## Testing

The project includes comprehensive test suites:

**PHP Tests (PHPUnit):**
- `tests/AnalyzerRunnerTest.php` - Core analyzer functionality
- `tests/Plugins/NamespaceValidatorPluginTest.php` - Namespace validation
- `tests/Plugins/BuildTaskValidatorPluginTest.php` - BuildTask validation

**TypeScript Tests (Vitest):**
- `tests/php-bridge.test.ts` - PHP subprocess communication
- `tests/ss-validator.test.ts` - Tool integration tests

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Invalid PHP syntax | Returns `parse_error` issue with line number |
| PHP subprocess fails | Returns error in MCP response |
| Missing config file | Uses defaults, continues normally |
| Missing custom plugin | Skips plugin silently |

## Design Decisions

1. **PHP subprocess over WASM** - Using a real PHP process ensures accurate parsing and allows leveraging the full php-parser library.

2. **Plugin-based architecture** - Separating validators into plugins allows easy extension and optional validators.

3. **AST-based analysis** - Using Abstract Syntax Tree analysis provides accurate line numbers and understands PHP structure.

4. **Curated mappings** - Rather than mapping all possible classes, we focus on high-frequency migration issues to keep the tool fast and focused.

5. **JSON communication** - Simple, language-agnostic protocol between TypeScript and PHP.

6. **Context-aware plugin activation** - Auto plugins only run when relevant code is detected (e.g., Extension plugin only runs for Extension classes), minimizing overhead and token usage while still catching all relevant issues.
