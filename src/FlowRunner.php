<?php

declare(strict_types=1);

namespace Wundii\Flowcrafter;

use ReflectionClass;
use RuntimeException;
use Wundii\Flowcrafter\Interface\FlowInterface;
use Wundii\Flowcrafter\Interface\MessageInitInterface;
use Wundii\Flowcrafter\Interface\MessageReturnInterface;
use Wundii\Flowcrafter\Interface\StubInterface;

class FlowRunner
{
    // /**
    //  * @param class-string<FlowInterface> $flow
    //  * @param MessageInitInterface $messageInit
    //  */
    // public function __construct(
    //     private string $flow,
    //     private MessageInitInterface $messageInit,
    // ) {
    //     Assert::classString(
    //         $this->flow,
    //         FlowInterface::class,
    //         'The flow must be a class implementing FlowInterface'
    //     );
    // }

    // public function run(): false|MessageReturnInterface
    // {
    //     $flow = Flow::create(
    //         'testType',
    //         $this->flow,
    //     );
    //
    //     $messageToStubsMap = $flow->getMessageToSubsMap();
    //
    //     $flowSchema = $flow->getFlowSchema();
    //     $stubs = $flowSchema->stubs();
    //     $initStub = $flowSchema->initStub();
    //
    //     if (!is_subclass_of($this->messageInit, $initStub->getSource())) {
    //         throw new RuntimeException(sprintf(
    //             'The provided message init class "%s" does not match the expected source "%s".',
    //             $this->messageInit::class,
    //             $initStub->getSource(),
    //         ));
    //     }
    //
    //     $reflectionClass = new ReflectionClass($initStub->getSource());
    //     $instance = $reflectionClass->newInstance($this->messageInit);
    //
    //     if (!$instance instanceof StubInterface) {
    //         throw new RuntimeException(sprintf(
    //             'The flow instance must implement StubInterface, "%s" given.',
    //             $reflectionClass->getName(),
    //         ));
    //     }
    //
    //     $return = $instance->process();
    //
    //     // check: false or $instance->returnTypes()
    //
    //     dd($return);
    //
    //     return false;
    // }
}
