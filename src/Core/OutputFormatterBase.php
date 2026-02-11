<?php

declare(strict_types=1);

namespace CodebaseDump\Core;

use CodebaseDump\Models\DirectoryAnalysis;
use CodebaseDump\Models\NodeAnalysis;
use CodebaseDump\Models\TextFileAnalysis;

/**
 * Base class for output formatters.
 */
abstract class OutputFormatterBase
{
    /**
     * Gets the output file extension.
     */
    abstract public function outputFileExtension(): string;

    /**
     * Formats the analysis data as a string.
     *
     * @param array<string> $ignorePatterns
     */
    abstract public function format(DirectoryAnalysis $data, array $ignorePatterns): string;

    /**
     * Generates a tree representation of the directory structure.
     */
    public function generateTreeString(
        NodeAnalysis $node,
        string $prefix = '',
        bool $isLast = true,
        bool $showSize = false,
        bool $showIgnored = false
    ): string {
        if ($node->isIgnored && !$showIgnored) {
            return '';
        }

        $result = $prefix . ($isLast ? '└── ' : '├── ') . $node->name;

        if ($showSize && $node instanceof TextFileAnalysis) {
            $result .= " ({$node->getSize()} bytes)";
        }

        if ($node->isIgnored) {
            $result .= ' [Status: IGNORED]';
        }

        $result .= "\n";

        if ($node instanceof DirectoryAnalysis) {
            $newPrefix = $prefix . ($isLast ? '    ' : '│   ');
            $children = $node->children;
            if (!$showIgnored) {
                $children = array_filter($children, fn(NodeAnalysis $child) => !$child->isIgnored);
            }

            $childCount = count($children);
            foreach ($children as $index => $child) {
                $result .= $this->generateTreeString(
                    $child,
                    $newPrefix,
                    $index === $childCount - 1,
                    $showSize,
                    $showIgnored
                );
            }
        }

        return $result;
    }

    /**
     * Generates a tree representation readable by LLMs.
     */
    public function generateTreeStringForLLM(NodeAnalysis $node): string
    {
        if ($node->isIgnored) {
            return '';
        }

        $result = '- ' . $node->getFullPath();

        if ($node instanceof DirectoryAnalysis) {
            $result .= '/';
        }

        if ($node instanceof TextFileAnalysis) {
            $result .= " ({$node->getSize()} bytes)";
        }

        $result .= "\n";

        if ($node instanceof DirectoryAnalysis) {
            foreach ($node->children as $child) {
                $result .= $this->generateTreeStringForLLM($child);
            }
        }

        return $result;
    }

    /**
     * Generates a structured representation of file contents.
     *
     * @return array<int, array{path: string, content: string}>
     */
    public function generateContentString(NodeAnalysis $data): array
    {
        $content = [];

        $addFileContent = function (NodeAnalysis $node, string $path = '') use (&$content, &$addFileContent): void {
            if ($node instanceof TextFileAnalysis && !$node->isIgnored && $node->fileContent !== '[Non-text file]') {
                $fullPath = $path !== '' ? $path . DIRECTORY_SEPARATOR . $node->name : $node->name;
                $content[] = [
                    'path' => $fullPath,
                    'content' => $node->fileContent,
                ];
            } elseif ($node instanceof DirectoryAnalysis) {
                $newPath = $path !== '' ? $path . DIRECTORY_SEPARATOR . $node->name : $node->name;
                foreach ($node->children as $child) {
                    $addFileContent($child, $newPath);
                }
            }
        };

        $addFileContent($data);

        return $content;
    }

    /**
     * Generates a summary of the analysis.
     */
    public function generateSummaryString(DirectoryAnalysis $data): string
    {
        $output = "- Total files: " . count($data->getAllNonIgnoredFiles()) . "\n";
        $output .= "- Total directories: " . $data->getNonIgnoredDirCount() . "\n";
        $output .= "- Total text file size (including ignored): " . round($data->getSize() / 1024, 2) . " KB\n";
        $output .= "- Total tokens: " . $data->getTotalTokens() . "\n";
        $output .= "- Analyzed text content size: " . round($data->getNonIgnoredTextContentSize() / 1024, 2) . " KB\n\n";
        $output .= "Top largest non-ignored files:\n" . $this->generateTopFilesString($data->getLargestFiles()) . "\n";
        $output .= "Top largest non-ignored directories:\n" . $this->generateTopDirectoriesString($data->getLargestDirectories()) . "\n";

        return $output;
    }

    /**
     * Generates a summary of ignored files.
     *
     * @param array<string> $ignorePatterns
     */
    public function generateIgnoredFilesSummary(DirectoryAnalysis $data, array $ignorePatterns): string
    {
        $output = "During the analysis, some files were ignored:\n";
        $output .= "- No of files ignored during parsing: " . count($data->getAllIgnoredFiles()) . "\n";
        $output .= "- Patterns used to ignore files: " . implode(', ', $ignorePatterns) . "\n";

        return $output;
    }

    /**
     * Generates a string of the top largest files.
     *
     * @param array<int, TextFileAnalysis> $files
     */
    public function generateTopFilesString(array $files, string $prefix = ''): string
    {
        if (empty($files)) {
            return $prefix . "No large files found.\n";
        }

        $output = '';
        foreach ($files as $file) {
            $output .= $prefix . "- {$file->getFullPath()} (" . round($file->getSize() / 1024, 2) . " kB)\n";
        }

        return $output;
    }

    /**
     * Generates a string of the top largest directories.
     *
     * @param array<int, DirectoryAnalysis> $directories
     */
    public function generateTopDirectoriesString(array $directories, string $prefix = ''): string
    {
        if (empty($directories)) {
            return $prefix . "No large directories found.\n";
        }

        $output = '';
        foreach ($directories as $directory) {
            $output .= $prefix . "- {$directory->getFullPath()} (" . round($directory->getSize() / 1024, 2) . " kB)\n";
        }

        return $output;
    }
}
