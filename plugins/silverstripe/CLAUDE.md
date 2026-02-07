# Silverstripe CMS Development

This project uses Silverstripe CMS. The Silverstripe MCP server provides code validation and documentation tools.

## Available Tools

- **ss-validator** - Validates PHP code for Silverstripe 6 compatibility
- **list-sections** - Lists available SS6 changelog documentation sections
- **get-documentation** - Fetches SS6 changelog content

## Silverstripe 6 Reference

Full changelog: https://docs.silverstripe.org/en/6/changelogs/6.0.0/

## Validation Requirement

When writing Silverstripe PHP code, ALWAYS validate before presenting:

1. Call `ss-validator` with your code
2. Fix any issues found
3. Re-validate until clean
4. Then present to user

## Subagent Available

For complex Silverstripe file edits, delegate to the `silverstripe-php-editor` agent. It handles validation loops in its own context.
