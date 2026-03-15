<?php

declare(strict_types=1);

namespace App\Quiz\Application\Service;

use App\Quiz\Domain\Enum\QuizCategory;
use App\Quiz\Domain\Enum\QuizLevel;
use Symfony\Contracts\Cache\CacheInterface;

use function array_merge;
use function array_values;
use function sprintf;

final readonly class QuizCacheService
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function buildQuizReadKey(string $applicationSlug, ?string $level, ?string $category, bool $includeCorrection): string
    {
        return sprintf(
            'quiz_%s_%s_%s_%s',
            $applicationSlug,
            (string)$level,
            (string)$category,
            $includeCorrection ? 'with_correction' : 'public',
        );
    }

    public function buildQuizStatsKey(string $applicationSlug): string
    {
        return sprintf('quiz_stats_%s', $applicationSlug);
    }

    public function invalidateByApplicationSlug(string $applicationSlug): void
    {
        foreach ($this->buildKeysToInvalidate($applicationSlug) as $cacheKey) {
            $this->cache->delete($cacheKey);
        }
    }

    /**
     * @return list<string>
     */
    private function buildKeysToInvalidate(string $applicationSlug): array
    {
        $levels = array_merge([''], array_values(array_map(static fn (QuizLevel $level): string => $level->value, QuizLevel::cases())));
        $categories = array_merge([''], array_values(array_map(static fn (QuizCategory $category): string => $category->value, QuizCategory::cases())));

        $keys = [
            $this->buildQuizStatsKey($applicationSlug),
        ];

        foreach ($levels as $level) {
            foreach ($categories as $category) {
                $keys[] = $this->buildQuizReadKey($applicationSlug, $level, $category, false);
                $keys[] = $this->buildQuizReadKey($applicationSlug, $level, $category, true);
            }
        }

        return $keys;
    }
}
