# Silverstripe 6 Development Skill

You are skilled in Silverstripe CMS 6 development. Use this knowledge when working with Silverstripe PHP projects.

## Key Silverstripe 6 Changes

Reference: https://docs.silverstripe.org/en/6/changelogs/6.0.0/

### Namespace Changes

Many core classes moved namespaces in SS6:

| Class | Old (SS5) | New (SS6) |
|-------|-----------|-----------|
| ArrayList | `SilverStripe\ORM\ArrayList` | `SilverStripe\Model\List\ArrayList` |
| ArrayData | `SilverStripe\View\ArrayData` | `SilverStripe\Model\ArrayData` |
| ViewableData | `SilverStripe\View\ViewableData` | `SilverStripe\Model\ModelData` |
| PaginatedList | `SilverStripe\ORM\PaginatedList` | `SilverStripe\Model\List\PaginatedList` |
| ValidationResult | `SilverStripe\ORM\ValidationResult` | `SilverStripe\Core\Validation\ValidationResult` |

### BuildTask Pattern

SS6 BuildTasks use Symfony Console:

```php
// SS6 BuildTask pattern
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class MyTask extends BuildTask
{
    protected static string $commandName = 'app:my-task';
    protected static string $description = 'Task description';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $output->writeln('Output goes here');
        return Command::SUCCESS;
    }
}
```

**Do NOT use:**
- `run(HTTPRequest $request)` method
- `echo` for output
- `private static $segment`

### Extension Hooks

Extension hook methods should be `protected`, not `public`:

```php
// Correct in SS6
protected function updateCMSFields(FieldList $fields): void
protected function onBeforeWrite(): void
protected function onAfterWrite(): void
```

## MCP Tools Available

When the Silverstripe MCP server is connected, use these tools:

1. **ss-validator** - ALWAYS validate PHP code before presenting to user
2. **list-sections** - Discover available SS6 changelog sections
3. **get-documentation** - Fetch specific changelog sections

## Validation Loop

When writing Silverstripe PHP code:

1. Write the code
2. Call `ss-validator` with the code
3. If issues found, fix them and re-validate
4. Repeat until no issues remain
5. Present code to user
