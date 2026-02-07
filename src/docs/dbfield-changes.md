# DBField Validation Changes in Silverstripe 6

Source: https://docs.silverstripe.org/en/6/changelogs/6.0.0/#dbfield-validation

## Overview

Silverstripe 6 introduces strict validation for DBField classes. When a value is set on a DBField subclass, it is now validated against the constraints of that field. If a value is invalid, a `ValidationException` is thrown.

This is a **breaking change** from Silverstripe 5, where invalid values would be silently truncated or coerced.

## Key Changes

### 1. Strict Type Enforcement

Previously, DBField classes would accept arbitrary types and attempt to coerce them. In Silverstripe 6, the correct scalar type must be used:

- **DBVarchar**: Must receive a string (not an integer)
- **DBInt**: Must receive an integer (not a string representation)
- **DBBoolean**: Must receive a boolean

```php
<?php

use SilverStripe\ORM\DataObject;

class MyObject extends DataObject
{
    private static $db = [
        'MyTitle' => 'Varchar(255)',
        'MyCount' => 'Int',
    ];
}

$obj = new MyObject();
$obj->MyTitle = 123;   // Previously coerced, now throws ValidationException
$obj->MyCount = "456"; // Previously coerced, now throws ValidationException
$obj->write();
```

### 2. Field Length Validation

String fields now enforce their specified length constraints:

```php
<?php

$obj = new MyObject();
$obj->MyTitle = str_repeat('x', 300); // Exceeds Varchar(255)
$obj->write(); // Throws ValidationException
```

### 3. New FieldValidator Architecture

Validation is implemented through `FieldValidator` classes:

- **StringFieldValidator** - validates `DBVarchar`, `DBText`, etc.
- **EmailFieldValidator** - validates `DBEmail` fields
- **UrlFieldValidator** - validates `DBUrl` fields
- **IpFieldValidator** - validates `DBIp` fields
- **IntFieldValidator** - validates `DBInt` fields
- **BooleanFieldValidator** - validates `DBBoolean` fields
- **DecimalFieldValidator** - validates `DBDecimal` fields

## New DBField Types

Silverstripe 6 introduces new DBField types with built-in validation:

### DBEmail

Validates email addresses using Symfony Validator:

```php
<?php

private static $db = [
    'EmailAddress' => 'Email',
];

$obj->EmailAddress = 'invalid-email'; // Throws ValidationException
$obj->EmailAddress = 'user@example.com'; // Valid
```

### DBUrl

Validates URL format:

```php
<?php

private static $db = [
    'WebsiteUrl' => 'Url',
];

$obj->WebsiteUrl = 'not-a-url'; // Throws ValidationException
$obj->WebsiteUrl = 'https://example.com'; // Valid
```

### DBIp

Validates IP addresses (IPv4 or IPv6):

```php
<?php

private static $db = [
    'IpAddress' => 'Ip',
];

$obj->IpAddress = '999.999.999.999'; // Throws ValidationException
$obj->IpAddress = '192.168.1.1'; // Valid
$obj->IpAddress = '::1'; // Valid IPv6
```

## Type Conversion Behavior

Some DBField subclasses retain type conversion for common patterns:

### DBBoolean

Converts common boolean-like values:

```
true   -> 1
'true' -> 1
't'    -> 1
'1'    -> 1
false  -> 0
'false'-> 0
'f'    -> 0
'0'    -> 0
```

### DBInt

Converts integer-like strings:

```
'123' -> 123 (int)
```

## Handling Validation Errors

### Catching ValidationException

```php
<?php

use SilverStripe\Core\Validation\ValidationException;

try {
    $obj = new MyObject();
    $obj->MyTitle = str_repeat('x', 300);
    $obj->write();
} catch (ValidationException $e) {
    error_log($e->getMessage());
}
```

### Using ValidationResult

```php
<?php

$obj = new MyObject();
$obj->MyTitle = str_repeat('x', 300);

$result = $obj->validate();

if (!$result->isValid()) {
    foreach ($result->getMessages() as $message) {
        echo $message['fieldName'] . ': ' . $message['message'];
    }
}
```

## Custom Validation

Override `validate()` in your DataObject:

```php
<?php

use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Validation\ValidationResult;

class Product extends DataObject
{
    private static $db = [
        'SKU' => 'Varchar(50)',
        'Price' => 'Decimal(10,2)',
    ];

    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if (empty($this->SKU)) {
            $result->addFieldError('SKU', 'SKU is required');
        }

        if ($this->Price < 0) {
            $result->addFieldError('Price', 'Price cannot be negative');
        }

        return $result;
    }
}
```

## Migration Guide

1. **Validate Before Writing**: Use try-catch around `write()` operations
2. **Use Proper Types**: Cast values to correct types before assignment
3. **Leverage New Field Types**: Use `DBEmail`, `DBUrl`, `DBIp` for validated data
4. **Implement Custom Validation**: Override `validate()` for business rules

## Summary

| Aspect | Silverstripe 5 | Silverstripe 6 |
|--------|----------------|----------------|
| Type Enforcement | Loose (auto-coerce) | Strict (exceptions) |
| Length Validation | Silent truncation | ValidationException |
| Email Validation | Form layer only | DBField layer enforced |
| URL Validation | Not available | DBUrl field type |
| IP Validation | Not available | DBIp field type |
