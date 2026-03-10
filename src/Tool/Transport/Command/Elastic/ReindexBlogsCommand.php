<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Elastic;

use App\Blog\Application\Projection\BlogProjection;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_map;

#[AsCommand(
    name: self::NAME,
    description: 'Index blogs in Elasticsearch.',
)]
final class ReindexBlogsCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'elastic:reindex:blogs';

    public function __construct(
        private readonly BlogRepository $blogRepository,
        private readonly ElasticsearchServiceInterface $elasticsearchService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexed = 0;

        /** @var Blog $blog */
        foreach ($this->blogRepository->findBy([], [
            'createdAt' => 'DESC',
        ]) as $blog) {
            $this->elasticsearchService->index(BlogProjection::INDEX_NAME, $blog->getId(), [
                'id' => $blog->getId(),
                'title' => $blog->getTitle(),
                'type' => $blog->getType()->value,
                'postStatus' => $blog->getPostStatus()->value,
                'commentStatus' => $blog->getCommentStatus()->value,
                'applicationSlug' => $blog->getApplication()?->getSlug(),
                'ownerId' => $blog->getOwner()->getId(),
                'postsCount' => $blog->getPosts()->count(),
                'postContents' => array_map(
                    static fn ($post): string => (string)$post->getContent(),
                    $blog->getPosts()->toArray(),
                ),
                'updatedAt' => $blog->getUpdatedAt()?->format(DATE_ATOM),
            ]);
            $indexed++;
        }

        if ($input->isInteractive()) {
            $this->getSymfonyStyle($input, $output)->success('Blogs indexed: ' . $indexed);
        }

        return Command::SUCCESS;
    }
}
