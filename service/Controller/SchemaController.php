<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use DateTimeInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Wundii\Flowcrafter\Interface\ProjectionHandlerInterface;
use Wundii\Flowcrafter\Interface\StepInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Storage\Entity\StepSourceEntity;

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

    public function stepSource(Request $request): JsonResponse
    {
        $className = $request->query->get('className', '');
        $stepHash = $request->query->get('stepHash', '');

        $className = $className && !str_starts_with($className, '\\') ? '\\' . $className : $className;
        if (class_exists($className)) {
            if (!is_subclass_of($className, StepInterface::class)) {
                return new JsonResponse([
                    'error' => 'The class does not implement StepInterface',
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

        $stepSourceEntity = $this->storage->findStepSourceByHash($stepHash);
        if (!$stepSourceEntity instanceof StepSourceEntity) {
            return new JsonResponse([
                'error' => 'Step source not found',
            ], 404);
        }

        $current = class_exists($stepSourceEntity->stepSource);
        $source = $stepSourceEntity->sourceContent;

        if ($current) {
            $ref = new ReflectionClass($stepSourceEntity->stepSource);
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

    public function projectionHandlerSource(Request $request): JsonResponse
    {
        $className = $request->query->get('className', '');
        $className = $className && !str_starts_with($className, '\\') ? '\\' . $className : $className;

        if (!class_exists($className)) {
            return new JsonResponse([
                'error' => 'Projection handler class not found',
            ], 404);
        }

        if (!is_subclass_of($className, ProjectionHandlerInterface::class)) {
            return new JsonResponse([
                'error' => 'The class does not implement ProjectionHandlerInterface',
            ], 400);
        }

        $reflectionClass = new ReflectionClass($className);
        $file = (string) $reflectionClass->getFileName();

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

    public function messageSources(): JsonResponse
    {
        $messageSources = $this->storage->findAllMessageSources();

        return new JsonResponse(iterator_to_array($messageSources));
    }

    public function stepSources(Request $request): JsonResponse
    {
        $stepSource = $request->query->get('stepSource', '');

        /** @var class-string $stepSource */
        $stepSources = $this->storage->findStepSourcesByStepSource($stepSource);

        $result = [];
        foreach ($stepSources as $stepSource) {
            $current = class_exists($stepSource->stepSource);
            $source = $stepSource->sourceContent;

            if ($current) {
                $ref = new ReflectionClass($stepSource->stepSource);
                $file = (string) $ref->getFileName();

                $current = file_exists($file);
                $currentSource = file_get_contents($file);
                $current = $current && is_string($currentSource);
                $current = $current && $source === $currentSource;
            }

            $result[] = [
                'current' => $current,
                'source' => $source,
                'time' => $stepSource->time->format(DateTimeInterface::RFC3339_EXTENDED),
            ];
        }

        return new JsonResponse($result);
    }
}
