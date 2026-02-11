<?php

declare(strict_types=1);

use CodebaseDump\Models\NodeAnalysis;
use CodebaseDump\Models\DirectoryAnalysis;
use CodebaseDump\Models\TextFileAnalysis;

describe('NodeAnalysis', function () {
    test('node analysis stores name', function () {
        $node = new class ('test') extends NodeAnalysis {
            public function getType(): string
            {
                return 'test';
            }

            public function getSize(): int
            {
                return 0;
            }

            public function toArray(): array
            {
                return ['name' => $this->name];
            }
        };

        expect($node->name)->toBe('test');
    });
});

describe('DirectoryAnalysis', function () {
    test('directory analysis stores name', function () {
        $directory = new DirectoryAnalysis('test');
        expect($directory->name)->toBe('test');
        expect($directory->getType())->toBe('directory');
    });

    test('empty directory has zero counts', function () {
        $directory = new DirectoryAnalysis('test');
        expect($directory->getNonIgnoredFileCount())->toBe(0);
        expect($directory->getNonIgnoredDirCount())->toBe(0);
        expect($directory->getTotalTokens())->toBe(0);
        expect($directory->getNonIgnoredTextContentSize())->toBe(0);
        expect($directory->getSize())->toBe(0);
    });

    test('directory with one text file', function () {
        $directory = new DirectoryAnalysis('test');
        $textFile = new TextFileAnalysis('test');
        $textFile->fileContent = 'length of this string is 27';

        $directory->children[] = $textFile;

        expect($directory->getNonIgnoredFileCount())->toBe(1);
        expect($directory->getNonIgnoredDirCount())->toBe(0);
        expect($directory->getNonIgnoredTextContentSize())->toBe(27);
        expect($directory->getSize())->toBe(27);
    });

    test('directory with ten files', function () {
        $directory = new DirectoryAnalysis('test');
        for ($i = 0; $i < 10; $i++) {
            $textFile = new TextFileAnalysis('test');
            $textFile->fileContent = 'length of this string is 27';
            $directory->children[] = $textFile;
        }

        expect($directory->getNonIgnoredFileCount())->toBe(10);
        expect($directory->getNonIgnoredDirCount())->toBe(0);
        expect($directory->getNonIgnoredTextContentSize())->toBe(270);
        expect($directory->getSize())->toBe(270);
    });

    test('directory with one subdirectory', function () {
        $directory = new DirectoryAnalysis('test');
        $subDirectory = new DirectoryAnalysis('test');
        $textFile = new TextFileAnalysis('test');
        $textFile->fileContent = 'length of this string is 27';
        $subDirectory->children[] = $textFile;
        $directory->children[] = $subDirectory;

        expect($directory->getNonIgnoredFileCount())->toBe(1);
        expect($directory->getNonIgnoredDirCount())->toBe(1);
        expect($directory->getNonIgnoredTextContentSize())->toBe(27);
        expect($directory->getSize())->toBe(27);
    });

    test('directory with one ignored file', function () {
        $directory = new DirectoryAnalysis('test');
        $textFile = new TextFileAnalysis('test');
        $textFile->isIgnored = true;
        $textFile->fileContent = 'length of this string is 27';
        $directory->children[] = $textFile;

        expect($directory->getNonIgnoredFileCount())->toBe(0);
        expect($directory->getNonIgnoredDirCount())->toBe(0);
        expect($directory->getNonIgnoredTextContentSize())->toBe(0);
        expect($directory->getSize())->toBe(27);
    });

    test('directory with one ignored subdirectory', function () {
        $directory = new DirectoryAnalysis('test');
        $subDirectory = new DirectoryAnalysis('test');
        $subDirectory->isIgnored = true;
        $textFile = new TextFileAnalysis('test');
        $textFile->isIgnored = true;
        $textFile->fileContent = 'length of this string is 27';
        $subDirectory->children[] = $textFile;
        $directory->children[] = $subDirectory;

        expect($directory->getNonIgnoredFileCount())->toBe(0);
        expect($directory->getNonIgnoredDirCount())->toBe(0);
        expect($directory->getNonIgnoredTextContentSize())->toBe(0);
        expect($directory->getSize())->toBe(27);
    });

    test('getFullPath returns full path for nested directories', function () {
        $root = new DirectoryAnalysis(name: 'root');
        $dir1 = new DirectoryAnalysis(name: 'dir1', parent: $root);
        $dir2 = new DirectoryAnalysis(name: 'dir2', parent: $dir1);
        $file1 = new TextFileAnalysis(name: 'file1.txt', parent: $dir2);

        $root->children[] = $dir1;
        $dir1->children[] = $dir2;
        $dir2->children[] = $file1;

        expect($root->getFullPath())->toBe('root');
        expect($dir1->getFullPath())->toBe('root' . DIRECTORY_SEPARATOR . 'dir1');
        expect($dir2->getFullPath())->toBe('root' . DIRECTORY_SEPARATOR . 'dir1' . DIRECTORY_SEPARATOR . 'dir2');
        expect($file1->getFullPath())->toBe('root' . DIRECTORY_SEPARATOR . 'dir1' . DIRECTORY_SEPARATOR . 'dir2' . DIRECTORY_SEPARATOR . 'file1.txt');
    });

    test('getFullPath returns name without parent', function () {
        $file = new TextFileAnalysis('file.txt');
        expect($file->getFullPath())->toBe('file.txt');

        $dir = new DirectoryAnalysis('dir');
        expect($dir->getFullPath())->toBe('dir');
    });

    test('getAllChildren returns all children including nested', function () {
        $root = new DirectoryAnalysis(name: 'root');
        $file1 = new TextFileAnalysis(name: 'file1.txt', fileContent: 'Hello', parent: $root);
        $dir1 = new DirectoryAnalysis(name: 'dir1', parent: $root);
        $file2 = new TextFileAnalysis(name: 'file2.txt', fileContent: 'Hello, world!', parent: $dir1);
        $dir2 = new DirectoryAnalysis(name: 'dir2', parent: $root);
        $file3 = new TextFileAnalysis(name: 'file3.txt', fileContent: 'PHP', parent: $dir2, isIgnored: true);

        $root->children = [$file1, $dir1, $dir2];
        $dir1->children = [$file2];
        $dir2->children = [$file3];

        $allChildren = $root->getAllChildren();
        $names = array_map(fn($node) => $node->name, $allChildren);

        expect($names)->toContain('file1.txt');
        expect($names)->toContain('dir1');
        expect($names)->toContain('file2.txt');
        expect($names)->toContain('dir2');
        expect($names)->toContain('file3.txt');
        expect(count($allChildren))->toBe(5);
    });

    test('getAllNonIgnoredFiles returns only non-ignored files', function () {
        $root = new DirectoryAnalysis(name: 'root');
        $file1 = new TextFileAnalysis(name: 'file1.txt', fileContent: 'Hello', parent: $root);
        $dir1 = new DirectoryAnalysis(name: 'dir1', parent: $root);
        $file2 = new TextFileAnalysis(name: 'file2.txt', fileContent: 'Hello, world!', parent: $dir1);
        $dir2 = new DirectoryAnalysis(name: 'dir2', parent: $root);
        $file3 = new TextFileAnalysis(name: 'file3.txt', fileContent: 'PHP', parent: $dir2, isIgnored: true);

        $root->children = [$file1, $dir1, $dir2];
        $dir1->children = [$file2];
        $dir2->children = [$file3];

        $nonIgnoredFiles = $root->getAllNonIgnoredFiles();
        $names = array_map(fn($file) => $file->name, $nonIgnoredFiles);

        expect($names)->toContain('file1.txt');
        expect($names)->toContain('file2.txt');
        expect($names)->not->toContain('file3.txt');
        expect(count($nonIgnoredFiles))->toBe(2);
    });

    test('getAllIgnoredFiles returns only ignored files', function () {
        $root = new DirectoryAnalysis(name: 'root');
        $file1 = new TextFileAnalysis(name: 'file1.txt', fileContent: 'Hello', parent: $root);
        $dir1 = new DirectoryAnalysis(name: 'dir1', parent: $root);
        $file2 = new TextFileAnalysis(name: 'file2.txt', fileContent: 'Hello, world!', parent: $dir1);
        $dir2 = new DirectoryAnalysis(name: 'dir2', parent: $root);
        $file3 = new TextFileAnalysis(name: 'file3.txt', fileContent: 'PHP', parent: $dir2, isIgnored: true);

        $root->children = [$file1, $dir1, $dir2];
        $dir1->children = [$file2];
        $dir2->children = [$file3];

        $ignoredFiles = $root->getAllIgnoredFiles();
        $names = array_map(fn($file) => $file->name, $ignoredFiles);

        expect($names)->toContain('file3.txt');
        expect(count($ignoredFiles))->toBe(1);
    });

    test('getLargestFiles returns largest files by size', function () {
        $root = new DirectoryAnalysis(name: 'root');
        $fileSmall = new TextFileAnalysis(name: 'small.txt', fileContent: 'a', parent: $root);
        $fileMedium = new TextFileAnalysis(name: 'medium.txt', fileContent: 'abc', parent: $root);
        $fileLarge = new TextFileAnalysis(name: 'large.txt', fileContent: 'abcdef', parent: $root);
        $ignoredFile = new TextFileAnalysis(name: 'ignored.txt', fileContent: 'ignored', parent: $root, isIgnored: true);
        $root->children = [$fileSmall, $fileMedium, $fileLarge, $ignoredFile];

        $largest = $root->getLargestFiles(n: 2);

        expect($largest[0]->name)->toBe('large.txt');
        expect($largest[1]->name)->toBe('medium.txt');
        expect(count($largest))->toBe(2);
    });

    test('getLargestDirectories returns largest directories by content size', function () {
        $root = new DirectoryAnalysis(name: 'root');
        $dir1 = new DirectoryAnalysis(name: 'dir1', parent: $root);
        $dir2 = new DirectoryAnalysis(name: 'dir2', parent: $root);
        $file1 = new TextFileAnalysis(name: 'file1.txt', fileContent: str_repeat('a', 10), parent: $dir1);
        $file2 = new TextFileAnalysis(name: 'file2.txt', fileContent: str_repeat('a', 20), parent: $dir2);
        $dir1->children = [$file1];
        $dir2->children = [$file2];
        $root->children = [$dir1, $dir2];

        $largestDirs = $root->getLargestDirectories(n: 1);

        expect($largestDirs[0]->name)->toBe('dir2');
        expect(count($largestDirs))->toBe(1);
    });
});

describe('TextFileAnalysis', function () {
    test('text file stores name', function () {
        $textFile = new TextFileAnalysis('test');
        expect($textFile->name)->toBe('test');
        expect($textFile->getType())->toBe('text_file');
    });

    test('counts tokens correctly', function () {
        $textFile = new TextFileAnalysis('test', fileContent: 'This is a test string');
        expect($textFile->countTokens())->toBe(5);
    });

    test('counts one token for single word', function () {
        $textFile = new TextFileAnalysis('test', fileContent: 'This');
        expect($textFile->countTokens())->toBe(1);
    });

    test('counts zero tokens for empty content', function () {
        $textFile = new TextFileAnalysis('test', fileContent: '');
        expect($textFile->countTokens())->toBe(0);
    });

    test('returns zero size for no content', function () {
        $textFile = new TextFileAnalysis('test', fileContent: '');
        expect($textFile->getSize())->toBe(0);
    });

    test('returns correct size for content', function () {
        $textFile = new TextFileAnalysis('test', fileContent: 'Test content');
        expect($textFile->getSize())->toBe(12);
    });
});
