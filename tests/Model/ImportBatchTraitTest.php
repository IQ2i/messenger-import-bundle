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

namespace IQ2i\MessengerImportBundle\Tests\Model;

use IQ2i\MessengerImportBundle\Model\ImportBatchInterface;
use IQ2i\MessengerImportBundle\Model\ImportBatchTrait;
use PHPUnit\Framework\TestCase;

class ImportBatchTraitTest extends TestCase
{
    private ImportBatchInterface $batch;

    protected function setUp(): void
    {
        $this->batch = new class implements ImportBatchInterface {
            use ImportBatchTrait;
        };
    }

    public function testInitializeSetsTotal(): void
    {
        $this->batch->initialize(42);

        $this->assertSame(42, $this->batch->getTotal());
    }

    public function testInitializeSetsRemainingEqualToTotal(): void
    {
        $this->batch->initialize(42);

        $this->assertSame(42, $this->batch->getRemaining());
    }

    public function testInitializeSetsCreatedAt(): void
    {
        $before = new \DateTimeImmutable();
        $this->batch->initialize(1);
        $after = new \DateTimeImmutable();

        $createdAt = $this->batch->getCreatedAt();
        $this->assertGreaterThanOrEqual($before, $createdAt);
        $this->assertLessThanOrEqual($after, $createdAt);
    }

    public function testGetCompletedAtIsNullAfterInitialize(): void
    {
        $this->batch->initialize(10);

        $this->assertNull($this->batch->getCompletedAt());
    }

    public function testIsCompleteReturnsFalseWhenRemainingGreaterThanZero(): void
    {
        $this->batch->initialize(5);

        $this->assertFalse($this->batch->isComplete());
    }

    public function testIsCompleteReturnsTrueWhenRemainingIsZero(): void
    {
        // Use reflection to set remaining to 0 without going through the repository
        $batch = new class implements ImportBatchInterface {
            use ImportBatchTrait;

            public function forceRemaining(int $value): void
            {
                $this->remaining = $value;
            }
        };

        $batch->initialize(1);
        $batch->forceRemaining(0);

        $this->assertTrue($batch->isComplete());
    }

    public function testIsCompleteReturnsTrueWhenRemainingIsNegative(): void
    {
        $batch = new class implements ImportBatchInterface {
            use ImportBatchTrait;

            public function forceRemaining(int $value): void
            {
                $this->remaining = $value;
            }
        };

        $batch->initialize(1);
        $batch->forceRemaining(-1);

        $this->assertTrue($batch->isComplete());
    }

    public function testMarkCompleteSetsCompletedAt(): void
    {
        $this->batch->initialize(1);

        $before = new \DateTimeImmutable();
        $this->batch->markComplete();
        $after = new \DateTimeImmutable();

        $completedAt = $this->batch->getCompletedAt();
        $this->assertNotNull($completedAt);
        $this->assertGreaterThanOrEqual($before, $completedAt);
        $this->assertLessThanOrEqual($after, $completedAt);
    }

    public function testMarkCompleteIsIdempotent(): void
    {
        $this->batch->initialize(1);
        $this->batch->markComplete();

        $firstCompletedAt = $this->batch->getCompletedAt();

        // A second call must not overwrite completedAt
        $this->batch->markComplete();

        $this->assertSame($firstCompletedAt, $this->batch->getCompletedAt());
    }
}
