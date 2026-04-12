<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Attribute\FlowGroup;
use Wundii\Flowcrafter\ClassResolver;
use Wundii\Flowcrafter\Config\FlowcrafterConfig;
use Wundii\Flowcrafter\Converter;
use Wundii\Flowcrafter\Enum\MessageEnum;
use Wundii\Flowcrafter\Flow;
use Wundii\Flowcrafter\FlowPreflight;
use Wundii\Flowcrafter\FlowRunner;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
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
                    $storedStubs = $storedSchema;
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
                        'livePropertyNames' => $liveSource->propertyNames,
                        'storedPropertyNames' => $storedEntity->propertyNames,
                    ];
                }
            }

            $initMessageSchema = null;
            $initMessageTypes = null;
            foreach ($schema->stubs() as $stub) {
                if ($stub->getMessageEnum() !== MessageEnum::INIT) {
                    continue;
                }

                $initMessageClass = $stub->getMessages()[0] ?? null;
                if ($initMessageClass !== null && class_exists($initMessageClass)) {
                    $built = $this->buildInitMessageSchema($initMessageClass);
                    $initMessageSchema = $built['defaults'];
                    $initMessageTypes = $built['types'];
                }

                break;
            }

            $messageSchemas = [];
            foreach ($schema->stubs() as $stub) {
                foreach ([...$stub->getMessages(), ...$stub->getReturnTypes()] as $messageClass) {
                    if (array_key_exists($messageClass, $messageSchemas)) {
                        continue;
                    }

                    if (!class_exists($messageClass)) {
                        continue;
                    }

                    $built = $this->buildInitMessageSchema($messageClass);
                    $messageSchemas[$messageClass] = $built['types'];
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
                'messageSchemas' => $messageSchemas,
                'initMessageSchema' => $initMessageSchema,
                'initMessageTypes' => $initMessageTypes,
            ]);
        } catch (Throwable $throwable) {
            return new JsonResponse([
                'valid' => false,
                'error' => $throwable->getMessage(),
                'schema' => null,
                'hash' => null,
                'storedHash' => null,
                'hashDrift' => false,
                'storedSchema' => null,
                'changedMessages' => [],
            ]);
        }
    }

    public function run(Request $request): JsonResponse
    {
        if (!$this->flowcrafterConfig->getServerDev()) {
            return new JsonResponse([
                'error' => 'Dev mode is not enabled',
            ], 403);
        }

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse([
                'error' => 'Invalid JSON body',
            ], 400);
        }

        $className = ltrim(Assert::string($body['className'] ?? ''), '\\');
        $messageSource = Assert::string($body['messageSource'] ?? '');
        $message = Assert::array($body['message'] ?? []);

        if ($className === '' || $messageSource === '') {
            return new JsonResponse([
                'error' => 'className and messageSource required',
            ], 400);
        }

        if (!class_exists($className)) {
            return new JsonResponse([
                'error' => 'Class not found',
            ], 404);
        }

        if (!is_a($className, FlowInterface::class, true)) {
            return new JsonResponse([
                'error' => 'Class does not implement FlowInterface',
            ], 400);
        }

        /** @var class-string<FlowInterface> $className */
        $flowPreflight = new FlowPreflight();

        try {
            $flowSource = $flowPreflight->ensureFlowSource($className);
            $validatedMessageSource = $flowPreflight->ensureMessageSource($flowSource, $messageSource);
            $messageInstance = $flowPreflight->hydrateMessage($validatedMessageSource, $message);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return new JsonResponse([
                'error' => $invalidArgumentException->getMessage(),
            ], 400);
        }

        try {
            $flowRunner = new FlowRunner(
                type: $className::schema()->type(),
                flowSource: $className,
                storage: null,
                dependenciesInjection: $this->flowcrafterConfig->getDependencyInjections(),
            );
        } catch (Throwable $throwable) {
            return new JsonResponse([
                'error' => $throwable->getMessage(),
            ], 500);
        }

        $messageReturn = null;
        $error = null;
        try {
            $messageReturn = $flowRunner->run(message: $messageInstance);
        } catch (Throwable $throwable) {
            $error = $throwable->getMessage();
        }

        $flowInstance = $flowRunner->getFlow();
        $flowData = null;
        if ($flowInstance instanceof Flow) {
            $flowData = json_decode(Converter::flowToJson($flowInstance), true);
        }

        $outputLog = $flowRunner->getOutputLog();

        return new JsonResponse([
            'success' => $error === null,
            'error' => $error,
            'output' => $outputLog !== [] ? $outputLog : null,
            'messageReturn' => $messageReturn instanceof MessageReturnInterface ? $messageReturn : null,
            'flow' => $flowData,
        ]);
    }

    /**
     * @param class-string $messageClass
     * @return array{defaults: array<string, mixed>, types: array<string, string>}
     */
    private function buildInitMessageSchema(string $messageClass): array
    {
        $defaults = [];
        $types = [];
        $constructor = (new ReflectionClass($messageClass))->getConstructor();

        foreach ($constructor?->getParameters() ?? [] as $param) {
            $type = $param->getType();
            $nullable = $type?->allowsNull() ?? false;

            if ($type instanceof ReflectionUnionType) {
                $parts = array_map(
                    static fn (ReflectionNamedType $reflectionNamedType): string => $reflectionNamedType->getName(),
                    array_filter($type->getTypes(), static fn (\ReflectionIntersectionType|\ReflectionNamedType $t): bool => $t instanceof ReflectionNamedType && $t->getName() !== 'null'),
                );
                $typeString = ($nullable ? '?' : '') . implode('|', $parts);
                $firstTypeName = $parts[0] ?? 'string';
            } elseif ($type instanceof ReflectionNamedType) {
                $typeString = ($nullable ? '?' : '') . $type->getName();
                $firstTypeName = $type->getName();
            } else {
                $typeString = 'mixed';
                $firstTypeName = 'string';
            }

            $types[$param->getName()] = $typeString;

            if ($param->isDefaultValueAvailable()) {
                $defaults[$param->getName()] = $param->getDefaultValue();
            } else {
                $defaults[$param->getName()] = match (true) {
                    $nullable => null,
                    $firstTypeName === 'int' => 0,
                    $firstTypeName === 'float' => 0.0,
                    $firstTypeName === 'bool' => false,
                    $firstTypeName === 'array' => [],
                    default => '',
                };
            }
        }

        return [
            'defaults' => $defaults,
            'types' => $types,
        ];
    }
}
