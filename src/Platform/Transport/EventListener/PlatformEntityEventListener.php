<?php

declare(strict_types=1);

namespace App\Platform\Transport\EventListener;

use App\Platform\Domain\Entity\Platform;
use App\Platform\Domain\Entity\Plugin;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * @package App\Platform
 */
class PlatformEntityEventListener
{
    public function prePersist(LifecycleEventArgs $event): void
    {
        $this->process($event);
    }

    public function preUpdate(LifecycleEventArgs $event): void
    {
        $this->process($event);
    }

    private function process(LifecycleEventArgs $event): void
    {
        $entity = $event->getObject();

        if ($entity instanceof Platform || $entity instanceof Plugin) {
            $entity->ensureGeneratedPhoto();
        }
    }
}
