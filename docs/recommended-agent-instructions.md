# Recommended Agent Instructions for Silverstripe Projects

Add the following to your AI coding agent's project instructions file (e.g., `CLAUDE.md` for Claude Code, `.cursorrules` for Cursor, `.github/copilot-instructions.md` for Copilot) to get the most out of the Silverstripe MCP server.

---

## Full Version

```markdown
## Silverstripe 6 Development

This project uses Silverstripe 6. You have access to the Silverstripe MCP server which provides real-time validation for PHP code.

### Available MCP Tools:

#### ss-validator

Analyzes PHP code for Silverstripe 6 compatibility issues. Detects:
- Wrong namespace imports (SS5 → SS6 migrations)
- Deprecated BuildTask patterns
- Missing required properties
- Incorrect method signatures

**IMPORTANT:** You MUST use this tool whenever writing or modifying Silverstripe PHP code before presenting it to the user. Keep calling it until no issues are returned.

Example workflow:
1. Write the PHP code
2. Call `ss-validator` with the code
3. If issues are returned, fix them
4. Call `ss-validator` again
5. Repeat until no issues remain
6. Present the clean code to the user

### Common SS5 → SS6 Migrations

The validator will catch these, but be aware of common changes:

| SS5 | SS6 |
|-----|-----|
| `SilverStripe\ORM\ArrayList` | `SilverStripe\Model\List\ArrayList` |
| `SilverStripe\View\ArrayData` | `SilverStripe\Model\ArrayData` |
| `SilverStripe\View\ViewableData` | `SilverStripe\Model\ModelData` |
| `BuildTask::run(HTTPRequest)` | `BuildTask::execute(InputInterface, PolyOutput): int` |
| `echo` in BuildTask | `$output->writeln()` |

### BuildTask Pattern (SS6)

Always use this pattern for BuildTask classes:

```php
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class MyTask extends BuildTask
{
    protected static string $commandName = 'app:my-task';

    private static string $description = 'Description of what this task does';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $output->writeln('Processing...');
        return Command::SUCCESS;
    }
}
```
```

---

## Minimal Version

If you prefer a shorter version:

```markdown
## Silverstripe MCP

You have access to the `ss-validator` MCP tool. You MUST call this tool on any PHP code you write for this Silverstripe 6 project. Keep calling it until no issues are returned. The tool detects SS5→SS6 migration issues including wrong namespaces, deprecated BuildTask patterns, and missing properties.
```

---

## With Project-Specific Additions

Customize for your project:

```markdown
## Silverstripe MCP

You have access to the `ss-validator` MCP tool. You MUST call this tool on any PHP code you write before presenting it. Keep calling until no issues remain.

### Project-Specific Patterns

- All BuildTask commands use the `app:` prefix (e.g., `app:import-users`)
- Extensions go in `app/src/Extensions/`
- Page types go in `app/src/PageTypes/`
- Use `$output->writeln()` for task output, never `echo`
```
