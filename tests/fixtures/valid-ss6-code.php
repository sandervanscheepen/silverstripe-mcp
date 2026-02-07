<?php

namespace App\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class ValidTask extends BuildTask
{
    protected static string $commandName = 'app:valid-task';

    protected static string $description = 'A valid SS6 BuildTask';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $list = ArrayList::create();
        $output->writeln('Task completed');
        return Command::SUCCESS;
    }
}
