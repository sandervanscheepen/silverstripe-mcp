import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import { analyzeCode } from '../src/lib/php-bridge.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

describe('ss-validator tool', () => {
    describe('Tool schema requirements', () => {
        it('should accept code parameter', async () => {
            const result = await analyzeCode({
                code: '<?php echo 1;'
            });

            expect(result).toBeDefined();
            expect(result.issues).toBeDefined();
        });

        it('should accept optional filename parameter', async () => {
            const result = await analyzeCode({
                code: '<?php echo 1;',
                filename: 'MyClass.php'
            });

            expect(result).toBeDefined();
        });

        it('should accept optional targetVersion parameter', async () => {
            const result = await analyzeCode({
                code: '<?php echo 1;',
                targetVersion: '6.0'
            });

            expect(result).toBeDefined();
        });
    });

    describe('Integration with fixtures', () => {
        it('should pass valid SS6 code', async () => {
            const code = readFileSync(
                join(__dirname, 'fixtures', 'valid-ss6-code.php'),
                'utf-8'
            );

            const result = await analyzeCode({
                code,
                filename: 'ValidTask.php',
                targetVersion: '6.0'
            });

            expect(result.issues).toHaveLength(0);
        });

        it('should detect wrong imports in fixture', async () => {
            const code = readFileSync(
                join(__dirname, 'fixtures', 'wrong-imports.php'),
                'utf-8'
            );

            const result = await analyzeCode({
                code,
                filename: 'MyClass.php',
                targetVersion: '6.0'
            });

            expect(result.issues.length).toBeGreaterThanOrEqual(3);

            const types = result.issues.map(i => i.type);
            expect(types.every(t => t === 'deprecated_import')).toBe(true);
        });

        it('should detect old BuildTask patterns in fixture', async () => {
            const code = readFileSync(
                join(__dirname, 'fixtures', 'old-buildtask.php'),
                'utf-8'
            );

            const result = await analyzeCode({
                code,
                filename: 'OldTask.php',
                targetVersion: '6.0'
            });

            expect(result.issues.length).toBeGreaterThanOrEqual(1);

            const types = result.issues.map(i => i.type);
            expect(types).toContain('deprecated_buildtask_signature');
            expect(types).toContain('deprecated_echo');
            expect(types).toContain('missing_command_name');
        });
    });

    describe('Response format', () => {
        it('should return issues array', async () => {
            const result = await analyzeCode({
                code: '<?php use SilverStripe\\ORM\\ArrayList;'
            });

            expect(Array.isArray(result.issues)).toBe(true);
        });

        it('should return suggestions array', async () => {
            const result = await analyzeCode({
                code: '<?php echo 1;'
            });

            expect(Array.isArray(result.suggestions)).toBe(true);
        });

        it('should return rerun boolean', async () => {
            const result = await analyzeCode({
                code: '<?php echo 1;'
            });

            expect(typeof result.rerun).toBe('boolean');
        });

        it('should include issue details', async () => {
            const result = await analyzeCode({
                code: '<?php use SilverStripe\\ORM\\ArrayList;'
            });

            const issue = result.issues[0];
            expect(issue).toHaveProperty('type');
            expect(issue).toHaveProperty('message');
            expect(issue).toHaveProperty('line');
            expect(issue).toHaveProperty('suggestion');
            expect(issue).toHaveProperty('docsUrl');
        });
    });
});
