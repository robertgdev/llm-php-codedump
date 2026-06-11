<?php

declare(strict_types=1);

namespace CodebaseDump\Core;

use CodebaseDump\Models\DirectoryAnalysis;

/**
 * Formats analysis output as Markdown.
 */
class MarkdownOutputFormatter extends OutputFormatterBase
{
    /**
     * {@inheritdoc}
     */
    public function outputFileExtension(): string
    {
        return '.md';
    }

    /**
     * {@inheritdoc}
     */
    public function format(DirectoryAnalysis $data, array $ignorePatterns): string
    {
        $output = "# Parsed codebase for the project: {$data->name}\n\n";
        $output .= "\n## Directory Structure\n";
        $output .= $this->generateTreeStringForLLM($data);
        $output .= "\n## Summary\n";
        $output .= $this->generateSummaryString($data);
        $output .= "\n## Ignore summary:\n";
        $output .= $this->generateIgnoredFilesSummary($data, $ignorePatterns);
        $output .= "\n" . $this->generateSkippedFilesList($data);
        $output .= "## Files:\n";

        foreach ($this->generateContentString($data) as $file) {
            $output .= "### {$file['path']}\n\n";
            $output .= "```\n{$file['content']}\n```\n\n";
        }

        return $output;
    }
}
