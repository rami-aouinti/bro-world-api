<?php

declare(strict_types=1);

namespace App\Blog\Transport\Command;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogTag;
use App\Blog\Domain\Enum\BlogType;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\Blog\Infrastructure\Repository\BlogTagRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_filter;
use function array_slice;
use function array_unique;
use function explode;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function shuffle;
use function strtolower;
use function strip_tags;
use function trim;

#[AsCommand(
    name: self::NAME,
    description: 'Generate clean AI posts (with tags extraction).',
)]
final class GenerateAiPostsCommand extends Command
{
    public const string NAME = 'app:generate-ai-posts';

    private const string AI_URL = 'http://127.0.0.1:11434/api/generate';
    private const string AI_MODEL = 'phi';

    private const array TOPICS = [
        'Symfony 6',
        'Docker',
        'Nuxt Vue Js',
        'Elastic Search',
        'Messenger RabbitMQ',
        'Redis Cache',
        'MongoDB',
        'Cron Jobs'
    ];

    private const array AUTHORS = [
        'john-root',
        'john-admin',
        'john-user',
    ];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly BlogRepository $blogRepository,
        private readonly BlogPostRepository $blogPostRepository,
        private readonly BlogTagRepository $tagRepository,
        private readonly SluggerInterface $slugger,
        private readonly CacheInvalidationService $cacheInvalidationService,
    ) {
        parent::__construct();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $topics = self::TOPICS;
        shuffle($topics);

        $selected = array_slice($topics, 0, 3);

        foreach ($selected as $i => $topic) {

            $author = $this->resolveAuthor(self::AUTHORS[$i % 3]);
            $blog = $this->resolveBlog();

            [$title, $content] = $this->generatePost($topic);

            $post = new BlogPost()
                ->setBlog($blog)
                ->setAuthor($author)
                ->setTitle($title)
                ->setSlug($this->slug($title, $topic))
                ->setContent($content)
                ->setIsPinned(false);

            // 🔥 TAG EXTRACTION + LINK
            $tags = $this->extractTags($content);

            foreach ($tags as $tagName) {
                $tag = $this->resolveTag($blog, $tagName);
                $post->addTag($tag);
            }

            $this->blogPostRepository->save($post, false);

            $output->writeln("✔ Post created: $topic by {$author->getUsername()}");
        }

        $this->em->flush();
        $this->cacheInvalidationService->invalidateBlogCaches(null, []);

        $output->writeln('🎉 3 clean AI posts generated.');

        return Command::SUCCESS;
    }

    private function resolveAuthor(string $username): User
    {
        $user = $this->userRepository->findOneBy(['username' => $username]);

        if (!$user) {
            throw new RuntimeException("User not found: $username");
        }

        return $user;
    }

    private function resolveBlog(): ?Blog
    {
        $blog = $this->blogRepository->findOneBy([
            'type' => BlogType::GENERAL,
        ]);

        if (!$blog) {
            throw new RuntimeException('Blog not found');
        }

        return $blog;
    }

    /**
     * @param string $topic
     * @return array{0: string, 1: string}
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function generatePost(string $topic): array
    {
        $prompt = "Write a viral social media post about: $topic.
Max 100 words. Add 3 hashtags at the end.
Do NOT use quotes.";

        $response = $this->client->request('POST', self::AI_URL, [
            'timeout' => 120,
            'json' => [
                'model' => self::AI_MODEL,
                'prompt' => $prompt,
                'stream' => false,
            ],
        ]);

        $data = $response->toArray(false);
        $raw = trim((string)($data['response'] ?? ''));

        return $this->clean($raw, $topic);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function clean(string $text, string $topic): array
    {
        $text = trim($text, "\"'");
        $text = strip_tags($text);

        $lines = array_unique(array_filter(explode("\n", $text)));
        $text = implode("\n", $lines);

        if (preg_match('/^(.+)\1$/s', $text, $match)) {
            $text = $match[1];
        }

        $text = trim(preg_replace('/\n+/', "\n", $text));

        $lines = explode("\n", $text);
        $title = trim($lines[0] ?? '');

        if ($title === '') {
            $title = "Post about $topic";
        }

        return [$title, $text];
    }

    /**
     * 🔥 Extract hashtags
     *
     * @return string[]
     */
    private function extractTags(string $content): array
    {
        preg_match_all('/#([\p{L}\p{N}_-]+)/u', $content, $matches);

        return array_unique($matches[1] ?? []);
    }

    /**
     * 🔥 Get or create tag
     */
    private function resolveTag(Blog $blog, string $name): BlogTag
    {
        $name = strtolower(trim($name));

        $tag = $this->tagRepository->findOneBy(['blog' => $blog, 'label' => $name]);

        if (!$tag) {
            $tag = new BlogTag();
            $tag->setBlog($blog)
                ->setLabel($name);

            $this->em->persist($tag);
        }

        return $tag;
    }

    private function slug(string $title, string $topic): string
    {
        $base = strtolower($this->slugger->slug($topic . ' ' . $title)->toString());
        return trim($base, '-') ?: 'ai-post';
    }
}
