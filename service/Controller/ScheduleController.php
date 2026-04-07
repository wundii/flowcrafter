<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use ReflectionClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;
use Wundii\Flowcrafter\Schedule\ScheduleDiscovery;

final class ScheduleController
{
    public function list(): JsonResponse
    {
        $schedules = ScheduleDiscovery::discover();

        $result = [];
        foreach ($schedules as $className => $attribute) {
            $result[] = [
                'className' => $className,
                'name' => $attribute->name,
                'expression' => $attribute->expression,
            ];
        }

        usort($result, static fn (array $a, array $b): int => strcasecmp($a['name'] ?? $a['className'], $b['name'] ?? $b['className']));

        return new JsonResponse($result);
    }

    public function source(Request $request): JsonResponse
    {
        $className = $request->query->get('className', '');
        $resolvedClassName = $className && !str_starts_with($className, '\\') ? '\\' . $className : $className;

        if (!class_exists($resolvedClassName)) {
            return new JsonResponse([
                'error' => 'Class not found',
            ], 404);
        }

        if (!is_subclass_of($resolvedClassName, AbstractSchedule::class)) {
            return new JsonResponse([
                'error' => 'The class does not extend AbstractSchedule',
            ], 400);
        }

        $reflectionClass = new ReflectionClass($resolvedClassName);
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
            'className' => $className,
            'source' => $content,
        ]);
    }
}
