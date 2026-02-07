# View Layer Changes in Silverstripe 6

Source: https://docs.silverstripe.org/en/6/changelogs/6.0.0/#view-layer

## Overview

Silverstripe 6 introduces significant changes to the view layer and templating system, improving separation between model and view layers.

## Class Renames

### Primary View/Model Classes

| Old Namespace (SS5) | New Namespace (SS6) |
|---------------------|---------------------|
| `SilverStripe\View\ViewableData` | `SilverStripe\Model\ModelData` |
| `SilverStripe\View\ArrayData` | `SilverStripe\Model\ArrayData` |

### Related List Classes

| Old Namespace (SS5) | New Namespace (SS6) |
|---------------------|---------------------|
| `SilverStripe\ORM\ArrayList` | `SilverStripe\Model\List\ArrayList` |
| `SilverStripe\ORM\PaginatedList` | `SilverStripe\Model\List\PaginatedList` |
| `SilverStripe\ORM\Map` | `SilverStripe\Model\List\Map` |
| `SilverStripe\ORM\GroupedList` | `SilverStripe\Model\List\GroupedList` |

## ViewableData to ModelData

### Why This Change?

In Silverstripe 5, `ViewableData` served dual purposes:
- Base class for all model types
- Wrapper class for template data

The rename to `ModelData` clarifies that it's a model base class, not a view component.

### Migration

```php
// Old
use SilverStripe\View\ViewableData;

class MyModel extends ViewableData
{
    // ...
}

// New
use SilverStripe\Model\ModelData;

class MyModel extends ModelData
{
    // ...
}
```

## Template Engine Architecture

### New TemplateEngine Interface

`SilverStripe\TemplateEngine\TemplateEngine` is the interface for template rendering:

```php
$engine = Injector::inst()->get('TemplateEngine');
echo $engine->renderString('Hello $Name', $data);
```

### SSViewer Changes

SSViewer now delegates to the TemplateEngine:

```php
// Old (removed)
$template = SSViewer::fromString('Hello $Name');
echo $template->process($data);

// New
$engine = Injector::inst()->get('TemplateEngine');
echo $engine->renderString('Hello $Name', $data);
```

### Removed Methods

| Method | Status |
|--------|--------|
| `SSViewer::fromString()` | Removed |
| `SSViewer_FromString` | Removed |
| `SSViewer::chooseTemplate()` | Removed |
| `SSViewer::hasTemplate()` | Moved to TemplateEngine |

## ViewLayerData

New class for template data wrapping:

```php
use SilverStripe\Model\ViewLayerData;

// Get raw value for conditional statements
$rawValue = ViewLayerData::getRawDataValue();
```

### Why ViewLayerData?

- Provides uniform value lookup for templates
- Ensures consistent type handling
- Properly evaluates truthiness

## Template Syntax Changes

### Native PHP Arrays

You can now pass native PHP arrays directly into templates:

```html
<% loop $MyArray %>
    $value
<% end_loop %>

Count: $MyArray.Count
<% if $MyArray %>Has items<% end_if %>
```

### Getter Method Arguments

Getter methods now receive arguments from templates:

```php
public function getFormattedPrice($currencyCode = 'USD')
{
    return '$' . $this->Price . ' ' . $currencyCode;
}
```

```html
$getFormattedPrice('GBP')
```

### Removed i18n Syntax

Old i18n template syntaxes have been removed:

```html
<!-- No longer works -->
<% _t("My_KEY", "Default text") %>
```

## Deprecated Methods

| Method | Status | Replacement |
|--------|--------|-------------|
| `ModelData::XML_val()` | Deprecated | Use getter methods |
| `ModelData::obj()` | Deprecated | Use getter methods |
| `Hierarchy::AllChildrenIncludingDeleted()` | Deprecated | `getChildrenForTree()` |

### Using Getter Methods

```php
// Old approach (deprecated)
public function XML_val($key, $arguments = null)
{
    return parent::XML_val($key, $arguments);
}

// New approach
public function getMyField()
{
    return $this->MyField . ' processed';
}
```

## Configuration Changes

| Old Config | New Config |
|------------|------------|
| `SSViewer.global_key` | `SSTemplateEngine.global_key` |

## Migration Checklist

1. Update class imports:
   ```diff
   - use SilverStripe\View\ViewableData;
   + use SilverStripe\Model\ModelData;

   - use SilverStripe\View\ArrayData;
   + use SilverStripe\Model\ArrayData;

   - use SilverStripe\ORM\ArrayList;
   + use SilverStripe\Model\List\ArrayList;
   ```

2. Update class declarations:
   ```diff
   - class MyModel extends ViewableData {
   + class MyModel extends ModelData {
   ```

3. Replace `SSViewer::fromString()` with TemplateEngine

4. Update i18n template syntax

5. Replace `XML_val()` overrides with getter methods

6. Test template variables and conditionals
