<?php

declare(strict_types=1);

/**
 * Configuration for renamed extension hooks in Silverstripe 6.
 *
 * Note: The SS6 changelog primarily documents hooks being changed to protected
 * visibility (handled by ExtensionHookVisibilityPlugin), not renamed hooks.
 * This file is prepared for any hook renames that may be discovered.
 *
 * Format:
 * 'oldHookName' => [
 *     'newName' => 'newHookName',
 *     'message' => 'Explanation of the rename',
 *     'docsUrl' => 'Link to documentation',
 * ]
 */

return [
    // No hook renames currently documented in SS6 changelog.
    // Hooks were changed to protected visibility, not renamed.
    // Add entries here if hook renames are discovered.
];
