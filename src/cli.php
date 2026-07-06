#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Codebase Digest - Generate a single-file dump of your repository for LLM input.
 *
 * Usage:
 *   php cli.php <path> [options]
 *
 * Options:
 *   -o, --output-format  Output format (text|markdown) [default: text]
 *   -f, --file          Output file name [default: <directory_name>_codebase_dump.<format_extension>]
 *   --audit-upload      Send the output to the audits API
 *   --audit-base-url    API URL to send the audit to [default: https://codeaudits.ai/]
 *   --ignore-top-large-files  Number of largest files to ignore [default: 0]
 *   --api-key           Your private API key for https://codeaudits.ai/
 *   -h, --help          Show this help message
 */

require_once __DIR__ . '/../vendor/autoload.php';

use CodebaseDump\Core\CodebaseAnalysis;
use CodebaseDump\Core\IgnorePatternManager;
use CodebaseDump\Core\AuditApiUploader;
use CodebaseDump\Core\PlainTextOutputFormatter;
use CodebaseDump\Core\MarkdownOutputFormatter;

// Parse command line arguments
$options = getopt('o:f:h', [
    'output-format:',
    'file:',
    'audit-upload',
    'audit-base-url:',
    'ignore-top-large-files:',
    'api-key:',
    'help',
], $restIndex);

$args = array_slice($argv, $restIndex);

// Show help if requested or no arguments
if (isset($options['h']) || isset($options['help']) || empty($args)) {
    echo file_get_contents(__FILE__);
    exit(empty($args) ? 1 : 0);
}

// Collect all positional path arguments (before any trailing options)
$paths = [];
$i = 0;
while ($i < count($args)) {
    $arg = $args[$i];
    
    // Handle -f or --file option
    if ($arg === '-f' || $arg === '--file') {
        if (isset($args[$i + 1])) {
            $file = $args[$i + 1];
            $i += 2;
            continue;
        }
    }
    
    // Handle -o or --output-format option
    if ($arg === '-o' || $arg === '--output-format') {
        if (isset($args[$i + 1])) {
            $outputFormat = $args[$i + 1];
            $i += 2;
            continue;
        }
    }
    
    // Otherwise it's a path argument (or an unknown option; treat as path)
    $paths[] = $arg;
    $i++;
}

// Get options - prioritize already parsed ones, then fall back to getopt results
$outputFormat = $outputFormat ?? $options['o'] ?? $options['output-format'] ?? 'text';
$file = $file ?? $options['f'] ?? $options['file'] ?? null;

$auditUpload = isset($options['audit-upload']) || isset($args[0]) && $args[0] === '--audit-upload';
$auditBaseUrl = $options['audit-base-url'] ?? 'https://codeaudits.ai/';
$ignoreTopFiles = (int) ($options['ignore-top-large-files'] ?? 0);
$apiKey = $options['api-key'] ?? null;

// Validate paths
if (empty($paths)) {
    echo "Error: At least one path argument is required.\n\n";
    echo file_get_contents(__FILE__);
    exit(1);
}

// Normalize and validate all paths
$resolvedPaths = [];
foreach ($paths as $p) {
    if ($p === '.') {
        $p = getcwd();
    }
    if (!is_dir($p)) {
        echo "Error: The specified path is not a directory: {$p}\n";
        exit(1);
    }
    $resolvedPaths[] = realpath($p);
}

$paths = array_unique($resolvedPaths);

// If only one directory, use it as base for ignore patterns
$primaryPath = $paths[0];

// Initialize components
$ignorePatternManager = new IgnorePatternManager($primaryPath);
$codebaseAnalysis = new CodebaseAnalysis();

echo "Codebase Digest\n";
if (count($paths) === 1) {
    echo "Analyzing directory: {$paths[0]}\n";
} else {
    echo "Analyzing " . count($paths) . " directories:\n";
    foreach ($paths as $p) {
        echo "  {$p}\n";
    }
}

// Analyze all directories
$data = $codebaseAnalysis->analyzeDirectories(
    $paths,
    $ignorePatternManager,
    $ignoreTopFiles
);

// Calculate estimated output size
$estimatedOutputSize = $data->getNonIgnoredTextContentSize();
$estimatedOutputSize += count($data->getAllNonIgnoredFiles()) * 100; // Assume 100 bytes per file for structure
$estimatedOutputSize += 1000; // Add 1KB for summary

echo sprintf("Estimated output size: %.2f KB\n", $estimatedOutputSize / 1024);

// Choose output formatter
$outputFormatter = match ($outputFormat) {
    'markdown' => new MarkdownOutputFormatter(),
    default => new PlainTextOutputFormatter(),
};

// Format the output
$ignorePatterns = $ignorePatternManager->getIgnorePatternsAsArray();
$output = $outputFormatter->format($data, $ignorePatterns);

// Save to file
if ($file !== null) {
    $fullPath = getcwd() . DIRECTORY_SEPARATOR . $file;
} else {
    $fileName = (count($paths) === 1 ? basename($paths[0]) : 'multi_dir_codebase_dump') . $outputFormatter->outputFileExtension();
    $fullPath = realpath(dirname($paths[0])) . DIRECTORY_SEPARATOR . $fileName;
}

// Ensure the output directory exists
$outputDir = dirname($fullPath);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Write the file with error handling
$result = file_put_contents($fullPath, $output);
if ($result === false) {
    $error = error_get_last();
    echo "Error: Failed to write file to {$fullPath}";
    if ($error) {
        echo ": " . $error['message'];
    }
    echo "\n";
    exit(1);
}

echo "\nAnalysis saved to: {$fullPath}\n";

echo "\nAnalysis Summary\n\n";
echo $outputFormatter->generateTreeString($data, showIgnored: false);
echo "\n" . $outputFormatter->generateSummaryString($data);
echo "Ignore summary:\n\n";
echo $outputFormatter->generateIgnoredFilesSummary($data, $ignorePatterns);
echo $outputFormatter->generateSkippedFilesList($data);

// Upload to audit API if requested
if ($auditUpload) {
    $appVersion = defined('CodebaseDump\VERSION') ? \CodebaseDump\VERSION : null;
    $submittedBy = $appVersion ? "codebase-dump-v{$appVersion}" : "codebase-dump";

    $auditApiUploader = new AuditApiUploader(
        apiKey: $apiKey,
        apiUrl: $auditBaseUrl,
        apiSubmittedBy: $submittedBy
    );

    try {
        $auditApiUploader->uploadAudit($output);
    } catch (\Exception $e) {
        echo "Error uploading audit: " . $e->getMessage() . "\n";
        exit(1);
    }
}
