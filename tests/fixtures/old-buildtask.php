<?php

namespace App\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;

class OldTask extends BuildTask
{
    private static $segment = 'old-task';

    protected $title = 'Old Task';

    protected $description = 'An old-style BuildTask';

    public function run(HTTPRequest $request)
    {
        echo "Starting task...\n";

        // Do some work
        for ($i = 0; $i < 10; $i++) {
            echo "Processing item $i\n";
        }

        echo "Task completed!\n";
    }
}
