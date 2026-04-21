<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Enum\BlogType;
use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\ApplicationPlugin;
use App\Platform\Domain\Enum\PlatformKey;
use App\Platform\Domain\Enum\PluginKey;
use Doctrine\ORM\EntityManagerInterface;

use function preg_replace;
use function sprintf;
use function strtolower;
use function trim;

final readonly class CrmEntityBlogProvisioningService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(Project|Sprint|Task|TaskRequest $subject): void
    {
        if ($subject->getBlog() instanceof Blog) {
            return;
        }

        $application = $this->resolveApplication($subject);
        if (!$application instanceof Application) {
            return;
        }

        if ($application->getPlatform()?->getPlatformKey() !== PlatformKey::CRM || !$this->hasBlogPlugin($application)) {
            return;
        }

        $owner = $application->getUser();
        if ($owner === null) {
            return;
        }

        $blog = new Blog()
            ->setApplication($application)
            ->setOwner($owner)
            ->setType($this->resolveBlogType($subject))
            ->setTitle($this->buildTitle($subject))
            ->setSlug($this->buildUniqueSlug($subject));

        $subject->setBlog($blog);
        $this->entityManager->persist($blog);
    }

    private function resolveApplication(Project|Sprint|Task|TaskRequest $subject): ?Application
    {
        if ($subject instanceof TaskRequest) {
            $subject = $subject->getTask();
        }

        if ($subject instanceof Sprint) {
            $subject = $subject->getProject();
        }

        return $subject instanceof Project
            ? $subject->getCompany()?->getCrm()?->getApplication()
            : $subject?->getProject()?->getCompany()?->getCrm()?->getApplication();
    }

    private function hasBlogPlugin(Application $application): bool
    {
        foreach ($application->getApplicationPlugins() as $applicationPlugin) {
            if (!$applicationPlugin instanceof ApplicationPlugin) {
                continue;
            }

            if ($applicationPlugin->getPlugin()?->getPluginKey() === PluginKey::BLOG) {
                return true;
            }
        }

        return false;
    }

    private function resolveBlogType(Project|Sprint|Task|TaskRequest $subject): BlogType
    {
        return match (true) {
            $subject instanceof Project => BlogType::PROJECT,
            $subject instanceof Sprint => BlogType::SPRINT,
            $subject instanceof TaskRequest => BlogType::TASK_REQUEST,
            default => BlogType::TASK,
        };
    }

    private function buildTitle(Project|Sprint|Task|TaskRequest $subject): string
    {
        return match (true) {
            $subject instanceof Project => sprintf('Project: %s', $subject->getName()),
            $subject instanceof Sprint => sprintf('Sprint: %s', $subject->getName()),
            $subject instanceof TaskRequest => sprintf('Task Request: %s', $subject->getTitle()),
            default => sprintf('Task: %s', $subject->getTitle()),
        };
    }

    private function buildUniqueSlug(Project|Sprint|Task|TaskRequest $subject): string
    {
        $prefix = match (true) {
            $subject instanceof Project => 'project',
            $subject instanceof Sprint => 'sprint',
            $subject instanceof TaskRequest => 'task-request',
            default => 'task',
        };

        $name = match (true) {
            $subject instanceof Project, $subject instanceof Sprint => $subject->getName(),
            default => $subject->getTitle(),
        };

        $baseSlug = $this->slugify(sprintf('%s-%s', $prefix, $name));
        $slug = $baseSlug;
        $index = 1;

        while (
            $this->entityManager->getRepository(Blog::class)->findOneBy([
                'slug' => $slug,
            ]) instanceof Blog
        ) {
            $index++;
            $slug = sprintf('%s-%d', $baseSlug, $index);
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));

        return $slug !== '' ? $slug : 'crm-entity-blog';
    }
}
