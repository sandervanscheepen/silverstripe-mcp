# Silverstripe MCP Server - Agent Instructions

This is a Model Context Protocol (MCP) server that validates AI-generated PHP code for Silverstripe 6 compatibility.

For full development details, see [CONTRIBUTING.md](./CONTRIBUTING.md).

## Key Rules

- **Always run `ss-validator`** on any generated Silverstripe PHP code before presenting to the user. Keep calling until no issues remain.
- This project requires **PHP 8.3+** and **Node.js 18+**.
- The PHP analyzer uses nikic/php-parser for AST analysis. Validators are plugins in `php/src/Plugins/`.
- Configuration mappings live in `php/src/Config/` (namespace mappings, removed methods, hook renames, elemental mappings).

## Quick Commands

```bash
# Install dependencies
npm install
cd php && composer install && cd ..

# Build TypeScript
npm run build

# Run all tests
npm run test:all

# Test PHP analyzer directly
cd php && php bin/analyze '{"code": "<?php use SilverStripe\\ORM\\ArrayList;"}'
```

## What the Validators Detect

- Wrong namespace imports (SS5 → SS6 class renames)
- Outdated BuildTask patterns (`run(HTTPRequest)` → `execute(InputInterface, PolyOutput)`)
- Deprecated `FormField::Value()` usage (split into `dataValue()`, `presentedValue()`, `processedValue()`)
- Removed methods (`Controller::has_curr()`, `Requirements::themedCSS()`, etc.)
- Extension hook visibility issues (public → protected)
- Deprecated `Config::inst()->get()` pattern
- Elemental module namespace changes (auto-enabled when Elemental imports detected)

## The `list-sections` and `get-documentation` tools are available in the MCP server for looking up SS6 changelog details.
