# Codebase Dump (PHP)

An AI-assisted PHP 8.4 port of the Python [codebase-dump](https://github.com/frogermcs/codebase-dump) tool. It generates a single-file dump of your repository for LLM input.

## Rationale

I was frustrated that I didn't find a native PHP tool for this task. The tools I did find, were written in Python, NodeJS and Rust and all of them came 
with environment/dependency issues I didn't want to deal with.  With the help of some AI coding tools, I generated this port. It hasn't been tested 
extensively, but I've used it on a few projects and it seemed to work fine. 

## Description

This tool analyzes a codebase directory and generates a structured text or markdown representation that can be used as input for Large Language Models (LLMs). It:

- Recursively analyzes directory structures
- Identifies and ignores common non-source files (compiled code, dependencies, etc.)
- Reads text file contents
- Generates tree representations
- Calculates file sizes and estimates token counts
- Supports custom ignore patterns (via `.gitignore` and `.cdigestignore`)
- Optionally uploads results to the [Code Audits API](https://codeaudits.ai/)

## Requirements

- PHP 8.4 or higher
- Composer for dependency management

## Installation

```bash
# Install dependencies
cd php
composer install
```

## Usage

### Basic Usage

```bash
php src/cli.php /path/to/your/project
```

### Command Line Options

```bash
php src/cli.php <path> [options]

Options:
  -o, --output-format      Output format (text|markdown) [default: text]
  -f, --file               Output file name
  --audit-upload           Send the output to the audits API
  --audit-base-url         API URL [default: https://codeaudits.ai/]
  --ignore-top-large-files Number of largest files to ignore [default: 0]
  --api-key               Your private API key for https://codeaudits.ai/
  -h, --help              Show this help message
```

### Examples

```bash
# Analyze current directory with markdown output
php src/cli.php . -o markdown

# Save to specific file
php src/cli.php /path/to/project -f mydump.txt

# Ignore the 5 largest files
php src/cli.php /path/to/project --ignore-top-large-files 5

# Upload to Code Audits API
php src/cli.php /path/to/project --audit-upload --api-key YOUR_API_KEY
```

## Features

### Ignore Patterns

The tool supports the following default ignore patterns:

- **Python**: `*.pyc`, `*.pyo`, `*.pyd`, `__pycache__`
- **JavaScript**: `node_modules`, `bower_components`
- **Version Control**: `.git`, `.svn`, `.hg`, `.gitignore`
- **Virtual Environments**: `venv`, `.venv`, `env`
- **IDE**: `.idea`, `.vscode`
- **Temporary Files**: `*.log`, `*.bak`, `*.swp`, `*.tmp`
- **OS Files**: `.DS_Store`, `Thumbs.db`
- **Build**: `build`, `dist`, `*.egg-info`
- **Compiled Libraries**: `*.so`, `*.dylib`, `*.dll`

Additionally, the tool reads patterns from:

- `.gitignore` - Standard Git ignore patterns
- `.cdigestignore` - Custom ignore file (same format as `.gitignore`)

### Output Formats

#### Text Format (Default)

Generates a plain text file with:
- Directory structure tree
- Summary statistics
- List of ignored files
- Full file contents

#### Markdown Format

Generates a Markdown file with:
- Directory structure tree
- Summary statistics
- List of ignored files
- File contents in code blocks

## API Usage

You can also use the library programmatically in your PHP code:

```php
<?php

require_once 'vendor/autoload.php';

use CodebaseDump\Core\CodebaseAnalysis;
use CodebaseDump\Core\IgnorePatternManager;
use CodebaseDump\Core\PlainTextOutputFormatter;

$path = '/path/to/your/project';

$ignorePatternManager = new IgnorePatternManager($path);
$codebaseAnalysis = new CodebaseAnalysis();

$data = $codebaseAnalysis->analyzeDirectory(
    path: $path,
    ignorePatternManager: $ignorePatternManager,
    basePath: $path
);

$formatter = new PlainTextOutputFormatter();
$output = $formatter->format($data, $ignorePatternManager->getIgnorePatternsAsArray());

file_put_contents('output.txt', $output);
```

## Development

### Running Tests

```bash
# Run all tests
cd php
composer test

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

### Project Structure

```
php/
├── src/
│   ├── Core/
│   │   ├── AuditApiUploader.php      # API upload functionality
│   │   ├── CodebaseAnalysis.php      # Directory/file analysis
│   │   ├── IgnorePatternManager.php  # Pattern matching
│   │   ├── OutputFormatterBase.php    # Base formatter class
│   │   ├── PlainTextOutputFormatter.php
│   │   └── MarkdownOutputFormatter.php
│   ├── Models/
│   │   ├── NodeAnalysis.php          # Abstract base class
│   │   ├── TextFileAnalysis.php      # Text file model
│   │   └── DirectoryAnalysis.php     # Directory model
│   ├── _version.php
│   └── cli.php                       # CLI application
├── tests/
│   ├── TestCase.php
│   ├── NodeModelsTest.php
│   ├── CodebaseAnalysisTest.php
│   ├── IgnorePatternManagerTest.php
│   └── AuditApiUploaderTest.php
├── composer.json
├── phpunit.xml
└── README.md
```

## Differences from Python Version

- **Token Counting**: Uses simple whitespace tokenization. For more accurate token counts (matching GPT models), consider integrating a library like `openai-php/tiktoken`.
- **Pattern Matching**: Implements basic fnmatch-style pattern matching. The Python version uses `py_walk` for more advanced glob patterns.

## License

MIT
