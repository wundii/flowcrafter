<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use DateTimeInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Interface\StubInterface;
use Wundii\Flowcrafter\Storage\Entity\StubSourceEntity;

final class SchemaController
{
    public function __construct(
        private readonly StorageInterface $storage,
    ) {
    }

    public function list(): JsonResponse
    {
        $schemas = $this->storage->findAllSchemas();

        return new JsonResponse(iterator_to_array($schemas));
    }

    public function stubSource(Request $request): JsonResponse
    {
        $className = $request->query->get('className', '');
        $stubHash = $request->query->get('stubHash', '');

        $className = $className && !str_starts_with($className, '\\') ? '\\' . $className : $className;
        if (class_exists($className)) {
            if (!is_subclass_of($className, StubInterface::class)) {
                return new JsonResponse([
                    'error' => 'The class does not implement StubInterface',
                ], 400);
            }

            $ref = new ReflectionClass($className);
            $file = (string) $ref->getFileName();

            if (!file_exists($file)) {
                return new JsonResponse([
                    'error' => 'The file not found',
                ], 400);
            }

            $content = file_get_contents($file);
            if (!is_string($content)) {
                return new JsonResponse([
                    'error' => 'The file could not be read.',
                ], 400);
            }

            return new JsonResponse([
                'current' => true,
                'source' => $content,
            ]);
        }

        $stubSourceEntity = $this->storage->findStubSourceByHash($stubHash);
        if (!$stubSourceEntity instanceof StubSourceEntity) {
            return new JsonResponse([
                'error' => 'Stub source not found',
            ], 404);
        }

        $current = class_exists($stubSourceEntity->stubSource);
        $source = $stubSourceEntity->sourceContent;

        if ($current) {
            $ref = new ReflectionClass($stubSourceEntity->stubSource);
            $file = (string) $ref->getFileName();

            $current = file_exists($file);
            $currentSource = file_get_contents($file);
            $current = $current && is_string($currentSource);
            $current = $current && $source === $currentSource;
        }

        return new JsonResponse([
            'current' => $current,
            'source' => $source,
        ]);
    }

    public function messageSources(): JsonResponse
    {
        $messageSources = $this->storage->findAllMessageSources();

        return new JsonResponse(iterator_to_array($messageSources));
    }

    public function stubSources(Request $request): JsonResponse
    {
        $stubSource = $request->query->get('stubSource', '');

        /** @var class-string $stubSource */
        $stubSources = $this->storage->findStubSourcesByStubSource($stubSource);

        $result = [];
        foreach ($stubSources as $stubSource) {
            $current = class_exists($stubSource->stubSource);
            $source = $stubSource->sourceContent;

            if ($current) {
                $ref = new ReflectionClass($stubSource->stubSource);
                $file = (string) $ref->getFileName();

                $current = file_exists($file);
                $currentSource = file_get_contents($file);
                $current = $current && is_string($currentSource);
                $current = $current && $source === $currentSource;
            }

            $result[] = [
                'current' => $current,
                'source' => $source,
                'time' => $stubSource->time->format(DateTimeInterface::RFC3339_EXTENDED),
            ];
        }

        return new JsonResponse($result);
    }
}
