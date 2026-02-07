# Renamed and Moved Classes in Silverstripe 6

Source: https://docs.silverstripe.org/en/6/changelogs/6.0.0/#renamed-classes

Many classes have been renamed or moved to new namespaces. Update your `use` statements accordingly.

## List Classes (SilverStripe\ORM -> SilverStripe\Model\List)

| Old Namespace | New Namespace |
|---------------|---------------|
| `SilverStripe\ORM\ArrayList` | `SilverStripe\Model\List\ArrayList` |
| `SilverStripe\ORM\PaginatedList` | `SilverStripe\Model\List\PaginatedList` |
| `SilverStripe\ORM\Map` | `SilverStripe\Model\List\Map` |
| `SilverStripe\ORM\GroupedList` | `SilverStripe\Model\List\GroupedList` |

## View/Model Classes

| Old Namespace | New Namespace |
|---------------|---------------|
| `SilverStripe\View\ArrayData` | `SilverStripe\Model\ArrayData` |
| `SilverStripe\View\ViewableData` | `SilverStripe\Model\ModelData` |

## Validation Classes (SilverStripe\ORM -> SilverStripe\Core\Validation)

| Old Namespace | New Namespace |
|---------------|---------------|
| `SilverStripe\ORM\ValidationResult` | `SilverStripe\Core\Validation\ValidationResult` |
| `SilverStripe\ORM\ValidationException` | `SilverStripe\Core\Validation\ValidationException` |

## Why These Changes?

The change from `ViewableData` to `ModelData` was made to improve separation between the model layer and view layer. Similarly, list classes were moved from `ORM` to `Model\List` to better reflect their purpose.
