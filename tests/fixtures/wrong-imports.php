<?php

namespace App;

use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\ViewableData;
use SilverStripe\ORM\PaginatedList;

class MyClass extends ViewableData
{
    public function getData(): ArrayList
    {
        return ArrayList::create([
            ArrayData::create(['Title' => 'Test']),
        ]);
    }
}
