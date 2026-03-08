<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Chat\Application\Service\ConversationCreatorService;
use App\Chat\Application\Service\ConversationParticipantCreatorService;
use App\Chat\Domain\Repository\Interfaces\ChatRepositoryInterface;
use App\Recruit\Domain\Entity\Application;
use DomainException;

final class ApplicationDiscussionBootstrapService
{
    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly ConversationCreatorService $conversationCreatorService,
        private readonly ConversationParticipantCreatorService $conversationParticipantCreatorService,
    ) {
    }

    public function bootstrap(Application $application): void
    {
        $jobOwner = $application->getJob()->getOwner();
        if ($jobOwner === null) {
            throw new DomainException('Cannot bootstrap discussion: job owner is missing.');
        }

        $applicantUser = $application->getApplicant()->getUser();
        if ($applicantUser === null) {
            throw new DomainException('Cannot bootstrap discussion: applicant user is missing.');
        }

        $platformApplication = $application->getJob()->getRecruit()?->getApplication();
        if ($platformApplication === null) {
            throw new DomainException('Cannot bootstrap discussion: platform application is missing for the job.');
        }

        $chat = $this->chatRepository->findOneByApplication($platformApplication);
        if ($chat === null) {
            throw new DomainException('Cannot bootstrap discussion: chat is not provisioned for this application.');
        }

        $applicationSlug = $platformApplication->getSlug();
        if ($applicationSlug === '') {
            throw new DomainException('Cannot bootstrap discussion: platform application slug is missing.');
        }

        $conversation = $this->conversationCreatorService->getOrCreate($chat, $applicationSlug);
        $this->conversationParticipantCreatorService->getOrCreate($conversation, $jobOwner);
        $this->conversationParticipantCreatorService->getOrCreate($conversation, $applicantUser);
    }
}
