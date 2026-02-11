<?php

declare(strict_types=1);

namespace CodebaseDump\Models;

/**
 * Represents analysis of a text file.
 */
class TextFileAnalysis extends NodeAnalysis
{
    public function __construct(
        string $name = '',
        public string $fileContent = '',
        bool $isIgnored = false,
        ?NodeAnalysis $parent = null
    ) {
        parent::__construct($name, $isIgnored, $parent);
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'text_file';
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): int
    {
        return strlen($this->fileContent);
    }

    /**
     * Counts the number of tokens in the file content.
     * Uses a simple approximation: splits on whitespace.
     * For more accurate results, consider using a proper tokenizer library.
     */
    public function countTokens(): int
    {
        if (empty($this->fileContent)) {
            return 0;
        }

        // Simple whitespace tokenization as approximation
        // For better accuracy, use a library like openai-php/tiktoken
        $tokens = preg_split('/\s+/', $this->fileContent, -1, PREG_SPLIT_NO_EMPTY);

        return count($tokens);
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
            'content' => $this->fileContent,
        ];
    }
}
