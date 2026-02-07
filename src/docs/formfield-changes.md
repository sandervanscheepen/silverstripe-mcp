# FormField Changes in Silverstripe 6

Source: https://docs.silverstripe.org/en/6/changelogs/6.0.0/

## Overview

Silverstripe 6 introduces significant changes to FormField handling, validation, and value management.

## 1. Value Handling Changes

### Value() Method Split

The `FormField::Value()` method has been split into two methods:

#### getValue()

Returns the unmodified raw value:

```php
$rawValue = $field->getValue();
```

#### getFormattedValue()

Returns the value formatted for display:

```php
$displayValue = $field->getFormattedValue();
```

### Migration

```php
// Silverstripe 5
public function Value()
{
    return $this->formatValue($this->value);
}

// Silverstripe 6
public function getValue()
{
    return $this->value;
}

public function getFormattedValue()
{
    return $this->formatValue($this->value);
}
```

### Template Changes

```html
<!-- Old -->
<p>Your value: {$Value}</p>

<!-- New -->
<p>Your value: {$FormattedValue}</p>
```

## 2. Validation Changes

### Changed validate() Signature

**Silverstripe 5:**

```php
public function validate($validator): bool
{
    if ($invalid) {
        return false;
    }
    return true;
}
```

**Silverstripe 6:**

```php
public function validate(): ValidationResult
{
    $result = ValidationResult::create();
    if ($invalid) {
        $result->addFieldError($this->getName(), 'Error message');
    }
    return $result;
}
```

### Extension Hook Changes

**Silverstripe 5:**

```php
public function updateValidationResult(ValidationResult $result, $validator): void
{
    // Custom validation
}
```

**Silverstripe 6:**

```php
protected function updateValidate(ValidationResult $result): void
{
    // Custom validation
}
```

### New FieldValidator System

Configure validators via `field_validators`:

```php
use SilverStripe\Core\Validation\FieldValidation\EmailFieldValidator;

CustomField::config()->field_validators = [
    EmailFieldValidator::class,
];
```

## 3. Validator Class Renames

| Old Name (SS5) | New Name (SS6) |
|----------------|----------------|
| `RequiredFields` | `RequiredFieldsValidator` |
| `PasswordValidator` | `RulesPasswordValidator` |
| `FieldsValidator` | `FormFieldsValidator` |

### Using Validators

```php
// Old
use SilverStripe\Forms\RequiredFields;
$form->setValidator(new RequiredFields('Email', 'Password'));

// New
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
$form->setValidator(new RequiredFieldsValidator('Email', 'Password'));
```

### Composite Validator

```php
use SilverStripe\Forms\Validation\CompositeValidator;
use SilverStripe\Forms\Validation\FormFieldsValidator;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;

$validator = CompositeValidator::create()
    ->addValidator(FormFieldsValidator::create())
    ->addValidator(RequiredFieldsValidator::create(['Email', 'Password']));

$form->setValidator($validator);
```

## 4. Specific Field Type Changes

### EmailField

- Now uses `symfony/validator` for RFC 2822 compliant validation
- More strict than previous regex validation

### CheckboxField / DBBoolean

- Strict value handling - no longer auto-converts arbitrary strings
- Valid boolean-like values: `true`, `false`, `"1"`, `"0"`, `"t"`, `"f"`

```php
$field->setValue("Amazing");  // Fails validation!
$field->setValue("1");        // OK
$field->setValue(true);       // OK
```

### NumericField / DBCurrency

- Only fully numeric strings are parsed
- Currency symbols no longer stripped automatically

```php
$field->setValue("$100.50");  // Fails validation!
$field->setValue("100.50");   // OK
```

## 5. Scaffolded Field Changes

### SiteTree::getCMSFields()

SiteTree now uses standard scaffolding (calls `parent::getCMSFields()`). May auto-add relation fields.

### Default Scaffolded Fields

| Relation Type | Default Field |
|---------------|---------------|
| has_one to File | UploadField |
| has_one (other) | SearchableDropdownField |
| has_many | GridField |
| many_many | GridField |

### Custom Scaffolding Methods

```php
protected function scaffoldFormFieldForHasOne(
    string $fieldName,
    string|null $fieldTitle,
    string $relationName,
    DataObject $ownerRecord
): FormField {
    // Custom has_one field scaffolding
}

protected function scaffoldFormFieldForHasMany(
    string $relationName,
    string|null $fieldTitle,
    DataObject $ownerRecord,
    bool $includeInOwnTab
): FormField {
    // Custom has_many field scaffolding
}
```

## 6. Schema Data Changes

### getSchemaDataDefaults() Now Calls getAttributes()

Avoid infinite recursion - don't call `getSchemaData()` inside `getAttributes()`:

```php
// BAD - causes infinite recursion
public function getAttributes()
{
    $attributes = parent::getAttributes();
    $attributes['data-schema'] = json_encode($this->getSchemaData());
    return $attributes;
}

// GOOD - use template instead
// {$SchemaAttributesHtml}
```

## Migration Checklist

1. Update `Value()` to `getValue()` / `getFormattedValue()`
2. Update `validate()` to return `ValidationResult`
3. Rename validator classes (`RequiredFields` -> `RequiredFieldsValidator`)
4. Update validation extension hooks
5. Ensure boolean/numeric values use correct types
6. Test email validation (now stricter)
7. Review scaffolded CMS fields for SiteTree subclasses
