<?php

declare(strict_types=1);

use CodebaseDump\Core\CodebaseAnalysis;
use CodebaseDump\Core\IgnorePatternManager;
use CodebaseDump\Models\DirectoryAnalysis;
use CodebaseDump\Models\TextFileAnalysis;

function removeDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? removeDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

describe('CodebaseAnalysis', function () {
    $tempDir = null;

    beforeEach(function () use (&$tempDir) {
        $tempDir = sys_get_temp_dir() . '/codebase_analysis_test_' . uniqid();
        mkdir($tempDir);
    });

    afterEach(function () use (&$tempDir) {
        removeDirectory($tempDir);
    });

    test('isTextFile returns true for text file', function () use (&$tempDir) {
        $testFile = $tempDir . '/test.txt';
        file_put_contents($testFile, 'Hello World');

        $analysis = new CodebaseAnalysis();
        expect($analysis->isTextFile($testFile))->toBeTrue();
    });

    test('isTextFile returns false for binary file', function () use (&$tempDir) {
        $testFile = $tempDir . '/test.bin';
        file_put_contents($testFile, "\x00\x01\x02\x03");

        $analysis = new CodebaseAnalysis();
        expect($analysis->isTextFile($testFile))->toBeFalse();
    });

    test('readFileContent returns correct content', function () use (&$tempDir) {
        $testFile = $tempDir . '/test.txt';
        $content = 'Test content';
        file_put_contents($testFile, $content);

        $analysis = new CodebaseAnalysis();
        expect($analysis->readFileContent($testFile))->toBe($content);
    });

    test('listDirectoryItems returns directory contents', function () use (&$tempDir) {
        file_put_contents($tempDir . '/file1.txt', 'content1');
        file_put_contents($tempDir . '/file2.txt', 'content2');

        $analysis = new CodebaseAnalysis();
        $items = $analysis->listDirectoryItems($tempDir);

        expect(count($items))->toBe(2);
    });

    test('listDirectoryItems returns empty array for nonexistent path', function () {
        $analysis = new CodebaseAnalysis();
        ob_start();
        $items = $analysis->listDirectoryItems('/nonexistent/path');
        ob_end_clean();

        expect($items)->toBeEmpty();
    });

    test('analyzeTextFile returns TextFileAnalysis', function () use (&$tempDir) {
        $testFile = $tempDir . '/test.txt';
        file_put_contents($testFile, 'Sample content');

        $analysis = new CodebaseAnalysis();
        $result = $analysis->analyzeFile($testFile, false, null);

        expect($result)->toBeInstanceOf(TextFileAnalysis::class);
        expect($result->name)->toBe('test.txt');
        expect($result->fileContent)->toBe('Sample content');
        expect($result->isIgnored)->toBeFalse();
    });

    test('analyzeNonTextFile returns TextFileAnalysis with placeholder content', function () use (&$tempDir) {
        $testFile = $tempDir . '/test.bin';
        file_put_contents($testFile, "\x00\x01\x02\x03");

        $analysis = new CodebaseAnalysis();
        $result = $analysis->analyzeFile($testFile, false, null);

        expect($result)->toBeInstanceOf(TextFileAnalysis::class);
        expect($result->fileContent)->toBe('[Non-text file]');
    });

    test('analyzeDirectory returns DirectoryAnalysis with children', function () use (&$tempDir) {
        file_put_contents($tempDir . '/file1.txt', 'content1');
        file_put_contents($tempDir . '/file2.py', 'content2');

        $ignoreManager = new IgnorePatternManager(
            $tempDir,
            loadDefaultIgnorePatterns: false
        );

        $analysis = new CodebaseAnalysis();
        $result = $analysis->analyzeDirectory($tempDir, $ignoreManager, $tempDir);

        expect($result)->toBeInstanceOf(DirectoryAnalysis::class);
        expect(count($result->children))->toBe(2);
    });

    test('analyzeDirectory respects ignore patterns', function () use (&$tempDir) {
        file_put_contents($tempDir . '/file1.log', 'log content');
        file_put_contents($tempDir . '/file2.tmp', 'tmp content');
        file_put_contents($tempDir . '/file3.txt', 'txt content');

        $ignoreManager = new IgnorePatternManager(
            $tempDir,
            loadDefaultIgnorePatterns: false,
            extraIgnorePatterns: ['*.log', '*.tmp']
        );

        $analysis = new CodebaseAnalysis();
        $result = $analysis->analyzeDirectory($tempDir, $ignoreManager, $tempDir);

        $allChildren = $result->getAllChildren();
        expect(count($allChildren))->toBe(3);

        $nonIgnoredFiles = $result->getAllNonIgnoredFiles();
        expect(count($nonIgnoredFiles))->toBe(1);
        expect($nonIgnoredFiles[0]->name)->toBe('file3.txt');
    });

    test('analyzeDirectory handles nested directories', function () use (&$tempDir) {
        mkdir($tempDir . '/subdir');
        file_put_contents($tempDir . '/file1.txt', 'content1');
        file_put_contents($tempDir . '/file2.py', 'content2');
        file_put_contents($tempDir . '/subdir/file3.txt', 'content3');

        $ignoreManager = new IgnorePatternManager(
            $tempDir,
            loadDefaultIgnorePatterns: false
        );

        $analysis = new CodebaseAnalysis();
        $result = $analysis->analyzeDirectory($tempDir, $ignoreManager, $tempDir);

        expect(count($result->children))->toBe(3);

        $subdir = null;
        foreach ($result->children as $child) {
            if ($child instanceof DirectoryAnalysis && $child->name === 'subdir') {
                $subdir = $child;
                break;
            }
        }

        expect($subdir)->not->toBeNull();
        expect(count($subdir->children))->toBe(1);
    });

    test('analyzeFile returns null for nonexistent file', function () {
        $analysis = new CodebaseAnalysis();
        ob_start();
        $result = $analysis->analyzeFile('/nonexistent/file.txt', false, null);
        ob_end_clean();

        expect($result)->toBeNull();
    });

    test('analyzeDirectories handles multiple directories', function () use (&$tempDir) {
        $dir1 = $tempDir . '/dir1';
        $dir2 = $tempDir . '/dir2';
        mkdir($dir1);
        mkdir($dir2);

        file_put_contents($dir1 . '/file_a.txt', 'content a');
        file_put_contents($dir1 . '/file_b.txt', 'content b');
        file_put_contents($dir2 . '/file_c.txt', 'content c');
        file_put_contents($dir2 . '/file_d.txt', 'content d');

        $ignoreManager = new IgnorePatternManager(
            $tempDir,
            loadDefaultIgnorePatterns: false
        );

        $analysis = new CodebaseAnalysis();
        $result = $analysis->analyzeDirectories([$dir1, $dir2], $ignoreManager);

        expect($result)->toBeInstanceOf(DirectoryAnalysis::class);
        expect($result->name)->toBe('Codebase Dump');
        expect(count($result->children))->toBe(2);

        $childNames = array_map(fn($c) => $c->name, $result->children);
        expect($childNames)->toContain('dir1');
        expect($childNames)->toContain('dir2');

        $nonIgnoredFiles = $result->getAllNonIgnoredFiles();
        expect(count($nonIgnoredFiles))->toBe(4);
    });

    test('analyzeDirectories with single path delegates to analyzeDirectory', function () use (&$tempDir) {
        file_put_contents($tempDir . '/file1.txt', 'content1');
        file_put_contents($tempDir . '/file2.txt', 'content2');

        $ignoreManager = new IgnorePatternManager(
            $tempDir,
            loadDefaultIgnorePatterns: false
        );

        $analysis = new CodebaseAnalysis();
        $result = $analysis->analyzeDirectories([$tempDir], $ignoreManager);

        expect($result)->toBeInstanceOf(DirectoryAnalysis::class);
        expect($result->name)->toBe(basename($tempDir));
        expect(count($result->children))->toBe(2);
    });

    test('analyzeDirectories handles nested subdirectories in multiple roots', function () use (&$tempDir) {
        $dir1 = $tempDir . '/src';
        $dir2 = $tempDir . '/lib';
        mkdir($dir1);
        mkdir($dir2);
        mkdir($dir1 . '/sub');
        mkdir($dir2 . '/vendor');

        file_put_contents($dir1 . '/main.php', '<?php');
        file_put_contents($dir1 . '/sub/helper.php', '<?php');
        file_put_contents($dir2 . '/utils.php', '<?php');
        file_put_contents($dir2 . '/vendor/dep.php', '<?php');

        $ignoreManager = new IgnorePatternManager(
            $tempDir,
            loadDefaultIgnorePatterns: false
        );

        $analysis = new CodebaseAnalysis();
        $result = $analysis->analyzeDirectories([$dir1, $dir2], $ignoreManager);

        $nonIgnoredFiles = $result->getAllNonIgnoredFiles();
        $fileNames = array_map(fn($f) => $f->name, $nonIgnoredFiles);
        expect($fileNames)->toContain('main.php', 'helper.php', 'utils.php', 'dep.php');

        $dirChildren = null;
        $libChildren = null;
        foreach ($result->children as $child) {
            if ($child instanceof DirectoryAnalysis && $child->name === 'src') {
                $dirChildren = $child;
            }
            if ($child instanceof DirectoryAnalysis && $child->name === 'lib') {
                $libChildren = $child;
            }
        }
        expect($dirChildren)->not->toBeNull();
        expect($libChildren)->not->toBeNull();

        $subNames = array_map(fn($c) => $c->name, $dirChildren->children);
        expect($subNames)->toContain('main.php', 'sub');

        $vendorNames = array_map(fn($c) => $c->name, $libChildren->children);
        expect($vendorNames)->toContain('utils.php', 'vendor');
    });
});
