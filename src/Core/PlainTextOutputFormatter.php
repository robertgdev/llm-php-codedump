<?php

declare(strict_types=1);

namespace CodebaseDump\Core;

use CodebaseDump\Models\DirectoryAnalysis;

/**
 * Formats analysis output as plain text.
 */
class PlainTextOutputFormatter extends OutputFormatterBase
{
    /**
     * {@inheritdoc}
     */
    public function outputFileExtension(): string
    {
        return '.txt';
    }

    /**
     * {@inheritdoc}
     */
    public function format(DirectoryAnalysis $data, array $ignorePatterns): string
    {
        $output = "Parsed codebase for the project: {$data->name}\n\n";
        $output .= "\nDirectory Structure:\n";
        $output .= $this->generateTreeStringForLLM($data);
        $output .= "\n\n";
        $output .= "Summary\n\n";
        $output .= $this->generateSummaryString($data);
        $output .= "Ignore summary:\n";
        $output .= $this->generateIgnoredFilesSummary($data, $ignorePatterns);
        $output .= "\n" . $this->generateSkippedFilesList($data);
        $output .= "Files:\n\n";

        foreach ($this->generateContentString($data) as $file) {
            $output .= "\n\n========== File: {$file['path']} ==========\n\n";
            $output .= $file['content'];
            $output .= "\n\n";
        }

        return $output;
    }
}
