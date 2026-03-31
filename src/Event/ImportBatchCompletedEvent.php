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

namespace IQ2i\MessengerImportBundle\Event;

final class ImportBatchCompletedEvent
{
    public function __construct(
        private readonly string $batchId,
        private readonly int $total,
    ) {
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
