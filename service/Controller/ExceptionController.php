<?php

declare(strict_types=1);

namespace Wundii\Service\Controller;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Wundii\Flowcrafter\Enum\SortEnum;
use Wundii\Flowcrafter\Interface\StorageInterface;

final class ExceptionController
{
    public function __construct(
        private readonly StorageInterface $storage,
    ) {
    }

    public function list(Request $request): JsonResponse
    {
        $sort = $request->query->get('sort', 'desc') === 'asc' ? SortEnum::ASC : SortEnum::DESC;
        $top = max(1, min(10000, (int) $request->query->get('top', 1000)));
        $skip = max(0, (int) $request->query->get('skip', 0));
        $flowHash = $request->query->get('flowHash');
        $status = $request->query->get('status');
        $fromStr = $request->query->get('from');
        $toStr = $request->query->get('to');
        $from = is_string($fromStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $fromStr) : null;
        $to = is_string($toStr) ? DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $toStr) : null;
        $from = $from instanceof DateTimeImmutable ? $from : null;
        $to = $to instanceof DateTimeImmutable ? $to : null;

        $exceptions = $flowHash !== null
            ? $this->storage->findExceptionsByFlowHash($flowHash, $sort, $top + 1, $skip, $from, $to)
            : $this->storage->findAllExceptions($sort, $top + 1, $skip, $from, $to, $status);

        $items = array_values(iterator_to_array($exceptions));
        $hasMore = count($items) > $top;
        if ($hasMore) {
            array_pop($items);
        }

        $total = $flowHash !== null
            ? $this->storage->countExceptionsByFlowHash($flowHash)
            : $this->storage->countExceptions($from, $to, $status);

        return new JsonResponse([
            'items' => $items,
            'total' => $total,
            'hasMore' => $hasMore,
        ]);
    }
}
