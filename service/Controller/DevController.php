<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use ReflectionClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Wundii\Flowcrafter\Attribute\FlowGroup;
use Wundii\Flowcrafter\ClassResolver;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\StorageInterface;
use Wundii\Flowcrafter\Source;
use Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity;

final class DevController
{
    public function __construct(
        private readonly FlowcrafterConfig $flowcrafterConfig,
        private readonly StorageInterface $storage,
    ) {
    }

    public function flows(): JsonResponse
    {
        if (!$this->flowcrafterConfig->getServerDev()) {
            return new JsonResponse([
                'error' => 'Dev mode is not enabled',
            ], 403);
        }

        $result = [];

        foreach (ClassResolver::resolve() as $className) {
            if (!is_a($className, FlowInterface::class, true)) {
                continue;
            }

            $reflectionClass = new ReflectionClass($className);
            $attributes = $reflectionClass->getAttributes(FlowGroup::class);
            $group = $attributes !== [] ? $attributes[0]->newInstance()->name : null;

            try {
                $type = $className::schema()->type();
            } catch (Throwable) {
                $type = null;
            }

            $file = $reflectionClass->getFileName();

            $result[] = [
                'className' => $className,
                'type' => $type,
                'group' => $group,
                'file' => $file !== false ? $file : null,
            ];
        }

        usort($result, static fn (array $a, array $b): int => strcasecmp($a['className'], $b['className']));

        return new JsonResponse($result);
    }

    public function flow(Request $request): JsonResponse
    {
        if (!$this->flowcrafterConfig->getServerDev()) {
            return new JsonResponse([
                'error' => 'Dev mode is not enabled',
            ], 403);
        }

        $className = $request->query->getString('className');
        $resolvedClassName = ltrim($className, '\\');

        if ($resolvedClassName === '') {
            return new JsonResponse([
                'error' => 'className is required',
            ], 400);
        }

        if (!class_exists($resolvedClassName)) {
            return new JsonResponse([
                'error' => 'Class not found',
            ], 404);
        }

        if (!is_a($resolvedClassName, FlowInterface::class, true)) {
            return new JsonResponse([
                'error' => 'Class does not implement FlowInterface',
            ], 400);
        }

        try {
            /** @var class-string<FlowInterface> $resolvedClassName */
            $schema = $resolvedClassName::schema();
            $hash = $schema->getHash();
            $type = $schema->type();

            $storedHash = null;
            $storedStubs = null;
            foreach ($this->storage->findAllSchemas() as $storedSchema) {
                if ($storedSchema->type === $type) {
                    $storedHash = $storedSchema->schemaHash;
                    $storedStubs = $storedSchema->stubs;
                    break;
                }
            }

            $hashDrift = $storedHash !== null && $storedHash !== $hash;

            $changedMessages = [];
            foreach ($schema->stubs() as $stub) {
                $messageSources = [...$stub->getMessages(), ...$stub->getReturnTypes()];
                foreach ($messageSources as $messageSource) {
                    if (array_key_exists($messageSource, $changedMessages)) {
                        continue;
                    }

                    if (!class_exists($messageSource)) {
                        continue;
                    }

                    $liveSource = Source::message($messageSource);
                    $storedEntity = null;
                    foreach ($this->storage->findMessageSourceByMessageSource($messageSource) as $entity) {
                        $storedEntity = $entity;
                    }

                    if (!$storedEntity instanceof MessageSourceEntity) {
                        continue;
                    }

                    if ($storedEntity->messageHash === $liveSource->messageHash) {
                        continue;
                    }

                    $shortName = (new ReflectionClass($messageSource))->getShortName();
                    $changedMessages[$messageSource] = [
                        'class' => $messageSource,
                        'liveHash' => $liveSource->messageHash,
                        'storedHash' => $storedEntity->messageHash,
                        'liveProperties' => $liveSource->propertyNames[$shortName] ?? [],
                        'storedProperties' => $storedEntity->propertyNames[$shortName] ?? [],
                    ];
                }
            }

            return new JsonResponse([
                'valid' => true,
                'error' => null,
                'schema' => $schema->jsonSerialize(),
                'hash' => $hash,
                'storedHash' => $storedHash,
                'hashDrift' => $hashDrift,
                'storedSchema' => $hashDrift ? $storedStubs : null,
                'changedMessages' => array_values($changedMessages),
            ]);
        } catch (Throwable $throwable) {
            return new JsonResponse([
                'valid' => false,
                'error' => $throwable->getMessage(),
                'schema' => null,
                'hash' => null,
                'storedHash' => null,
                'hashDrift' => false,
                'changedMessages' => [],
            ]);
        }
    }
}
