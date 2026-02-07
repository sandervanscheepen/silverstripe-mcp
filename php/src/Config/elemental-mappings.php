<?php

declare(strict_types=1);

/**
 * Elemental module namespace mappings for Silverstripe 6.
 *
 * Maps old (SS5) Elemental class paths to their new (SS6) locations.
 * Classes mapped to null have been removed without a direct replacement.
 *
 * Sources:
 * - https://github.com/dnadesign/silverstripe-elemental/releases/tag/6.0.0
 * - https://github.com/dnadesign/silverstripe-elemental/pull/1249 (TopPage renames)
 * - https://github.com/dnadesign/silverstripe-elemental/pull/1323 (Search refactor)
 * - https://github.com/dnadesign/silverstripe-elemental/pull/1220 (GraphQL removal)
 */
return [
    // ===== TOPPAGE CLASS RENAMES =====
    // PR #1249: TopPage classes moved to Extensions namespace with descriptive names
    'DNADesign\\Elemental\\TopPage\\DataExtension' => 'DNADesign\\Elemental\\Extensions\\TopPageElementExtension',
    'DNADesign\\Elemental\\TopPage\\FluentExtension' => 'DNADesign\\Elemental\\Extensions\\TopPageFluentElementExtension',
    'DNADesign\\Elemental\\TopPage\\SiteTreeExtension' => 'DNADesign\\Elemental\\Extensions\\TopPageSiteTreeExtension',

    // ===== SEARCH CLASS REFACTORING =====
    // PR #1323: Search functionality moved from Controller to ORM/SearchContext pattern
    'DNADesign\\Elemental\\Controllers\\ElementSiteTreeFilterSearch' => 'DNADesign\\Elemental\\ORM\\Search\\ElementalSiteTreeSearchContext',

    // ===== REMOVED CLASSES (no direct replacement) =====
    // These classes have been removed entirely. Using null to indicate removal.

    // PR #1220: GraphQL support removed - use REST API instead
    'DNADesign\\Elemental\\GraphQL\\Resolvers\\Resolver' => null,

    // Removed without equivalent - functionality handled via YAML config
    'DNADesign\\Elemental\\Extensions\\ElementalLeftAndMainExtension' => null,

    // PR #1323: Removed as part of search refactoring
    'DNADesign\\Elemental\\Extensions\\ElementalCMSMainExtension' => null,

    // Removed without equivalent functionality
    'DNADesign\\Elemental\\ORM\\FieldType\\DBObjectType' => null,
];
