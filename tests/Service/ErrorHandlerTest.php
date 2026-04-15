<?php

declare(strict_types=1);

namespace Tests\Service;

use Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Wundii\Service\ErrorHandler;

final class ErrorHandlerTest extends TestCase
{
    public function testHandleReturns500(): void
    {
        $runtimeException = new RuntimeException('something went wrong');
        $jsonResponse = ErrorHandler::handle($runtimeException);

        $this->assertSame(500, $jsonResponse->getStatusCode());
    }

    public function testHandleContainsErrorMessage(): void
    {
        $runtimeException = new RuntimeException('my error message');
        $jsonResponse = ErrorHandler::handle($runtimeException);

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('my error message', $data['error']);
    }

    public function testHandleContainsFileAndLine(): void
    {
        $runtimeException = new RuntimeException('test');
        $jsonResponse = ErrorHandler::handle($runtimeException);

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('file', $data);
        $this->assertArrayHasKey('line', $data);
        $this->assertIsString($data['file']);
        $this->assertIsInt($data['line']);
    }

    public function testHandleContainsTrace(): void
    {
        $runtimeException = new RuntimeException('test');
        $jsonResponse = ErrorHandler::handle($runtimeException);

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('trace', $data);
        $this->assertIsString($data['trace']);
    }

    public function testHandleIncludesFileContextForExistingFile(): void
    {
        // throw here so file/line point to this actual source file
        try {
            throw new RuntimeException('context test');
        } catch (RuntimeException $runtimeException) {
        }

        $jsonResponse = ErrorHandler::handle($runtimeException);

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsArray($data['fileContext']);
        $this->assertNotEmpty($data['fileContext']);

        $firstLine = $data['fileContext'][0];
        $this->assertArrayHasKey('number', $firstLine);
        $this->assertArrayHasKey('content', $firstLine);
        $this->assertArrayHasKey('highlighted', $firstLine);
    }

    public function testHandleHighlightsCorrectLine(): void
    {
        try {
            throw new RuntimeException('highlight test');
        } catch (RuntimeException $runtimeException) {
        }

        $jsonResponse = ErrorHandler::handle($runtimeException);

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertIsArray($data['fileContext']);

        $highlightedLines = array_filter(
            $data['fileContext'],
            static fn (mixed $contextLine): bool => is_array($contextLine) && $contextLine['highlighted'] === true,
        );

        $this->assertCount(1, $highlightedLines);

        $highlighted = array_values($highlightedLines)[0];
        $this->assertSame($runtimeException->getLine(), $highlighted['number']);
    }

    public function testHandleReturnsNullFileContextForUnknownFile(): void
    {
        // Throw from a temp file, then delete it so the file no longer exists when handle() runs
        $tmpFile = tempnam(sys_get_temp_dir(), 'flowcrafter_test_');
        $this->assertIsString($tmpFile);
        file_put_contents($tmpFile, '<?php throw new \RuntimeException("no-context error");');

        $throwable = null;
        try {
            include $tmpFile;
        } catch (RuntimeException $runtimeException) {
            $throwable = $runtimeException;
        }

        unlink($tmpFile);

        $this->assertInstanceOf(RuntimeException::class, $throwable);
        $jsonResponse = ErrorHandler::handle($throwable);

        $data = json_decode((string) $jsonResponse->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNull($data['fileContext']);
    }

    public function testCreateReturnsCallable(): void
    {
        $closure = ErrorHandler::create();

        $this->assertIsCallable($closure);
    }

    public function testCreateReturnedClosureProducesResponse(): void
    {
        $closure = ErrorHandler::create();
        $response = $closure(new Exception('closure error'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('closure error', (string) $response->getContent());
    }
}
