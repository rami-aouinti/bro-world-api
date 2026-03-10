<?php

declare(strict_types=1);

namespace App\Platform\Transport\EventListener;

use App\General\Domain\Service\Interfaces\MailerServiceInterface;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\ApplicationPlugin;
use App\Platform\Domain\Entity\Platform;
use App\Platform\Domain\Entity\Plugin;
use App\Platform\Domain\Enum\PlatformKey;
use App\Recruit\Domain\Entity\Recruit;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment as Twig;

use function array_filter;
use function array_map;
use function array_values;
use function trim;

/**
 * @package App\Platform
 */
class PlatformEntityEventListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerServiceInterface $mailerService,
        private readonly Twig $twig,
        #[Autowire('%env(resolve:APP_SENDER_EMAIL)%')]
        private readonly string $appSenderEmail,
    ) {
    }

    public function prePersist(LifecycleEventArgs $event): void
    {
        $this->process($event);
    }

    public function preUpdate(LifecycleEventArgs $event): void
    {
        $this->process($event);
    }

    public function postPersist(LifecycleEventArgs $event): void
    {
        $entity = $event->getObject();

        if (!$entity instanceof Application) {
            return;
        }

        $ownerEmail = $entity->getUser()?->getEmail();
        if ($ownerEmail === null || $ownerEmail === '') {
            return;
        }

        $body = $this->twig->render('Emails/application_created.html.twig', [
            'application' => $entity,
            'platformName' => $entity->getPlatform()?->getName() ?? 'Unknown platform',
            'plugins' => $this->extractPluginNames($entity),
        ]);

        $this->mailerService->sendMail(
            'Your platform application has been created',
            $this->appSenderEmail,
            $ownerEmail,
            $body,
        );
    }

    public function postUpdate(LifecycleEventArgs $event): void
    {
        $entity = $event->getObject();

        if (!$entity instanceof Application) {
            return;
        }

        $ownerEmail = $entity->getUser()?->getEmail();
        if ($ownerEmail === null || $ownerEmail === '') {
            return;
        }

        $body = $this->twig->render('Emails/application_updated.html.twig', [
            'application' => $entity,
            'platformName' => $entity->getPlatform()?->getName() ?? 'Unknown platform',
            'plugins' => $this->extractPluginNames($entity),
        ]);

        $this->mailerService->sendMail(
            'Your platform application was updated successfully',
            $this->appSenderEmail,
            $ownerEmail,
            $body,
        );
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

    /**
     * @return array<int, string>
     */
    private function extractPluginNames(Application $application): array
    {
        $pluginNames = array_map(static function (ApplicationPlugin $applicationPlugin): string {
            $plugin = $applicationPlugin->getPlugin();

            return trim($plugin?->getName() ?? $plugin?->getPluginKeyValue() ?? '');
        }, $application->getApplicationPlugins()->toArray());

        return array_values(array_filter($pluginNames, static fn (string $name): bool => $name !== ''));
    }
}
