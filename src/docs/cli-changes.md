# Changes to BuildTask and CLI in Silverstripe 6

Source: https://docs.silverstripe.org/en/6/changelogs/6.0.0/#cli-changes

BuildTask has been completely redesigned to use Symfony Console.

## Old Pattern (Silverstripe 5)

```php
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;

class MyTask extends BuildTask
{
    private static $segment = 'my-task';
    protected $title = 'My Task';
    protected $description = 'Does something';

    public function run(HTTPRequest $request)
    {
        echo "Running...\n";
    }
}
```

## New Pattern (Silverstripe 6)

```php
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class MyTask extends BuildTask
{
    protected static string $commandName = 'app:my-task';
    protected static string $description = 'Does something';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $output->writeln('Running...');
        return Command::SUCCESS;
    }
}
```

## Key Changes

1. **Method signature:** `run(HTTPRequest)` -> `execute(InputInterface, PolyOutput): int`
2. **Command name:** `$segment` -> `$commandName` (static string property)
3. **Output:** `echo` -> `$output->writeln()`
4. **Return value:** Must return `Command::SUCCESS` or `Command::FAILURE`
5. **Properties:** `$title` and `$description` are now static

## Running Tasks

```bash
# Old way (deprecated but still works)
sake dev/tasks/my-task

# New way
sake app:my-task

# Or via HTTP
/dev/tasks/app:my-task
```

## Input Parameters

Old way using `$request->getVar()`:
```php
public function run(HTTPRequest $request)
{
    $limit = $request->getVar('limit');
}
```

New way using Symfony Console InputOption:
```php
use Symfony\Component\Console\Input\InputOption;

public function getOptions(): array
{
    return [
        new InputOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit results'),
    ];
}

protected function execute(InputInterface $input, PolyOutput $output): int
{
    $limit = $input->getOption('limit');
    return Command::SUCCESS;
}
```
