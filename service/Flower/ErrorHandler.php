<?php

declare(strict_types=1);

namespace Wundii\Flower;

use Closure;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ErrorHandler
{
    public static function handle(Throwable $throwable): JsonResponse
    {
        $file = $throwable->getFile();
        $line = $throwable->getLine();

        $fileContext = null;
        if ($file !== '' && file_exists($file) && is_readable($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if ($lines !== false) {
                $start = max(0, $line - 6);
                $end = min(count($lines) - 1, $line + 4);
                $contextLines = [];
                for ($i = $start; $i <= $end; ++$i) {
                    $contextLines[] = [
                        'number' => $i + 1,
                        'content' => $lines[$i],
                        'highlighted' => ($i + 1) === $line,
                    ];
                }

                $fileContext = $contextLines;
            }
        }

        return new JsonResponse([
            'error' => $throwable->getMessage(),
            'file' => $file,
            'line' => $line,
            'trace' => $throwable->getTraceAsString(),
            'fileContext' => $fileContext,
        ], 500);
    }

    /**
     * @return Closure(Throwable): Response
     */
    public static function create(): Closure
    {
        return static fn (Throwable $throwable): Response => self::handle($throwable);
    }
}
