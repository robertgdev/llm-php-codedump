<?php

declare(strict_types=1);

namespace CodebaseDump\Models;

/**
 * Represents analysis of a directory and its contents.
 */
class DirectoryAnalysis extends NodeAnalysis
{
    /**
     * @param array<int, NodeAnalysis> $children
     */
    public function __construct(
        string $name = '',
        bool $isIgnored = false,
        ?NodeAnalysis $parent = null,
        public array $children = []
    ) {
        parent::__construct($name, $isIgnored, $parent);
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'directory';
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): int
    {
        $size = 0;
        foreach ($this->children as $child) {
            $size += $child->getSize();
        }

        return $size;
    }

    /**
     * Gets all children recursively.
     *
     * @return array<int, NodeAnalysis>
     */
    public function getAllChildren(): array
    {
        $allChildren = [];
        foreach ($this->children as $child) {
            $allChildren[] = $child;
            if ($child instanceof DirectoryAnalysis) {
                $allChildren = array_merge($allChildren, $child->getAllChildren());
            }
        }

        return $allChildren;
    }

    /**
     * Gets the count of non-ignored files.
     */
    public function getNonIgnoredFileCount(): int
    {
        return count($this->getAllNonIgnoredFiles());
    }

    /**
     * Gets the count of non-ignored directories.
     */
    public function getNonIgnoredDirCount(): int
    {
        return count($this->getAllNonIgnoredDirectories());
    }

    /**
     * Gets the total number of tokens in non-ignored files.
     */
    public function getTotalTokens(): int
    {
        $tokens = 0;
        foreach ($this->children as $child) {
            if ($child->isIgnored) {
                continue;
            }

            if ($child instanceof TextFileAnalysis) {
                $tokens += $child->countTokens();
            } elseif ($child instanceof DirectoryAnalysis) {
                $tokens += $child->getTotalTokens();
            }
        }

        return $tokens;
    }

    /**
     * Gets the total size of non-ignored text content in bytes.
     */
    public function getNonIgnoredTextContentSize(): int
    {
        $size = 0;
        foreach ($this->children as $child) {
            if ($child->isIgnored) {
                continue;
            }

            if ($child instanceof TextFileAnalysis && $child->fileContent !== '') {
                $size += strlen($child->fileContent);
            } elseif ($child instanceof DirectoryAnalysis) {
                $size += $child->getNonIgnoredTextContentSize();
            }
        }

        return $size;
    }

    /**
     * Gets all non-ignored files recursively.
     *
     * @return array<int, TextFileAnalysis>
     */
    public function getAllNonIgnoredFiles(): array
    {
        $files = [];
        foreach ($this->getAllChildren() as $child) {
            if ($child instanceof TextFileAnalysis && !$child->isIgnored) {
                $files[] = $child;
            }
        }

        return $files;
    }

    /**
     * Gets all ignored files recursively.
     *
     * @return array<int, TextFileAnalysis>
     */
    public function getAllIgnoredFiles(): array
    {
        $files = [];
        foreach ($this->getAllChildren() as $child) {
            if ($child instanceof TextFileAnalysis && $child->isIgnored) {
                $files[] = $child;
            }
        }

        return $files;
    }

    /**
     * Gets all non-ignored directories recursively.
     *
     * @return array<int, DirectoryAnalysis>
     */
    public function getAllNonIgnoredDirectories(): array
    {
        $directories = [];
        foreach ($this->getAllChildren() as $child) {
            if ($child instanceof DirectoryAnalysis && !$child->isIgnored) {
                $directories[] = $child;
            }
        }

        return $directories;
    }

    /**
     * Gets all ignored directories recursively.
     *
     * @return array<int, DirectoryAnalysis>
     */
    public function getAllIgnoredDirectories(): array
    {
        $directories = [];
        foreach ($this->getAllChildren() as $child) {
            if ($child instanceof DirectoryAnalysis && $child->isIgnored) {
                $directories[] = $child;
            }
        }

        return $directories;
    }

    /**
     * Gets the n largest non-ignored files.
     *
     * @return array<int, TextFileAnalysis>
     */
    public function getLargestFiles(int $n = 10): array
    {
        $allFiles = $this->getAllNonIgnoredFiles();
        usort($allFiles, fn(TextFileAnalysis $a, TextFileAnalysis $b) => $b->getSize() - $a->getSize());

        return array_slice($allFiles, 0, $n);
    }

    /**
     * Gets the n largest non-ignored directories.
     *
     * @return array<int, DirectoryAnalysis>
     */
    public function getLargestDirectories(int $n = 10): array
    {
        $allDirectories = $this->getAllNonIgnoredDirectories();
        usort($allDirectories, fn(DirectoryAnalysis $a, DirectoryAnalysis $b) => $b->getSize() - $a->getSize());

        return array_slice($allDirectories, 0, $n);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->getType(),
            'size' => $this->getSize(),
            'is_ignored' => $this->isIgnored,
            'non_ignored_text_content_size' => $this->getNonIgnoredTextContentSize(),
            'total_tokens' => $this->getTotalTokens(),
            'file_count' => $this->getNonIgnoredFileCount(),
            'dir_count' => $this->getNonIgnoredDirCount(),
            'children' => array_map(fn(NodeAnalysis $child) => $child->toArray(), $this->children),
        ];
    }
}
