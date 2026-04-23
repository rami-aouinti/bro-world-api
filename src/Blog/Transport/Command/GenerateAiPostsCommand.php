<?php

declare(strict_types=1);

namespace App\Blog\Transport\Command;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Enum\BlogType;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function array_slice;
use function implode;
use function json_decode;
use function preg_match;
use function shuffle;
use function sprintf;
use function strip_tags;
use function strtolower;
use function trim;

#[AsCommand(
    name: self::NAME,
    description: 'Generate 3 AI posts and publish them into blog_post as john-root.',
)]
final class GenerateAiPostsCommand extends Command
{
    final public const string NAME = 'app:generate-ai-posts';

    private const string AI_URL = 'http://127.0.0.1:11434/api/generate';
    private const string AI_MODEL = 'phi';
    private const string SYSTEM_USERNAME = 'john-root';

    /**
     * @var list<string>
     */
    private const array TOPICS = [
        'AI startups',
        'social media growth',
        'SaaS business ideas',
        'productivity tips',
        'tech trends',
    ];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly BlogRepository $blogRepository,
        private readonly BlogPostRepository $blogPostRepository,
        private readonly SluggerInterface $slugger,
        private readonly CacheInvalidationService $cacheInvalidationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $author = $this->resolveAuthor();
        $blog = $this->resolveTargetBlog($author);

        $topics = self::TOPICS;
        shuffle($topics);
        $selectedTopics = array_slice($topics, 0, 3);

        foreach ($selectedTopics as $topic) {
            [$title, $content] = $this->generatePostForTopic($topic);

            $post = (new BlogPost())
                ->setBlog($blog)
                ->setAuthor($author)
                ->setTitle($title)
                ->setSlug($this->buildUniqueSlug($title, $topic))
                ->setContent($content)
                ->setIsPinned(false);

            $this->blogPostRepository->save($post, false);
            $output->writeln(sprintf('Created blog post for topic "%s" as %s.', $topic, self::SYSTEM_USERNAME));
        }

        $this->entityManager->flush();
        $this->cacheInvalidationService->invalidateBlogCaches($blog->getApplication()?->getSlug(), [$author->getId()]);

        $output->writeln('3 AI posts generated in blog_post table.');

        return Command::SUCCESS;
    }

    private function resolveAuthor(): User
    {
        $author = $this->userRepository->findOneBy([
            'username' => self::SYSTEM_USERNAME,
        ]);

        if (!$author instanceof User) {
            throw new \RuntimeException(sprintf('User "%s" not found.', self::SYSTEM_USERNAME));
        }

        return $author;
    }

    private function resolveTargetBlog(User $author): Blog
    {
        $blog = $this->blogRepository->findOneBy([
            'owner' => $author,
            'type' => BlogType::GENERAL,
        ]);

        if (!$blog instanceof Blog) {
            $blog = $this->blogRepository->findOneBy([
                'owner' => $author,
            ]);
        }

        if (!$blog instanceof Blog) {
            throw new \RuntimeException(sprintf('No blog found for user "%s".', self::SYSTEM_USERNAME));
        }

        return $blog;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function generatePostForTopic(string $topic): array
    {
        $prompt = <<<PROMPT
You are a viral content creator.

Write one social media post about: {$topic}

Rules:
- Hook at the start
- Engaging tone
- Max 150 words
- Exactly 3 hashtags
- Make it viral and emotionally engaging

Return ONLY valid JSON with this shape:
{"title":"...","content":"..."}
PROMPT;

        $response = $this->client->request('POST', self::AI_URL, [
            'json' => [
                'model' => self::AI_MODEL,
                'prompt' => $prompt,
                'stream' => false,
            ],
        ]);

        /** @var array{response?: string} $payload */
        $payload = $response->toArray(false);
        $raw = trim((string)($payload['response'] ?? ''));

        try {
            /** @var array{title?: string, content?: string} $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            $title = trim((string)($decoded['title'] ?? ''));
            $content = trim((string)($decoded['content'] ?? ''));

            if ($title !== '' && $content !== '') {
                return [$title, $content];
            }
        } catch (Throwable) {
            // Fallback below.
        }

        if (preg_match('/^(.+?)\n+/s', $raw, $matches) === 1) {
            return [trim(strip_tags($matches[1])), trim($raw)];
        }

        return [sprintf('Post about %s', $topic), $raw !== '' ? $raw : sprintf('Topic: %s', $topic)];
    }

    private function buildUniqueSlug(string $title, string $topic): string
    {
        $baseSlug = strtolower($this->slugger->slug(sprintf('%s %s', $topic, $title))->toString());
        $candidate = trim($baseSlug, '-');

        if ($candidate === '') {
            $candidate = 'ai-post';
        }

        if (!$this->blogPostRepository->findOneBy(['slug' => $candidate]) instanceof BlogPost) {
            return $candidate;
        }

        $index = 2;
        while ($this->blogPostRepository->findOneBy(['slug' => sprintf('%s-%d', $candidate, $index)]) instanceof BlogPost) {
            ++$index;
        }

        return sprintf('%s-%d', $candidate, $index);
    }
}
