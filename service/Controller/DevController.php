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
use Wundii\DataMapper\Enum\DataTypeEnum;
use Wundii\DataMapper\Parser\ReflectionClassParser;
use Wundii\Flowcrafter\Assert;
use Wundii\Flowcrafter\Attribute\FlowEphemeral;
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
use Wundii\Flowcrafter\Projection\ProjectionDiscovery;
use Wundii\Flowcrafter\Projection\ProjectionResult;
use Wundii\Flowcrafter\Projection\ProjectionWorker;
use Wundii\Flowcrafter\Queue\InMemoryQueue;
use Wundii\Flowcrafter\Source;
use Wundii\Flowcrafter\Storage\Entity\MessageSourceEntity;
use Wundii\Flower\ThrowableEntity;

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

            $reflectionClass = new ReflectionClass($resolvedClassName);
            $ephemeralAttributes = $reflectionClass->getAttributes(FlowEphemeral::class);
            $ephemeral = $ephemeralAttributes !== [] ? $ephemeralAttributes[0]->newInstance()->expiryDays : null;

            $storedHash = null;
            $storedSteps = null;
            foreach ($this->storage->findAllSchemas() as $storedSchema) {
                if ($storedSchema->type === $type) {
                    $storedHash = $storedSchema->schemaHash;
                    $storedSteps = $storedSchema;
                    break;
                }
            }

            $hashDrift = $storedHash !== null && $storedHash !== $hash;

            $changedMessages = [];
            foreach ($schema->steps() as $step) {
                $messageSources = [...$step->getMessages(), ...$step->getReturnTypes()];
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
            foreach ($schema->steps() as $step) {
                if ($step->getMessageEnum() !== MessageEnum::INIT) {
                    continue;
                }

                $initMessageClass = $step->getMessages()[0] ?? null;
                if ($initMessageClass !== null && class_exists($initMessageClass)) {
                    $built = $this->buildInitMessageSchema($initMessageClass);
                    $initMessageSchema = $built['defaults'];
                    $initMessageTypes = $built['types'];
                }

                break;
            }

            $messageSchemas = [];
            foreach ($schema->steps() as $step) {
                foreach ([...$step->getMessages(), ...$step->getReturnTypes()] as $messageClass) {
                    if (!class_exists($messageClass)) {
                        continue;
                    }

                    $this->buildMessageSchemasRecursively($messageClass, $messageSchemas);
                }
            }

            return new JsonResponse([
                'valid' => true,
                'error' => null,
                'schema' => $schema->jsonSerialize(),
                'hash' => $hash,
                'storedHash' => $storedHash,
                'hashDrift' => $hashDrift,
                'storedSchema' => $hashDrift ? $storedSteps : null,
                'changedMessages' => array_values($changedMessages),
                'messageSchemas' => $messageSchemas,
                'initMessageSchema' => $initMessageSchema,
                'initMessageTypes' => $initMessageTypes,
                'ephemeral' => $ephemeral,
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

        $projectionHandlerMetas = [];
        $projectionThrowable = null;
        try {
            $projectionHandlerMetas = ProjectionDiscovery::discover();
        } catch (Throwable $throwable) {
            $projectionThrowable = ThrowableEntity::create($throwable);
        }

        $inMemoryQueue = new InMemoryQueue();

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
                queue: $inMemoryQueue,
                dependencyRegistry: $this->flowcrafterConfig->getDependencyRegistry(),
                projectionHandlerMetas: $projectionHandlerMetas,
            );
        } catch (Throwable $throwable) {
            return new JsonResponse([
                'error' => $throwable->getMessage(),
            ], 500);
        }

        $messageReturn = null;
        $runnerThrowable = null;
        $memoryBefore = memory_get_usage();
        try {
            $messageReturn = $flowRunner->run(message: $messageInstance);
        } catch (Throwable $throwable) {
            $runnerThrowable = ThrowableEntity::create($throwable);
        }

        $memoryAfter = memory_get_usage();
        $memoryPeak = memory_get_peak_usage();

        $flowInstance = $flowRunner->getFlow();
        $flowData = null;
        if ($flowInstance instanceof Flow) {
            $flowData = json_decode(Converter::flowToJson($flowInstance), true);
        }

        $runnerOutput = $flowRunner->getOutput();

        $projectionOutput = [];
        if (!$projectionThrowable instanceof ThrowableEntity && $projectionHandlerMetas !== []) {
            $projectionWorker = new ProjectionWorker(
                storage: null,
                queue: $inMemoryQueue,
                projectionHandlerMetas: $projectionHandlerMetas,
                dependencyRegistry: $this->flowcrafterConfig->getDependencyRegistry(),
            );

            $projectionWorker->tick(reporter: static function (ProjectionResult $projectionResult) use (&$projectionOutput): void {
                $projectionOutput[] = [
                    'handler' => $projectionResult->handlerClass,
                    'method' => $projectionResult->method,
                    'messageSource' => $projectionResult->messageSource,
                    'content' => $projectionResult->content,
                    'throwable' => $projectionResult->throwable instanceof Throwable
                        ? ThrowableEntity::create($projectionResult->throwable)
                        : null,
                ];
            });
        }

        return new JsonResponse([
            'success' => !$runnerThrowable instanceof ThrowableEntity,
            'runnerThrowable' => $runnerThrowable,
            'runnerOutput' => $runnerOutput !== [] ? $runnerOutput : null,
            'projectionThrowable' => $projectionThrowable,
            'projectionOutput' => $projectionOutput !== [] ? $projectionOutput : null,
            'messageReturn' => $messageReturn instanceof MessageReturnInterface ? $messageReturn : null,
            'flow' => $flowData,
            'memory' => [
                'used' => $memoryAfter - $memoryBefore,
                'peak' => $memoryPeak,
            ],
        ]);
    }

    /**
     * @param class-string $messageClass
     * @param array<string, array<string, string>> $schemas
     */
    private function buildMessageSchemasRecursively(string $messageClass, array &$schemas): void
    {
        if (array_key_exists($messageClass, $schemas)) {
            return;
        }

        $built = $this->buildInitMessageSchema($messageClass);
        $schemas[$messageClass] = $built['types'];

        $primitives = ['string', 'int', 'float', 'bool', 'array', 'mixed', 'null', 'void', 'never', 'object'];
        foreach ($built['types'] as $typeString) {
            $typeName = rtrim(ltrim($typeString, '?'), '[]');
            foreach (explode('|', $typeName) as $part) {
                if (!in_array($part, $primitives, true) && class_exists($part)) {
                    /** @var class-string $part */
                    $this->buildMessageSchemasRecursively($part, $schemas);
                }
            }
        }

        $constructor = (new ReflectionClass($messageClass))->getConstructor();
        foreach ($constructor?->getParameters() ?? [] as $param) {
            $arrayTarget = $this->resolveArrayTargetType($messageClass, $param->getName());
            if ($arrayTarget !== null && class_exists($arrayTarget)) {
                /** @var class-string $arrayTarget */
                $this->buildMessageSchemasRecursively($arrayTarget, $schemas);
            }
        }
    }

    /**
     * @param class-string $messageClass
     */
    private function resolveArrayTargetType(string $messageClass, string $paramName): ?string
    {
        try {
            $reflectionObjectDto = (new ReflectionClassParser())->parse($messageClass);
        } catch (Throwable) {
            return null;
        }

        foreach ($reflectionObjectDto->getProperties() as $propertyDto) {
            if (strcasecmp($propertyDto->getName(), $paramName) !== 0) {
                continue;
            }

            if ($propertyDto->getDataType() !== DataTypeEnum::ARRAY) {
                return null;
            }

            return $propertyDto->getTargetType();
        }

        return null;
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

            if (ltrim($typeString, '?') === 'array') {
                $arrayTarget = $this->resolveArrayTargetType($messageClass, $param->getName());
                if ($arrayTarget !== null) {
                    // Object lists keep the FQCN so the UI can resolve the nested
                    // schema via messageSchemas[FQCN]; scalar lists keep the primitive.
                    $typeString = ($nullable ? '?' : '') . $arrayTarget . '[]';
                }
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
