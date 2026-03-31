# Messenger Import Bundle

A Symfony bundle that tracks the completion of asynchronous imports dispatched via `symfony/messenger`.

When importing large files, dispatching messages asynchronously speeds up processing but makes it impossible to know when all messages have been handled. This bundle solves that problem by listening to Messenger worker events and detecting when every message in a batch has been processed — whether successfully or not.

## Requirements

- PHP 8.1+
- Symfony 6.4 / 7.x / 8.x
- `symfony/messenger`
- Doctrine ORM (for the provided traits)

## Installation

```bash
composer require iq2i/messenger-import-bundle
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    IQ2i\MessengerImportBundle\MessengerImportBundle::class => ['all' => true],
];
```

## How it works

1. Before dispatching messages, you create an `ImportBatch` entity that stores the total number of messages to process.
2. Each message carries the batch ID via `BatchAwareMessageInterface`.
3. The bundle's subscriber listens to `WorkerMessageHandledEvent` and `WorkerMessageFailedEvent`. After each message, it decrements the batch counter.
4. When the counter reaches zero, an `ImportBatchCompletedEvent` is dispatched. You listen to this event to send a notification, trigger a follow-up action, etc.

## Setup

### 1. Create the batch entity

Create an entity that implements `ImportBatchInterface` and uses `ImportBatchTrait`:

```php
// src/Entity/ImportBatch.php

use Doctrine\ORM\Mapping as ORM;
use IQ2i\MessengerImportBundle\Model\ImportBatchInterface;
use IQ2i\MessengerImportBundle\Model\ImportBatchTrait;

#[ORM\Entity(repositoryClass: ImportBatchRepository::class)]
class ImportBatch implements ImportBatchInterface
{
    use ImportBatchTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    public function getId(): string
    {
        return $this->id;
    }
}
```

### 2. Create the batch repository

Create a repository that implements `ImportBatchRepositoryInterface` and uses `ImportBatchRepositoryTrait`:

```php
// src/Repository/ImportBatchRepository.php

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use IQ2i\MessengerImportBundle\Model\ImportBatchRepositoryInterface;
use IQ2i\MessengerImportBundle\Model\ImportBatchRepositoryTrait;

class ImportBatchRepository extends ServiceEntityRepository implements ImportBatchRepositoryInterface
{
    use ImportBatchRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportBatch::class);
    }
}
```

### 3. Implement `BatchAwareMessageInterface` on your messages

```php
// src/Message/ImportProductMessage.php

use IQ2i\MessengerImportBundle\Message\BatchAwareMessageInterface;

class ImportProductMessage implements BatchAwareMessageInterface
{
    public function __construct(
        private readonly array $row,
        private readonly ?string $batchId = null,
    ) {}

    public function getRow(): array
    {
        return $this->row;
    }

    public function getBatchId(): ?string
    {
        return $this->batchId;
    }
}
```

### 4. Dispatch your messages

Initialize the batch with the total number of messages, then attach the batch ID to each message:

```php
// src/Service/ProductImporter.php

class ProductImporter
{
    public function __construct(
        private readonly ImportBatchRepository $batchRepository,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
    ) {}

    public function import(array $rows): void
    {
        $batch = new ImportBatch();
        $batch->initialize(count($rows));

        $this->em->persist($batch);
        $this->em->flush();

        foreach ($rows as $row) {
            $this->bus->dispatch(new ImportProductMessage($row, $batch->getId()));
        }
    }
}
```

### 5. Listen to the completion event

```php
// src/EventSubscriber/ImportCompletedSubscriber.php

use IQ2i\MessengerImportBundle\Event\ImportBatchCompletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ImportCompletedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ImportBatchCompletedEvent::class => 'onImportCompleted',
        ];
    }

    public function onImportCompleted(ImportBatchCompletedEvent $event): void
    {
        // Send a notification, trigger a report, clean up temporary files...
    }
}
```

## API reference

### `BatchAwareMessageInterface`

Implement this interface on any message that belongs to an import batch.

| Method | Description |
|---|---|
| `getBatchId(): ?string` | Returns the batch ID, or `null` if the message is not part of a batch |

### `ImportBatchInterface`

| Method | Description |
|---|---|
| `initialize(int $total): void` | Sets the total message count and marks the batch as started |
| `getTotal(): int` | Total number of messages in the batch |
| `getRemaining(): int` | Number of messages not yet processed |
| `getCreatedAt(): \DateTimeImmutable` | When the batch was initialized |
| `getCompletedAt(): ?\DateTimeImmutable` | When the batch completed, `null` if still in progress |
| `isComplete(): bool` | Returns `true` when all messages have been processed |
| `markComplete(): void` | Sets `completedAt` (idempotent — safe to call multiple times) |

### `ImportBatchCompletedEvent`

Dispatched when the last message in a batch has been handled (or permanently failed).

| Method | Description |
|---|---|
| `getBatchId(): string` | ID of the completed batch |
| `getTotal(): int` | Total number of messages that were processed |

## Notes

- **Failed messages** are counted as processed only when all retry attempts are exhausted (`willRetry() === false`). A message that will be retried does not decrement the counter.
- **`completedAt`** is set atomically inside `ImportBatchRepositoryTrait::decrement()` the first time `remaining` reaches zero. It is safe in concurrent worker environments.
- The `decrement()` operation uses a single `UPDATE ... WHERE remaining > 0` query to prevent the counter from going below zero under concurrent load.

## License

MIT — see [LICENSE](LICENSE).
