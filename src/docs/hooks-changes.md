# Extension Hook Changes in Silverstripe 6

Source: https://docs.silverstripe.org/en/6/changelogs/6.0.0/#hooks-protected

## Overview

Silverstripe 6 introduces two significant changes to extension hooks:
1. Most extension hook methods are now `protected` instead of `public`
2. Some hook methods have been renamed

## Hook Visibility Changes

Extension hook methods are internal implementation details and should not be part of the public API. Making them protected:
- Prevents accidental external calls
- Allows for better type safety
- Clarifies the intended usage

### Affected Methods

Common hooks that should now be protected:

- `onBeforeWrite()`
- `onAfterWrite()`
- `onBeforeDelete()`
- `onAfterDelete()`
- `updateCMSFields()`
- `updateCMSActions()`
- `augmentSQL()`
- `augmentDatabase()`

### Old Pattern (Silverstripe 5)

```php
<?php

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;

class MyExtension extends DataExtension
{
    public function onBeforeWrite()
    {
        // ...
    }

    public function updateCMSFields(FieldList $fields)
    {
        // ...
    }
}
```

### New Pattern (Silverstripe 6)

```php
<?php

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;

class MyExtension extends Extension
{
    protected function onBeforeWrite(): void
    {
        // ...
    }

    protected function updateCMSFields(FieldList $fields): void
    {
        // ...
    }
}
```

## Hook Renames

Some extension hooks have been renamed for clarity:

| Old Hook Name | New Hook Name | Class |
|---------------|---------------|-------|
| `onAfterRenewToken` | `onAfterRenewSession` | RememberLoginHash |

## Extension Base Class Change

The `DataExtension` class has been removed. Use `Extension` instead:

```php
// Old (Silverstripe 5)
use SilverStripe\ORM\DataExtension;

class MyExtension extends DataExtension
{
    // ...
}

// New (Silverstripe 6)
use SilverStripe\Core\Extension;

class MyExtension extends Extension
{
    // ...
}
```

### Removed Extension Classes

| Removed Class | Replacement |
|---------------|-------------|
| `SilverStripe\ORM\DataExtension` | `SilverStripe\Core\Extension` |
| `SilverStripe\CMS\Model\SiteTreeExtension` | `SilverStripe\Core\Extension` |
| `SilverStripe\Admin\LeftAndMainExtension` | `SilverStripe\Core\Extension` |

## Validation Hook Changes

The validation extension hooks have also changed:

### Old Pattern (Silverstripe 5)

```php
public function updateValidationResult(ValidationResult $result, $validator): void
{
    // Custom validation logic
}
```

### New Pattern (Silverstripe 6)

```php
protected function updateValidate(ValidationResult $result): void
{
    // Hook called with just ValidationResult
}
```

Key changes:
- `extendValidationResult()` method removed
- `updateValidationResult` extension hook removed
- Replaced with `updateValidate()` hook (single ValidationResult parameter)

## Migration Checklist

1. Change extension base class from `DataExtension` to `Extension`
2. Update hook method visibility from `public` to `protected`
3. Add return type hints (`:void` for most hooks)
4. Update any renamed hooks
5. Update validation hooks to use new signature
