<?php

declare(strict_types=1);

namespace App\Quiz\Application\Service;

use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class QuizReadService
{
    public function __construct(
        private QuizRepository $quizRepository,
        private CacheInterface $cache,
        private ElasticsearchServiceInterface $elasticsearchService
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getByApplicationSlug(string $slug): array
    {
        return $this->cache->get('quiz_' . $slug, function (ItemInterface $item) use ($slug): array {
            $item->expiresAfter(120);
            $quiz = $this->quizRepository->createQueryBuilder('q')
                ->leftJoin('q.application', 'a')
                ->leftJoin('q.questions', 'qq')->addSelect('qq')
                ->leftJoin('qq.answers', 'qa')->addSelect('qa')
                ->andWhere('a.slug = :slug')->setParameter('slug', $slug)->getQuery()->getOneOrNullResult();

            if (!$quiz instanceof Quiz) {
                return [];
            }

            return [
                'id' => $quiz->getId(),
                'applicationSlug' => $slug,
                'configuration' => $quiz->getConfiguration()?->getConfigurationValue(),
                'questions' => array_map(static fn ($q): array => [
                    'id' => $q->getId(),
                    'title' => $q->getTitle(),
                    'level' => $q->getLevel(),
                    'category' => $q->getCategory(),
                    'answers' => array_map(static fn ($a): array => [
                        'id' => $a->getId(),
                        'label' => $a->getLabel(),
                        'correct' => $a->isCorrect(),
                    ], $q->getAnswers()->toArray()),
                ], $quiz->getQuestions()->toArray()),
            ];
        });
    }
}
