<?php


declare(strict_types=1);

/*
 * This file is part of the Messenger Import Bundle.
 *
 * (c) Loïc Sapone <loic@sapone.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace IQ2i\MessengerImportBundle\Tests\Model;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use IQ2i\MessengerImportBundle\Model\ImportBatchInterface;
use IQ2i\MessengerImportBundle\Model\ImportBatchRepositoryTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImportBatchRepositoryTraitTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Query&MockObject $query;

    protected function setUp(): void
    {
        $this->query = $this->createMock(Query::class);
        $this->query->method('setParameter')->willReturnSelf();

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('createQuery')->willReturn($this->query);
    }

    public function testDecrementUsesEntityNameInQuery(): void
    {
        $entityName = 'App\Entity\ImportBatch';

        $this->em->expects($this->once())
            ->method('createQuery')
            ->with($this->stringContains($entityName))
            ->willReturn($this->query);

        $this->createRepository($entityName, null)->decrement('any-id');
    }

    public function testDecrementSetsParameterWithBatchId(): void
    {
        $batchId = 'batch-uuid-123';

        $this->query->expects($this->once())
            ->method('setParameter')
            ->with('id', $batchId)
            ->willReturnSelf();

        $this->createRepository('App\Entity\ImportBatch', null)->decrement($batchId);
    }

    public function testDecrementExecutesQuery(): void
    {
        $this->query->expects($this->once())->method('execute');

        $this->createRepository('App\Entity\ImportBatch', null)->decrement('any-id');
    }

    public function testDecrementClearsEntityManager(): void
    {
        $this->em->expects($this->once())->method('clear');

        $this->createRepository('App\Entity\ImportBatch', null)->decrement('any-id');
    }

    public function testDecrementReturnsNullWhenBatchNotFound(): void
    {
        $result = $this->createRepository('App\Entity\ImportBatch', null)->decrement('unknown-id');

        $this->assertNull($result);
    }

    public function testDecrementReturnsBatchWhenFound(): void
    {
        $batch = $this->createIncompleteBatch();

        $result = $this->createRepository('App\Entity\ImportBatch', $batch)->decrement('batch-uuid-123');

        $this->assertSame($batch, $result);
    }

    public function testDecrementCallsMarkCompleteAndFlushesWhenBatchJustFinished(): void
    {
        $batch = $this->createMock(ImportBatchInterface::class);
        $batch->method('isComplete')->willReturn(true);
        $batch->method('getCompletedAt')->willReturn(null);
        $batch->expects($this->once())->method('markComplete');

        $this->em->expects($this->once())->method('flush');

        $this->createRepository('App\Entity\ImportBatch', $batch)->decrement('batch-uuid-123');
    }

    public function testDecrementDoesNotMarkCompleteWhenAlreadyCompleted(): void
    {
        $batch = $this->createMock(ImportBatchInterface::class);
        $batch->method('isComplete')->willReturn(true);
        $batch->method('getCompletedAt')->willReturn(new \DateTimeImmutable());
        $batch->expects($this->never())->method('markComplete');

        $this->em->expects($this->never())->method('flush');

        $this->createRepository('App\Entity\ImportBatch', $batch)->decrement('batch-uuid-123');
    }

    public function testDecrementDoesNotMarkCompleteWhenBatchIsNotFinished(): void
    {
        $batch = $this->createIncompleteBatch();
        $batch->expects($this->never())->method('markComplete');

        $this->em->expects($this->never())->method('flush');

        $this->createRepository('App\Entity\ImportBatch', $batch)->decrement('batch-uuid-123');
    }

    /**
     * Creates an anonymous repository that uses the trait with injected Doctrine dependencies.
     */
    private function createRepository(string $entityName, ?ImportBatchInterface $findResult): object
    {
        $em = $this->em;

        return new class($em, $entityName, $findResult) {
            use ImportBatchRepositoryTrait;

            public function __construct(
                private readonly EntityManagerInterface $em,
                private readonly string $entityName,
                private readonly ?ImportBatchInterface $findResult,
            ) {
            }

            public function getEntityManager(): EntityManagerInterface
            {
                return $this->em;
            }

            public function getEntityName(): string
            {
                return $this->entityName;
            }

            public function find(mixed $id, mixed $lockMode = null, mixed $lockVersion = null): ?ImportBatchInterface
            {
                return $this->findResult;
            }
        };
    }

    private function createIncompleteBatch(): ImportBatchInterface&MockObject
    {
        $batch = $this->createMock(ImportBatchInterface::class);
        $batch->method('isComplete')->willReturn(false);

        return $batch;
    }
}
