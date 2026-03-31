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

namespace IQ2i\MessengerImportBundle\EventSubscriber;

use IQ2i\MessengerImportBundle\Event\ImportBatchCompletedEvent;
use IQ2i\MessengerImportBundle\Message\BatchAwareMessageInterface;
use IQ2i\MessengerImportBundle\Model\ImportBatchRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ImportBatchSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ImportBatchRepositoryInterface $batchRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'onMessageHandled',
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->handleBatchDecrement($event->getEnvelope()->getMessage());
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if (!$event->willRetry()) {
            $this->handleBatchDecrement($event->getEnvelope()->getMessage());
        }
    }

    private function handleBatchDecrement(object $message): void
    {
        if (!$message instanceof BatchAwareMessageInterface) {
            return;
        }

        $batchId = $message->getBatchId();
        if (null === $batchId) {
            return;
        }

        $batch = $this->batchRepository->decrement($batchId);

        if (null === $batch) {
            return;
        }

        $this->logger->info('Import batch progress', [
            'batch_id' => $batchId,
            'remaining' => $batch->getRemaining(),
            'total' => $batch->getTotal(),
        ]);

        if ($batch->isComplete()) {
            $this->eventDispatcher->dispatch(new ImportBatchCompletedEvent($batchId, $batch->getTotal()));

            $this->logger->info('Import batch completed', [
                'batch_id' => $batchId,
                'total' => $batch->getTotal(),
            ]);
        }
    }
}
