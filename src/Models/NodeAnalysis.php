<?php

declare(strict_types=1);

namespace CodebaseDump\Models;

use Stringable;

/**
 * Abstract base class for analyzing nodes (files and directories) in a codebase.
 */
abstract class NodeAnalysis implements Stringable
{
    public function __construct(
        public string $name = '',
        public bool $isIgnored = false,
        public ?NodeAnalysis $parent = null
    ) {}

    /**
     * Returns the type of the node.
     */
    abstract public function getType(): string;

    /**
     * Returns the size of the node in bytes.
     */
    abstract public function getSize(): int;

    /**
     * Converts the node to an array.
     */
    abstract public function toArray(): array;

    /**
     * Returns the full path of the node.
     */
    public function getFullPath(): string
    {
        if ($this->parent === null) {
            return $this->name;
        }

        return $this->parent->getFullPath() . DIRECTORY_SEPARATOR . $this->name;
    }

    /**
     * String representation of the node.
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
