<?php

/*
 * This file is part of the messenger-import-bundle.
 *
 * (c) Loïc Sapone <loic@sapone.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

/*
 * This file is part of the Messenger Import Bundle.
 *
 * (c) Loïc Sapone <loic@sapone.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IQ2i\MessengerImportBundle\Tests\EventSubscriber;

use IQ2i\MessengerImportBundle\Event\ImportBatchCompletedEvent;
use IQ2i\MessengerImportBundle\EventSubscriber\ImportBatchSubscriber;
use IQ2i\MessengerImportBundle\Message\BatchAwareMessageInterface;
use IQ2i\MessengerImportBundle\Model\ImportBatchInterface;
use IQ2i\MessengerImportBundle\Model\ImportBatchRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ImportBatchSubscriberTest extends TestCase
{
    private ImportBatchRepositoryInterface&MockObject $repository;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ImportBatchSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ImportBatchRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->subscriber = new ImportBatchSubscriber(
            $this->repository,
            $this->eventDispatcher,
            new NullLogger(),
        );
    }

    public function testGetSubscribedEventsRegistersRequiredEvents(): void
    {
        $events = ImportBatchSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(WorkerMessageHandledEvent::class, $events);
        $this->assertArrayHasKey(WorkerMessageFailedEvent::class, $events);
    }

    public function testOnMessageHandledIgnoresNonBatchMessage(): void
    {
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageHandledEvent($envelope, 'async');

        $this->repository->expects($this->never())->method('decrement');

        $this->subscriber->onMessageHandled($event);
    }

    public function testOnMessageHandledIgnoresMessageWithNullBatchId(): void
    {
        $message = $this->createMock(BatchAwareMessageInterface::class);
        $message->method('getBatchId')->willReturn(null);

        $event = new WorkerMessageHandledEvent(new Envelope($message), 'async');

        $this->repository->expects($this->never())->method('decrement');

        $this->subscriber->onMessageHandled($event);
    }

    public function testOnMessageHandledDecrementsWhenBatchIdPresent(): void
    {
        $batchId = 'batch-uuid-123';
        $message = $this->createBatchMessage($batchId);

        $batch = $this->createBatchMock(remaining: 5, total: 10, complete: false);
        $this->repository->expects($this->once())->method('decrement')->with($batchId)->willReturn($batch);
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->subscriber->onMessageHandled(new WorkerMessageHandledEvent(new Envelope($message), 'async'));
    }

    public function testOnMessageHandledDispatchesCompletedEventWhenBatchIsFinished(): void
    {
        $batchId = 'batch-uuid-123';
        $message = $this->createBatchMessage($batchId);

        $batch = $this->createBatchMock(remaining: 0, total: 10, complete: true);
        $this->repository->method('decrement')->willReturn($batch);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (ImportBatchCompletedEvent $event) use ($batchId) {
                return $event->getBatchId() === $batchId && 10 === $event->getTotal();
            }));

        $this->subscriber->onMessageHandled(new WorkerMessageHandledEvent(new Envelope($message), 'async'));
    }

    public function testOnMessageHandledDoesNothingWhenDecrementReturnsNull(): void
    {
        $message = $this->createBatchMessage('batch-uuid-123');
        $this->repository->method('decrement')->willReturn(null);

        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->subscriber->onMessageHandled(new WorkerMessageHandledEvent(new Envelope($message), 'async'));
    }

    public function testOnMessageFailedDoesNotDecrementWhenRetrying(): void
    {
        $message = $this->createBatchMessage('batch-uuid-123');
        $event = new WorkerMessageFailedEvent(new Envelope($message), 'async', new \RuntimeException('fail'));
        $event->setForRetry();

        $this->repository->expects($this->never())->method('decrement');

        $this->subscriber->onMessageFailed($event);
    }

    public function testOnMessageFailedDecrementsWhenRetriesExhausted(): void
    {
        $batchId = 'batch-uuid-123';
        $message = $this->createBatchMessage($batchId);
        $event = new WorkerMessageFailedEvent(new Envelope($message), 'async', new \RuntimeException('fail'));
        // willRetry() is false by default

        $batch = $this->createBatchMock(remaining: 2, total: 5, complete: false);
        $this->repository->expects($this->once())->method('decrement')->with($batchId)->willReturn($batch);

        $this->subscriber->onMessageFailed($event);
    }

    public function testOnMessageFailedDispatchesCompletedEventWhenLastMessageFails(): void
    {
        $batchId = 'batch-uuid-123';
        $message = $this->createBatchMessage($batchId);
        $event = new WorkerMessageFailedEvent(new Envelope($message), 'async', new \RuntimeException('fail'));

        $batch = $this->createBatchMock(remaining: 0, total: 5, complete: true);
        $this->repository->method('decrement')->willReturn($batch);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ImportBatchCompletedEvent::class));

        $this->subscriber->onMessageFailed($event);
    }

    private function createBatchMessage(string $batchId): BatchAwareMessageInterface
    {
        $message = $this->createMock(BatchAwareMessageInterface::class);
        $message->method('getBatchId')->willReturn($batchId);

        return $message;
    }

    private function createBatchMock(int $remaining, int $total, bool $complete): ImportBatchInterface
    {
        $batch = $this->createMock(ImportBatchInterface::class);
        $batch->method('getRemaining')->willReturn($remaining);
        $batch->method('getTotal')->willReturn($total);
        $batch->method('isComplete')->willReturn($complete);

        return $batch;
    }
}
