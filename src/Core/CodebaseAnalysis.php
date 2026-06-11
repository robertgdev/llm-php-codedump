<?php

declare(strict_types=1);

namespace CodebaseDump\Core;

use CodebaseDump\Models\DirectoryAnalysis;
use CodebaseDump\Models\NodeAnalysis;
use CodebaseDump\Models\TextFileAnalysis;

/**
 * Analyzes a codebase directory structure and file contents.
 */
class CodebaseAnalysis
{
    /**
     * Checks if a file is a text file by checking for null bytes.
     */
    public function isTextFile(string $filePath): bool
    {
        try {
            $handle = fopen($filePath, 'rb');
            if ($handle === false) {
                return false;
            }

            $chunk = fread($handle, 8192);
            fclose($handle);

            if ($chunk === false) {
                return false;
            }

            return strpos($chunk, "\x00") === false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isTextFileByContent(string $content): bool
    {
        return strpos($content, "\x00") === false;
    }

    /**
     * Reads the content of a file.
     */
    public function readFileContent(string $filePath): string
    {
        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                return "Error reading file: Unknown error";
            }

            return $content;
        } catch (\Throwable $e) {
            return "Error reading file: " . $e->getMessage();
        }
    }

    /**
     * Lists all items in a directory.
     *
     * @return array<string>
     */
    public function listDirectoryItems(string $path): array
    {
        try {
            if (!is_dir($path)) {
                echo "Directory not found: {$path}\n";
                return [];
            }

            $items = scandir($path);
            if ($items === false) {
                echo "Permission denied for: {$path}\n";
                return [];
            }

            // Remove . and .. entries
            $items = array_diff($items, ['.', '..']);

            // Convert to full paths
            return array_map(fn(string $item) => $path . DIRECTORY_SEPARATOR . $item, $items);
        } catch (\Throwable $e) {
            echo "Error listing directory: {$path} - " . $e->getMessage() . "\n";
            return [];
        }
    }

    /**
     * Analyzes a single file.
     */
    public function analyzeFile(
        string $itemPath,
        bool $isIgnored,
        ?NodeAnalysis $parent,
        string $ignoreReason = ''
    ): ?TextFileAnalysis {
        if (!file_exists($itemPath)) {
            echo "File not found {$itemPath}\n";
            return null;
        }

        $fileSize = filesize($itemPath);
        $isText = $this->isTextFile($itemPath);
        $content = $isText ? $this->readFileContent($itemPath) : '[Non-text file]';

        return new TextFileAnalysis(
            name: basename($itemPath),
            fileContent: $content,
            isIgnored: $isIgnored,
            parent: $parent,
            reason: $isIgnored ? $ignoreReason : (!$isText ? 'Binary file (contains null bytes)' : '')
        );
    }

    /**
     * Creates a node (file or directory) for a given path.
     */
    public function createNode(
        string $itemPath,
        IgnorePatternManager $ignorePatternManager,
        ?NodeAnalysis $parent
    ): ?NodeAnalysis {
        $isIgnored = $ignorePatternManager->shouldIgnore($itemPath);
        $reason = $isIgnored ? ($ignorePatternManager->getIgnoreReason($itemPath) ?? 'unknown') : '';

        if (is_file($itemPath)) {
            return $this->analyzeFile($itemPath, $isIgnored, $parent, $reason);
        }

        if (is_dir($itemPath)) {
            return new DirectoryAnalysis(
                name: basename($itemPath),
                isIgnored: $isIgnored,
                parent: $parent
            );
        }

        return null;
    }

    /**
     * Recursively analyzes a directory and its contents.
     */
    public function analyzeDirectory(
        string $path,
        IgnorePatternManager $ignorePatternManager,
        string $basePath,
        ?NodeAnalysis $parent = null,
        int $ignoreTopFiles = 0
    ): DirectoryAnalysis {
        if ($path === '.') {
            $path = getcwd();
        }

        $result = new DirectoryAnalysis(
            name: basename($path),
            isIgnored: $ignorePatternManager->shouldIgnore($path),
            parent: $parent
        );

        $items = $this->listDirectoryItems($path);

        foreach ($items as $itemPath) {
            $node = $this->createNode($itemPath, $ignorePatternManager, $result);
            if ($node === null) {
                continue;
            }

            if ($node instanceof DirectoryAnalysis) {
                $subdir = $this->analyzeDirectory(
                    $itemPath,
                    $ignorePatternManager,
                    $basePath,
                    $result,
                    $ignoreTopFiles
                );
                $result->children[] = $subdir;
            } else {
                $result->children[] = $node;
            }
        }

        // At root level, ignore the largest files if specified
        $isRootDir = $parent === null;
        if ($isRootDir && $ignoreTopFiles > 0) {
            $largestFiles = $result->getLargestFiles($ignoreTopFiles);
            echo "Ignoring {$ignoreTopFiles} largest files:\n";
            foreach ($largestFiles as $file) {
                echo "  {$file->getFullPath()} ({$file->getSize()} bytes)\n";
                $file->isIgnored = true;
                $file->reason = 'Excluded by --ignore-top-large-files';
            }
        }

        return $result;
    }
}
