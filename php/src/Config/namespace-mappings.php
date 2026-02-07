<?php

declare(strict_types=1);

/**
 * Silverstripe 5 to 6 namespace mappings.
 *
 * Maps old (SS5) class paths to their new (SS6) locations.
 * These mappings are used to detect deprecated imports and suggest corrections.
 *
 * Source: https://docs.silverstripe.org/en/6/changelogs/6.0.0/#renamed-classes
 */
return [
    // ===== LIST CLASSES (SilverStripe\ORM -> SilverStripe\Model\List) =====
    'SilverStripe\\ORM\\ArrayList' => 'SilverStripe\\Model\\List\\ArrayList',
    'SilverStripe\\ORM\\PaginatedList' => 'SilverStripe\\Model\\List\\PaginatedList',
    'SilverStripe\\ORM\\Map' => 'SilverStripe\\Model\\List\\Map',
    'SilverStripe\\ORM\\GroupedList' => 'SilverStripe\\Model\\List\\GroupedList',
    'SilverStripe\\ORM\\SS_List' => 'SilverStripe\\Model\\List\\SS_List',

    // ===== VIEW/MODEL CLASSES (SilverStripe\View -> SilverStripe\Model) =====
    'SilverStripe\\View\\ArrayData' => 'SilverStripe\\Model\\ArrayData',
    'SilverStripe\\View\\ViewableData' => 'SilverStripe\\Model\\ModelData',

    // ===== VALIDATION CLASSES (SilverStripe\ORM -> SilverStripe\Core\Validation) =====
    'SilverStripe\\ORM\\ValidationResult' => 'SilverStripe\\Core\\Validation\\ValidationResult',
    'SilverStripe\\ORM\\ValidationException' => 'SilverStripe\\Core\\Validation\\ValidationException',

    // ===== FORM VALIDATOR CLASSES (Renamed for consistency) =====
    'SilverStripe\\Forms\\RequiredFields' => 'SilverStripe\\Forms\\Validation\\RequiredFieldsValidator',
    'SilverStripe\\Forms\\FieldsValidator' => 'SilverStripe\\Forms\\Validation\\FormFieldsValidator',
    'SilverStripe\\Forms\\CompositeValidator' => 'SilverStripe\\Forms\\Validation\\CompositeValidator',
    'SilverStripe\\Forms\\Validator' => 'SilverStripe\\Forms\\Validation\\Validator',

    // ===== PASSWORD VALIDATOR (Renamed) =====
    'SilverStripe\\Security\\PasswordValidator' => 'SilverStripe\\Security\\Validation\\RulesPasswordValidator',

    // ===== EXTENSION CLASSES (REMOVED - use SilverStripe\Core\Extension) =====
    // These classes have been removed entirely. Use Extension instead.
    'SilverStripe\\ORM\\DataExtension' => 'SilverStripe\\Core\\Extension',
    'SilverStripe\\CMS\\Model\\SiteTreeExtension' => 'SilverStripe\\Core\\Extension',
    'SilverStripe\\Admin\\LeftAndMainExtension' => 'SilverStripe\\Core\\Extension',
];
