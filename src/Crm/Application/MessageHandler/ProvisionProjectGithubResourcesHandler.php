<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\ProjectCreated;
use App\Crm\Application\Message\ProvisionProjectGithubResources;
use App\Crm\Application\Service\CrmGithubOwnerResolver;
use App\Crm\Application\Service\ProjectGithubProvisioningService;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProvisionProjectGithubResourcesHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectGithubProvisioningService $projectGithubProvisioningService,
        private CrmGithubOwnerResolver $crmGithubOwnerResolver,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ProvisionProjectGithubResources $message): void
    {
        $this->provision($message->projectId, null);
    }

    #[AsMessageHandler]
    public function onProjectCreated(ProjectCreated $message): void
    {
        $this->provision($message->projectId, $message->applicationSlug);
    }

    private function provision(string $projectId, ?string $applicationSlug): void
    {
        $project = $this->projectRepository->find($projectId);
        if ($project === null) {
            return;
        }

        $resolvedApplicationSlug = $applicationSlug ?? (string)($project->getCompany()?->getCrm()?->getApplication()?->getSlug() ?? '');
        $repositoryName = $project->getCode() !== null && $project->getCode() !== '' ? $project->getCode() : $project->getName();
        $repositoryOwner = $this->crmGithubOwnerResolver->resolve($resolvedApplicationSlug);

        $this->projectGithubProvisioningService->provision($project, $repositoryName, $repositoryOwner);
        $this->entityManager->flush();
    }
}
