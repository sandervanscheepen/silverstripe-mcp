# Dependency Changes in Silverstripe 6

Source: https://docs.silverstripe.org/en/6/changelogs/6.0.0/#dependency-changes

## Overview

Silverstripe CMS 6 updates several major dependencies with breaking changes.

## PHP Version Requirements

**Breaking Change:** Requires **PHP 8.3 or PHP 8.4**

- PHP 8.1 and PHP 8.2 (supported in SS5) are no longer supported
- CMS 5 EOL aligns with PHP 8.3 EOL (December 2026)

### Required PHP Extensions

- ctype
- dom
- fileinfo
- hash
- intl
- mbstring
- session
- simplexml
- tokenizer
- xml
- GD or ImageMagick

### Memory Requirements

- Minimum 48MB memory limit

## Database Changes

### MySQL Version

- MySQL 5.6 and 5.7 are no longer supported
- Minimum required: **MySQL 8.0**

### Character Set

- MySQL now defaults to `utf8mb4` encoding

## Symfony Upgrade

**Breaking Change:** Symfony dependencies upgraded from **v6 to v7**

### Key Components

- `symfony/cache: ^7.1.5`
- `symfony/config: ^7.0`
- `symfony/console: ^7.0`
- `symfony/dom-crawler: ^7.0`
- `symfony/validator: ^7.0`

### Console/CLI Changes

Sake rebuilt using `symfony/console`:
- BuildTask pattern: `run(HTTPRequest)` -> `execute(InputInterface, PolyOutput)`
- New structured console commands architecture

### Password Validation

New `EntropyPasswordValidator` using Symfony's `PasswordStrength` constraint:
- Passwords validated based on entropy
- Configuration: `EntropyPasswordValidator.password_strength`

### URL Validation

`RedirectorPage` uses Symfony's `Url` constraint:
- No longer auto-adds `http://` to URLs
- Validation errors displayed instead

## Intervention/Image Upgrade

**Breaking Change:** Upgraded from **v2 to v3**

### Key Improvements

- Better animated image support (GIF, WebP)
- Enhanced driver auto-detection

### Driver Selection

- **Imagick** is now default if available
- **GD** is fallback

### New Animation APIs

```php
$image->getIsAnimated();
$image->removeAnimation();
$image->getAllowsAnimationInManipulations();
$image->setAllowsAnimationInManipulations(false);
```

### Migration Impact

Direct `intervention/image` API calls require updates per their [upgrade guide](https://image.intervention.io/v3/getting-started/upgrade).

## PHPUnit 11

**Testing Framework Update:** Now uses **PHPUnit 11**

### Cache Flushing Change

```bash
# Old (no longer works)
vendor/bin/phpunit <filepath> flush=1

# New
SS_PHPUNIT_FLUSH=1 vendor/bin/phpunit <filepath>
```

## Summary of Breaking Changes

| Dependency | Old Version | New Version |
|------------|-------------|-------------|
| PHP | 8.1+ | 8.3 - 8.4 |
| MySQL | 5.6, 5.7 | 8.0+ |
| Symfony | v6.x | v7.x |
| intervention/image | v2.x | v3.x |
| PHPUnit | 10.x | 11.x |

## Migration Checklist

### 1. Environment Setup

- [ ] Upgrade PHP to 8.3 or 8.4
- [ ] Upgrade MySQL/MariaDB to 8.0+
- [ ] Verify required PHP extensions

### 2. Dependencies

- [ ] Update `composer.json` for Symfony v7
- [ ] Update `intervention/image` to v3
- [ ] Update `phpunit/phpunit` to v11

### 3. Code Updates

- [ ] Review direct `intervention/image` API calls
- [ ] Update BuildTask classes to `execute()` signature
- [ ] Update PHPUnit test flush commands
- [ ] Review Symfony-dependent code

### 4. Database

- [ ] Plan MySQL/MariaDB upgrade
- [ ] Verify character set is utf8mb4
- [ ] Test read-only replica if applicable

### 5. Testing

- [ ] Run test suite with PHPUnit 11
- [ ] Test image manipulation with animated images
- [ ] Verify password validation
- [ ] Test BuildTask implementations
