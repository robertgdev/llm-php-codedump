<?php

declare(strict_types=1);

namespace CodebaseDump\Core;

use CodebaseDump\Models\NodeAnalysis;

/**
 * Manages ignore patterns for files and directories.
 */
class IgnorePatternManager
{
    public const DEFAULT_IGNORE_PATTERNS = [
        '*.pyc',
        '*.pyo',
        '*.pyd',
        '__pycache__',
        'node_modules',
        'bower_components',
        '.git',
        '.svn',
        '.hg',
        '.gitignore',
        'venv',
        '.venv',
        'env',
        '.idea',
        '.vscode',
        '*.log',
        '*.bak',
        '*.swp',
        '*.tmp',
        '.DS_Store',
        'Thumbs.db',
        'build',
        'dist',
        '*.egg-info',
        '*.so',
        '*.dylib',
        '*.dll',
    ];

    private string $basePath;
    private bool $loadDefaultIgnorePatterns;
    private bool $loadGitignore;
    private bool $loadCdigestignore;
    /** @var array<string> */
    private array $extraIgnorePatterns;
    /** @var array<string> */
    private array $ignorePatternsAsStr;

    /** @param array<string> $extraIgnorePatterns */
    public function __construct(
        string $basePath,
        bool $loadDefaultIgnorePatterns = true,
        bool $loadGitignore = true,
        bool $loadCdigestignore = true,
        array $extraIgnorePatterns = []
    ) {
        $this->basePath = $basePath === '.' ? getcwd() : $basePath;
        $this->loadDefaultIgnorePatterns = $loadDefaultIgnorePatterns;
        $this->loadGitignore = $loadGitignore;
        $this->loadCdigestignore = $loadCdigestignore;
        $this->extraIgnorePatterns = $extraIgnorePatterns;
        $this->ignorePatternsAsStr = [];

        $this->initIgnorePatterns();
    }

    /**
     * Initializes the ignore patterns based on the configuration.
     */
    private function initIgnorePatterns(): void
    {
        if ($this->loadDefaultIgnorePatterns) {
            foreach (self::DEFAULT_IGNORE_PATTERNS as $pattern) {
                $this->ignorePatternsAsStr[] = $pattern;
            }
        }

        foreach ($this->extraIgnorePatterns as $pattern) {
            $this->ignorePatternsAsStr[] = $pattern;
        }

        $cdigestignorePath = $this->basePath . DIRECTORY_SEPARATOR . '.cdigestignore';
        if ($this->loadCdigestignore && file_exists($cdigestignorePath)) {
            $this->parseGitignoreFile($cdigestignorePath);
        }

        $gitignorePath = $this->basePath . DIRECTORY_SEPARATOR . '.gitignore';
        if ($this->loadGitignore && file_exists($gitignorePath)) {
            $this->parseGitignoreFile($gitignorePath);
        }
    }

    /**
     * Parses a .gitignore file and adds patterns to the ignore list.
     */
    private function parseGitignoreFile(string $gitignorePath): void
    {
        $lines = file($gitignorePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $this->ignorePatternsAsStr[] = $line;
        }
    }

    /**
     * Checks if a path should be ignored.
     */
    public function shouldIgnore(string $path): bool
    {
        return $this->getIgnoreReason($path) !== null;
    }

    /**
     * Checks if a path should be ignored and returns the matching pattern.
     *
     * @return string|null The matching pattern, or null if not ignored.
     */
    public function getIgnoreReason(string $path): ?string
    {
        $normalizedPath = $this->normalizePath($path);
        $relativePath = $this->getRelativePath($normalizedPath);

        foreach ($this->ignorePatternsAsStr as $pattern) {
            if ($this->matchesPattern($pattern, $relativePath, $normalizedPath)) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * Normalizes a path for consistent matching.
     */
    private function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Gets the relative path from the base path.
     */
    private function getRelativePath(string $fullPath): string
    {
        $basePath = $this->normalizePath($this->basePath);

        if (str_starts_with($fullPath, $basePath)) {
            $relative = substr($fullPath, strlen($basePath));
            return ltrim($relative, '/');
        }

        return basename($fullPath);
    }

    /**
     * Checks if a path matches an ignore pattern.
     */
    private function matchesPattern(string $pattern, string $relativePath, string $fullPath): bool
    {
        // Handle directory patterns
        if (str_ends_with($pattern, '/')) {
            $pattern = rtrim($pattern, '/');
            return $this->fnmatch($pattern . '/*', $relativePath) ||
                   $this->fnmatch($pattern . '/*', $fullPath) ||
                   stripos($relativePath, $pattern . '/') !== false;
        }

        // Handle **/*.ext patterns (with wildcard extension) - check before simple **/pattern
        if (str_contains($pattern, '**/')) {
            $patternPart = str_replace('**/', '', $pattern);
            // Match against basename (e.g., *.tmp matches file.tmp)
            if ($this->fnmatch($patternPart, basename($relativePath)) ||
                $this->fnmatch($patternPart, basename($fullPath))) {
                return true;
            }
            // Also match the pattern anywhere in the path
            return $this->fnmatch('**/' . $patternPart, $relativePath) ||
                   $this->fnmatch('**/' . $patternPart, $fullPath);
        }

        // Handle recursive patterns like **/logs (without wildcard after **/)
        if (str_starts_with($pattern, '**/')) {
            $patternPart = substr($pattern, 3);
            return stripos($relativePath, $patternPart) !== false ||
                   stripos($fullPath, $patternPart) !== false;
        }

        // Regular file name pattern - check full relative path, basename, full path, and parent directories
        // Also check each directory component in the path for directory patterns like __pycache__
        $dirs = explode('/', dirname($relativePath));
        foreach ($dirs as $dir) {
            if ($dir !== '' && $this->fnmatch($pattern, $dir)) {
                return true;
            }
        }
        return $this->fnmatch($pattern, $relativePath) ||
               $this->fnmatch($pattern, basename($relativePath)) ||
               $this->fnmatch($pattern, $fullPath) ||
               $this->fnmatch($pattern, basename($fullPath));
    }

    /**
     * Simple fnmatch implementation for ignore patterns.
     */
    private function fnmatch(string $pattern, string $string): bool
    {
        // Convert glob pattern to regex
        // First replace glob wildcards with placeholders to avoid escaping issues
        $regex = str_replace('*', 'STAR', $pattern);
        $regex = str_replace('?', 'QMARK', $regex);
        
        // Then escape regex special chars
        $regex = preg_replace_callback('/[.^+$\[\]\\\\(){}|-]/', function ($matches) {
            return '\\' . $matches[0];
        }, $regex);
        
        // Also escape # (our regex delimiter) if present in the pattern
        $regex = str_replace('#', '\\#', $regex);

        // Replace placeholders with regex
        $regex = str_replace('STAR', '.*', $regex);
        $regex = str_replace('QMARK', '.', $regex);

        return (bool) preg_match('#^' . $regex . '$#i', $string);
    }

    /**
     * Gets all ignore patterns as a string array.
     *
     * @return array<string>
     */
    public function getIgnorePatternsAsArray(): array
    {
        return $this->ignorePatternsAsStr;
    }

    /**
     * Gets the base path.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
