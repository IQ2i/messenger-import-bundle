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

trait ImportBatchRepositoryTrait
{
    public function decrement(string $batchId): ?ImportBatchInterface
    {
        $this->getEntityManager()
            ->createQuery(\sprintf(
                'UPDATE %s b SET b.remaining = b.remaining - 1 WHERE b.id = :id AND b.remaining > 0',
                $this->getEntityName()
            ))
            ->setParameter('id', $batchId)
            ->execute();

        $this->getEntityManager()->clear();

        $batch = $this->find($batchId);

        if ($batch instanceof ImportBatchInterface && $batch->isComplete() && null === $batch->getCompletedAt()) {
            $batch->markComplete();
            $this->getEntityManager()->flush();
        }

        return $batch;
    }
}
