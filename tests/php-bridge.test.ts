import { describe, it, expect } from 'vitest';
import { analyzeCode, formatResult } from '../src/lib/php-bridge.js';

describe('PHP Bridge', () => {
    it('returns empty issues for valid PHP', async () => {
        const result = await analyzeCode({
            code: '<?php echo 1;',
            filename: 'test.php',
            targetVersion: '6.0'
        });

        expect(result.issues).toHaveLength(0);
        expect(result.rerun).toBe(false);
    });

    it('detects wrong ArrayList import', async () => {
        const result = await analyzeCode({
            code: '<?php use SilverStripe\\ORM\\ArrayList;',
            filename: 'test.php',
            targetVersion: '6.0'
        });

        expect(result.issues).toHaveLength(1);
        expect(result.issues[0].type).toBe('deprecated_import');
        expect(result.issues[0].suggestion).toContain('SilverStripe\\Model\\List\\ArrayList');
    });

    it('detects old BuildTask pattern', async () => {
        const code = `<?php
use SilverStripe\\Dev\\BuildTask;
use SilverStripe\\Control\\HTTPRequest;

class MyTask extends BuildTask {
    public function run(HTTPRequest $request) {
        echo "test";
    }
}`;
        const result = await analyzeCode({
            code,
            filename: 'MyTask.php',
            targetVersion: '6.0'
        });

        expect(result.issues.length).toBeGreaterThanOrEqual(2);

        const types = result.issues.map(i => i.type);
        expect(types).toContain('deprecated_buildtask_signature');
        expect(types).toContain('deprecated_echo');
    });

    it('returns parse error for invalid PHP', async () => {
        const result = await analyzeCode({
            code: '<?php function {',
            filename: 'test.php',
            targetVersion: '6.0'
        });

        expect(result.issues).toHaveLength(1);
        expect(result.issues[0].type).toBe('parse_error');
    });

    it('includes line numbers in issues', async () => {
        const code = `<?php

namespace App;

use SilverStripe\\ORM\\ArrayList;`;
        const result = await analyzeCode({
            code,
            filename: 'test.php',
            targetVersion: '6.0'
        });

        expect(result.issues[0].line).toBe(5);
    });

    it('includes documentation URLs', async () => {
        const result = await analyzeCode({
            code: '<?php use SilverStripe\\ORM\\ArrayList;',
            filename: 'test.php',
            targetVersion: '6.0'
        });

        expect(result.issues[0].docsUrl).toContain('docs.silverstripe.org');
    });

    it('detects multiple deprecated imports', async () => {
        const code = `<?php
use SilverStripe\\ORM\\ArrayList;
use SilverStripe\\View\\ArrayData;
use SilverStripe\\ORM\\PaginatedList;`;

        const result = await analyzeCode({
            code,
            filename: 'test.php',
            targetVersion: '6.0'
        });

        expect(result.issues).toHaveLength(3);
        result.issues.forEach(issue => {
            expect(issue.type).toBe('deprecated_import');
        });
    });

    it('sets rerun to true when issues are found', async () => {
        const result = await analyzeCode({
            code: '<?php use SilverStripe\\ORM\\ArrayList;',
            filename: 'test.php',
            targetVersion: '6.0'
        });

        expect(result.rerun).toBe(true);
    });

    it('returns empty suggestions array', async () => {
        const result = await analyzeCode({
            code: '<?php echo 1;',
            filename: 'test.php',
            targetVersion: '6.0'
        });

        expect(result.suggestions).toBeDefined();
        expect(Array.isArray(result.suggestions)).toBe(true);
    });
});

describe('formatResult', () => {
    it('returns success message for no issues', () => {
        const result = {
            issues: [],
            suggestions: [],
            rerun: false
        };

        const formatted = formatResult(result);
        expect(formatted).toContain('No issues found');
        expect(formatted).toContain('compatible with Silverstripe 6');
    });

    it('formats issues with line numbers', () => {
        const result = {
            issues: [{
                type: 'deprecated_import',
                message: 'Test message',
                line: 5,
                column: null,
                code: null,
                suggestion: 'Use new import',
                docsUrl: 'https://example.com'
            }],
            suggestions: [],
            rerun: true
        };

        const formatted = formatResult(result);
        expect(formatted).toContain('Line 5');
        expect(formatted).toContain('Test message');
        expect(formatted).toContain('Use new import');
        expect(formatted).toContain('https://example.com');
    });

    it('includes rerun message when rerun is true', () => {
        const result = {
            issues: [{
                type: 'test',
                message: 'Test',
                line: 1,
                column: null,
                code: null,
                suggestion: null,
                docsUrl: null
            }],
            suggestions: [],
            rerun: true
        };

        const formatted = formatResult(result);
        expect(formatted).toContain('fix the issues');
        expect(formatted).toContain('run the validator again');
    });
});
