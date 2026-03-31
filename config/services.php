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

use IQ2i\MessengerImportBundle\EventSubscriber\ImportBatchSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('iq2i_messenger_import.event_subscriber', ImportBatchSubscriber::class)
        ->tag('kernel.event_subscriber');
};
