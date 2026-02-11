<?php

declare(strict_types=1);

use CodebaseDump\Core\IgnorePatternManager;

describe('IgnorePatternManager', function () {
    test('loads default ignore patterns', function () {
        $manager = new IgnorePatternManager(
            '/test',
            loadDefaultIgnorePatterns: true,
            loadGitignore: false,
            loadCdigestignore: false
        );

        expect($manager->getIgnorePatternsAsArray())
            ->toEqual(IgnorePatternManager::DEFAULT_IGNORE_PATTERNS);
    });

    test('loads extra patterns', function () {
        $manager = new IgnorePatternManager(
            '/test',
            loadDefaultIgnorePatterns: false,
            loadGitignore: false,
            loadCdigestignore: false,
            extraIgnorePatterns: ['extra1', 'extra2']
        );

        $patterns = $manager->getIgnorePatternsAsArray();
        expect($patterns)->toContain('extra1');
        expect($patterns)->toContain('extra2');
    });

    test('ignores matching filename', function () {
        $manager = new IgnorePatternManager(
            '/test',
            loadDefaultIgnorePatterns: false,
            loadGitignore: false,
            loadCdigestignore: false,
            extraIgnorePatterns: ['test.txt']
        );

        expect($manager->shouldIgnore('/test/test.txt'))->toBeTrue();
        expect($manager->shouldIgnore('/test/other.txt'))->toBeFalse();
    });

    test('ignores relative path', function () {
        $manager = new IgnorePatternManager(
            '/test',
            loadDefaultIgnorePatterns: false,
            loadGitignore: false,
            loadCdigestignore: false,
            extraIgnorePatterns: ['sub/test.txt']
        );

        expect($manager->shouldIgnore('/test/sub/test.txt'))->toBeTrue();
        expect($manager->shouldIgnore('/test/sub/other.txt'))->toBeFalse();
        expect($manager->shouldIgnore('/test/test.txt'))->toBeFalse();
    });

    test('ignores directory pattern', function () {
        $manager = new IgnorePatternManager(
            '/test',
            loadDefaultIgnorePatterns: false,
            loadGitignore: false,
            loadCdigestignore: false,
            extraIgnorePatterns: ['sub/']
        );

        expect($manager->shouldIgnore('/test/sub/'))->toBeTrue();
        expect($manager->shouldIgnore('/test/sub.txt'))->toBeFalse();
    });

    test('ignores recursive wildcard pattern', function () {
        $manager = new IgnorePatternManager(
            '/test',
            loadDefaultIgnorePatterns: false,
            loadGitignore: false,
            loadCdigestignore: false,
            extraIgnorePatterns: ['**/logs', '**/*.tmp']
        );

        expect($manager->shouldIgnore('/test/logs'))->toBeTrue();
        expect($manager->shouldIgnore('/test/sub/logs'))->toBeTrue();
        expect($manager->shouldIgnore('/test/sub/file.tmp'))->toBeTrue();
        expect($manager->shouldIgnore('/test/sub/file.txt'))->toBeFalse();
    });

    test('handles empty pattern (comment only)', function () {
        $manager = new IgnorePatternManager(
            '/test',
            loadDefaultIgnorePatterns: false,
            loadGitignore: false,
            loadCdigestignore: false,
            extraIgnorePatterns: ['# Comment only']
        );

        expect($manager->shouldIgnore('/test/file.txt'))->toBeFalse();
    });

    test('ignores files with default patterns', function () {
        $manager = new IgnorePatternManager(
            '/test',
            loadDefaultIgnorePatterns: true,
            loadGitignore: false,
            loadCdigestignore: false
        );

        expect($manager->shouldIgnore('/test/__pycache__/file.py'))->toBeTrue();
        expect($manager->shouldIgnore('/test/node_modules/package/file.js'))->toBeTrue();
        expect($manager->shouldIgnore('/test/.git/config'))->toBeTrue();
        expect($manager->shouldIgnore('/test/venv/lib/python.so'))->toBeTrue();
        expect($manager->shouldIgnore('/test/.DS_Store'))->toBeTrue();
    });

    test('returns correct base path', function () {
        $manager = new IgnorePatternManager('/my/path', loadDefaultIgnorePatterns: false);
        expect($manager->getBasePath())->toBe('/my/path');
    });

    test('uses current directory when path is dot', function () {
        $originalCwd = getcwd();
        chdir('/tmp');

        $manager = new IgnorePatternManager('.', loadDefaultIgnorePatterns: false);
        expect($manager->getBasePath())->toBe('/tmp');

        chdir($originalCwd);
    });
});
