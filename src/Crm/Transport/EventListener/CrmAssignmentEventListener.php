<?php

declare(strict_types=1);

namespace App\Crm\Transport\EventListener;

use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\General\Domain\Service\Interfaces\MailerServiceInterface;
use App\Notification\Application\Service\NotificationPublisher;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment as Twig;

use function sprintf;

final class CrmAssignmentEventListener
{
    /**
     * @var array<int, array{entityType: string, entityId: string, assignee: User}>
     */
    private array $pendingAssignments = [];

    public function __construct(
        private readonly MailerServiceInterface $mailerService,
        private readonly NotificationPublisher $notificationPublisher,
        private readonly Security $security,
        private readonly Twig $twig,
        #[Autowire('%env(resolve:APP_SENDER_EMAIL)%')]
        private readonly string $appSenderEmail,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            if (!$collection instanceof PersistentCollection) {
                continue;
            }

            if (!$this->isSupportedAssigneeCollection($collection)) {
                continue;
            }

            $owner = $collection->getOwner();
            foreach ($collection->getInsertDiff() as $inserted) {
                if (!$inserted instanceof User) {
                    continue;
                }

                $this->pendingAssignments[] = [
                    'entityType' => $this->resolveEntityType($owner),
                    'entityId' => $owner->getId(),
                    'assignee' => $inserted,
                ];
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pendingAssignments === []) {
            return;
        }

        $assignments = $this->pendingAssignments;
        $this->pendingAssignments = [];

        $actor = $this->security->getUser();
        if (!$actor instanceof User) {
            return;
        }

        foreach ($assignments as $assignment) {
            $assignee = $assignment['assignee'];
            $context = [
                'assignee' => $assignee,
                'entityType' => $assignment['entityType'],
                'entityId' => $assignment['entityId'],
                'actor' => $actor,
            ];

            try {
                $body = $this->twig->render('Emails/crm_assignment_created.html.twig', $context);
                $this->mailerService->sendMail(
                    'Nouvelle assignation CRM',
                    $this->appSenderEmail,
                    $assignee->getEmail(),
                    $body,
                );
            } catch (\Throwable) {
            }

            try {
                $this->notificationPublisher->publish(
                    from: $actor,
                    recipient: $assignee,
                    title: sprintf('Vous avez été assigné à un(e) %s', $assignment['entityType']),
                    type: 'crm_assignment',
                    description: sprintf('ID: %s', $assignment['entityId']),
                );
            } catch (\Throwable) {
            }
        }
    }

    private function isSupportedAssigneeCollection(PersistentCollection $collection): bool
    {
        return $collection->getMapping()['fieldName'] === 'assignees'
            && ($collection->getOwner() instanceof Project
                || $collection->getOwner() instanceof Task
                || $collection->getOwner() instanceof Sprint
                || $collection->getOwner() instanceof TaskRequest);
    }

    private function resolveEntityType(object $entity): string
    {
        return match (true) {
            $entity instanceof Project => 'project',
            $entity instanceof Task => 'task',
            $entity instanceof Sprint => 'sprint',
            $entity instanceof TaskRequest => 'task request',
            default => 'élément',
        };
    }
}
