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

interface ImportBatchInterface
{
    public function initialize(int $total): void;

    public function markComplete(): void;

    public function getTotal(): int;

    public function getRemaining(): int;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getCompletedAt(): ?\DateTimeImmutable;

    public function isComplete(): bool;
}
