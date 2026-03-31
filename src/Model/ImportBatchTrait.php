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

namespace IQ2i\MessengerImportBundle\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;

trait ImportBatchTrait
{
    #[ORM\Column(type: Types::INTEGER)]
    private int $total;

    #[ORM\Column(type: Types::INTEGER)]
    private int $remaining;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function initialize(int $total): void
    {
        $this->total = $total;
        $this->remaining = $total;
        $this->createdAt = Clock::get()->now();
    }

    public function markComplete(): void
    {
        if (null === $this->completedAt) {
            $this->completedAt = Clock::get()->now();
        }
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getRemaining(): int
    {
        return $this->remaining;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function isComplete(): bool
    {
        return $this->remaining <= 0;
    }
}
