<?php

declare(strict_types=1);

namespace App\Platform\Transport\EventListener;

use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\Platform;
use App\Platform\Domain\Entity\Plugin;
use App\Platform\Domain\Enum\PlatformKey;
use App\Recruit\Domain\Entity\Recruit;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * @package App\Platform
 */
class PlatformEntityEventListener
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

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

        if ($entity instanceof Platform || $entity instanceof Plugin || $entity instanceof Application) {
            $entity->ensureGeneratedPhoto();

            if ($entity instanceof Application) {
                $entity->ensureGeneratedSlug();
                $this->createRecruitWhenNeeded($entity);
            }
        }
    }

    private function createRecruitWhenNeeded(Application $application): void
    {
        if ($application->getPlatform()?->getPlatformKey() !== PlatformKey::RECRUIT) {
            return;
        }

        $existingRecruit = $this->entityManager->getRepository(Recruit::class)->findOneBy([
            'application' => $application,
        ]);

        if ($existingRecruit instanceof Recruit) {
            return;
        }

        $recruit = (new Recruit())
            ->setApplication($application);

        $this->entityManager->persist($recruit);
    }
}
