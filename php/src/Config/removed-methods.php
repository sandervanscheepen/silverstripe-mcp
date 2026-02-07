<?php

declare(strict_types=1);

/**
 * Configuration for removed methods in Silverstripe 6.
 *
 * Format:
 * 'ClassName::methodName' => [
 *     'message' => 'Explanation of removal',
 *     'suggestion' => 'What to use instead',
 *     'docsUrl' => 'Link to documentation',
 *     'isStatic' => true/false (optional, defaults to false)
 * ]
 *
 * For instance methods, the className is used for context detection
 * (e.g., detecting $this->getCMSValidator() in a DataObject subclass).
 */

return [
    // Controller::has_curr() removed
    'Controller::has_curr' => [
        'message' => 'Controller::has_curr() has been removed in Silverstripe 6',
        'suggestion' => 'Use Controller::curr() with a try/catch block, or check if a controller exists another way',
        'docsUrl' => 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/#api-general',
        'isStatic' => true,
    ],

    // DataObject::getCMSValidator() removed
    'DataObject::getCMSValidator' => [
        'message' => 'DataObject::getCMSValidator() has been removed in Silverstripe 6',
        'suggestion' => 'Use getCMSCompositeValidator() instead',
        'docsUrl' => 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/#api-general',
        'isStatic' => false,
    ],

    // Requirements::themedCSS() removed
    'Requirements::themedCSS' => [
        'message' => 'Requirements::themedCSS() has been removed in Silverstripe 6',
        'suggestion' => 'Use Requirements::css() with ThemeResourceLoader to resolve themed paths',
        'docsUrl' => 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/#api-general',
        'isStatic' => true,
    ],

    // Requirements::themedJavascript() removed
    'Requirements::themedJavascript' => [
        'message' => 'Requirements::themedJavascript() has been removed in Silverstripe 6',
        'suggestion' => 'Use Requirements::javascript() with ThemeResourceLoader to resolve themed paths',
        'docsUrl' => 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/#api-general',
        'isStatic' => true,
    ],

    // Object::useCustomClass() removed
    'Object::useCustomClass' => [
        'message' => 'Object::useCustomClass() has been removed in Silverstripe 6',
        'suggestion' => 'Use Injector configuration instead',
        'docsUrl' => 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/#api-general',
        'isStatic' => true,
    ],

    // Config::inst()->update() deprecated pattern
    'Config::modify' => [
        'message' => 'Config::modify() pattern has changed in Silverstripe 6',
        'suggestion' => 'Use Config::modify()->set() or Config::modify()->merge() explicitly',
        'docsUrl' => 'https://docs.silverstripe.org/en/6/changelogs/6.0.0/#api-general',
        'isStatic' => true,
    ],
];
