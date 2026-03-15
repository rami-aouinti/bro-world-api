<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Enum\BlogType;
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

final readonly class CrmTaskBlogProvisioningService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(Task|TaskRequest $subject): void
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

        $blog = (new Blog())
            ->setApplication($application)
            ->setOwner($owner)
            ->setType(BlogType::APPLICATION)
            ->setTitle($this->buildTitle($subject))
            ->setSlug($this->buildUniqueSlug($subject));

        $subject->setBlog($blog);
        $this->entityManager->persist($blog);
    }

    private function resolveApplication(Task|TaskRequest $subject): ?Application
    {
        if ($subject instanceof TaskRequest) {
            $subject = $subject->getTask();
        }

        return $subject?->getProject()?->getCompany()?->getCrm()?->getApplication();
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

    private function buildTitle(Task|TaskRequest $subject): string
    {
        if ($subject instanceof TaskRequest) {
            return sprintf('Task Request: %s', $subject->getTitle());
        }

        return sprintf('Task: %s', $subject->getTitle());
    }

    private function buildUniqueSlug(Task|TaskRequest $subject): string
    {
        $prefix = $subject instanceof TaskRequest ? 'task-request' : 'task';
        $baseSlug = $this->slugify(sprintf('%s-%s', $prefix, $subject->getTitle()));
        $slug = $baseSlug;
        $index = 1;

        while ($this->entityManager->getRepository(Blog::class)->findOneBy(['slug' => $slug]) instanceof Blog) {
            $index++;
            $slug = sprintf('%s-%d', $baseSlug, $index);
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-'));

        return $slug !== '' ? $slug : 'crm-task-blog';
    }
}

