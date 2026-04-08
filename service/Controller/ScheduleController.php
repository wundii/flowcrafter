<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use ReflectionClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Wundii\Flowcrafter\Attribute\FlowSchedule;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\FlowContainerFactory;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Schedule\AbstractSchedule;
use Wundii\Flowcrafter\Schedule\ScheduleDiscovery;

final class ScheduleController
{
    public function __construct(
        private readonly FlowcrafterConfig $flowcrafterConfig,
        private readonly StorageInterface $storage,
    ) {
    }

    public function list(): JsonResponse
    {
        $schedules = ScheduleDiscovery::discover();

        $result = [];
        foreach ($schedules as $className => $attribute) {
            $result[] = [
                'className' => $className,
                'name' => $attribute->name,
                'expression' => $attribute->expression,
                'group' => $attribute->group,
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

    public function run(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
            ], 400);
        }

        $className = $body['className'] ?? '';
        if (!is_string($className) || $className === '') {
            return new JsonResponse([
                'error' => 'className is required',
            ], 400);
        }

        $resolvedClassName = ltrim($className, '\\');

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
        $attributes = $reflectionClass->getAttributes(FlowSchedule::class);
        if ($attributes === []) {
            return new JsonResponse([
                'error' => 'The class does not have a FlowSchedule attribute',
            ], 400);
        }

        $dependenciesInjection = $this->flowcrafterConfig->getDependencyInjections();

        try {
            $containerBuilder = FlowContainerFactory::build(
                autowireClasses: [$resolvedClassName],
                dependencies: $dependenciesInjection,
            );

            $schedule = $containerBuilder->get($resolvedClassName);

            if (!$schedule instanceof AbstractSchedule) {
                return new JsonResponse([
                    'error' => 'Failed to instantiate schedule',
                ], 500);
            }

            $schedule->setContext($this->storage, $dependenciesInjection);

            ob_start();
            $schedule->process();
        } catch (Throwable $throwable) {
            return new JsonResponse([
                'error' => $throwable->getMessage(),
            ], 500);
        } finally {
            ob_end_clean();
        }

        return new JsonResponse([
            'success' => true,
        ]);
    }
}
