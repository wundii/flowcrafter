<?php

declare(strict_types=1);

namespace Wundii\Flower;

use JsonSerializable;
use Throwable;

final readonly class ThrowableEntity implements JsonSerializable
{
    /**
     * @param list<array{number: int, content: string, highlighted: bool}>|null $fileContext
     */
    private function __construct(
        public string $error,
        public string $file,
        public int $line,
        public string $trace,
        public ?array $fileContext,
    ) {
    }

    public static function create(Throwable $throwable): self
    {
        $root = $throwable->getPrevious() ?? $throwable;
        $file = $root->getFile();
        $line = $root->getLine();

        return new self(
            error: $throwable->getMessage(),
            file: $file,
            line: $line,
            trace: $throwable->getTraceAsString(),
            fileContext: self::buildFileContext($file, $line),
        );
    }

    /**
     * @return array{error: string, file: string, line: int, trace: string, fileContext: list<array{number: int, content: string, highlighted: bool}>|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'error' => $this->error,
            'file' => $this->file,
            'line' => $this->line,
            'trace' => $this->trace,
            'fileContext' => $this->fileContext,
        ];
    }

    /**
     * @return list<array{number: int, content: string, highlighted: bool}>|null
     */
    private static function buildFileContext(string $file, int $line): ?array
    {
        if ($file === '' || !file_exists($file) || !is_readable($file)) {
            return null;
        }

        $fileLines = file($file, FILE_IGNORE_NEW_LINES);
        if ($fileLines === false) {
            return null;
        }

        $start = max(0, $line - 6);
        $end = min(count($fileLines) - 1, $line + 4);

        $contextLines = [];
        for ($i = $start; $i <= $end; ++$i) {
            $contextLines[] = [
                'number' => $i + 1,
                'content' => $fileLines[$i],
                'highlighted' => ($i + 1) === $line,
            ];
        }

        return $contextLines;
    }
}
