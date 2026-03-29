<?php

declare(strict_types=1);

namespace App\Game\Infrastructure\DataFixtures\ORM;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameCategory;
use App\Game\Domain\Entity\GameScore;
use App\Game\Domain\Entity\GameSession;
use App\Game\Domain\Enum\GameLevel;
use App\Game\Domain\Enum\GameStatus;
use App\General\Domain\Rest\UuidHelper;
use App\Tests\Utils\PhpUnitUtil;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Throwable;

final class LoadGameData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<non-empty-string, non-empty-string>
     */
    public static array $uuids = [
        'category-card' => '21000000-0000-1000-8000-000000000001',
        'category-board' => '21000000-0000-1000-8000-000000000002',
        'category-arcade' => '21000000-0000-1000-8000-000000000003',
        'game-card-memory-duel' => '21000000-0000-1000-8000-000000000101',
        'game-board-kingdom-conquest' => '21000000-0000-1000-8000-000000000102',
        'game-arcade-neon-runner' => '21000000-0000-1000-8000-000000000103',
        'session-card-john-root-1' => '21000000-0000-1000-8000-000000000201',
        'session-card-john-admin-1' => '21000000-0000-1000-8000-000000000202',
        'session-card-john-user-1' => '21000000-0000-1000-8000-000000000203',
        'session-board-john-root-1' => '21000000-0000-1000-8000-000000000204',
        'session-board-john-admin-1' => '21000000-0000-1000-8000-000000000205',
        'session-board-john-user-1' => '21000000-0000-1000-8000-000000000206',
        'score-card-john-root-1' => '21000000-0000-1000-8000-000000000301',
        'score-card-john-admin-1' => '21000000-0000-1000-8000-000000000302',
        'score-card-john-user-1' => '21000000-0000-1000-8000-000000000303',
        'score-board-john-root-1' => '21000000-0000-1000-8000-000000000304',
        'score-board-john-admin-1' => '21000000-0000-1000-8000-000000000305',
        'score-board-john-user-1' => '21000000-0000-1000-8000-000000000306',
    ];

    /**
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $categories = [
            'card' => ['Card', 'Jeux de cartes compétitifs et rapides.'],
            'board' => ['Board', 'Jeux de plateau avec objectifs et stratégie.'],
            'arcade' => ['Arcade', 'Jeux d\'arcade orientés performance en temps réel.'],
        ];

        foreach ($categories as $key => [$name, $description]) {
            $category = (new GameCategory())
                ->setKey($key)
                ->setName($name)
                ->setDescription($description);
            $this->forceUuid($category, 'category-' . $key);

            $manager->persist($category);
            $this->addReference('GameCategory-' . $key, $category);
        }

        $games = [
            [
                'key' => 'card-memory-duel',
                'name' => 'Memory Duel',
                'category' => 'card',
                'level' => GameLevel::BEGINNER,
                'metadata' => ['mode' => 'solo', 'max_rounds' => 6],
            ],
            [
                'key' => 'board-kingdom-conquest',
                'name' => 'Kingdom Conquest',
                'category' => 'board',
                'level' => GameLevel::INTERMEDIATE,
                'metadata' => ['board_size' => '8x8', 'max_players' => 4],
            ],
            [
                'key' => 'arcade-neon-runner',
                'name' => 'Neon Runner',
                'category' => 'arcade',
                'level' => GameLevel::ADVANCED,
                'metadata' => ['mode' => 'time-attack', 'duration_sec' => 90],
            ],
        ];

        foreach ($games as $item) {
            $game = (new Game())
                ->setName($item['name'])
                ->setCategory($this->getReference('GameCategory-' . $item['category'], GameCategory::class))
                ->setLevel($item['level'])
                ->setStatus(GameStatus::ACTIVE)
                ->setMetadata($item['metadata']);
            $this->forceUuid($game, 'game-' . $item['key']);

            $manager->persist($game);
            $this->addReference('Game-' . $item['key'], $game);
        }

        $sessionsAndScores = [
            [
                'sessionKey' => 'session-card-john-root-1',
                'scoreKey' => 'score-card-john-root-1',
                'gameRef' => 'Game-card-memory-duel',
                'userRef' => 'User-john-root',
                'startedAt' => '2026-02-01 10:00:00',
                'endedAt' => '2026-02-01 10:06:00',
                'context' => ['is_win' => true, 'cards' => [12, 18, 20], 'bonus' => 10],
                'score' => 60,
            ],
            [
                'sessionKey' => 'session-card-john-admin-1',
                'scoreKey' => 'score-card-john-admin-1',
                'gameRef' => 'Game-card-memory-duel',
                'userRef' => 'User-john-admin',
                'startedAt' => '2026-02-02 09:00:00',
                'endedAt' => '2026-02-02 09:07:00',
                'context' => ['is_win' => true, 'cards' => [10, 15, 17], 'bonus' => 8],
                'score' => 50,
            ],
            [
                'sessionKey' => 'session-card-john-user-1',
                'scoreKey' => 'score-card-john-user-1',
                'gameRef' => 'Game-card-memory-duel',
                'userRef' => 'User-john-user',
                'startedAt' => '2026-02-03 08:00:00',
                'endedAt' => '2026-02-03 08:05:00',
                'context' => ['is_win' => false, 'cards' => [8, 7, 9], 'bonus' => 0],
                'score' => 24,
            ],
            [
                'sessionKey' => 'session-board-john-root-1',
                'scoreKey' => 'score-board-john-root-1',
                'gameRef' => 'Game-board-kingdom-conquest',
                'userRef' => 'User-john-root',
                'startedAt' => '2026-02-04 17:00:00',
                'endedAt' => '2026-02-04 17:25:00',
                'context' => ['is_win' => true, 'objectives' => [20, 25, 18], 'turn_penalty' => 4],
                'score' => 59,
            ],
            [
                'sessionKey' => 'session-board-john-admin-1',
                'scoreKey' => 'score-board-john-admin-1',
                'gameRef' => 'Game-board-kingdom-conquest',
                'userRef' => 'User-john-admin',
                'startedAt' => '2026-02-05 17:00:00',
                'endedAt' => '2026-02-05 17:22:00',
                'context' => ['is_win' => false, 'objectives' => [14, 12, 10], 'turn_penalty' => 6],
                'score' => 30,
            ],
            [
                'sessionKey' => 'session-board-john-user-1',
                'scoreKey' => 'score-board-john-user-1',
                'gameRef' => 'Game-board-kingdom-conquest',
                'userRef' => 'User-john-user',
                'startedAt' => '2026-02-06 17:00:00',
                'endedAt' => '2026-02-06 17:24:00',
                'context' => ['is_win' => true, 'objectives' => [17, 19, 21], 'turn_penalty' => 5],
                'score' => 52,
            ],
        ];

        foreach ($sessionsAndScores as $item) {
            $session = (new GameSession())
                ->setGame($this->getReference($item['gameRef'], Game::class))
                ->setUser($this->getReference($item['userRef'], User::class))
                ->setStatus(GameStatus::COMPLETED)
                ->setStartedAt(new DateTimeImmutable($item['startedAt']))
                ->setEndedAt(new DateTimeImmutable($item['endedAt']))
                ->setContext($item['context']);
            $this->forceUuid($session, $item['sessionKey']);

            $score = (new GameScore())
                ->setSession($session)
                ->setValue($item['score'])
                ->setBreakdown($item['context'])
                ->setCalculatedAt(new DateTimeImmutable($item['endedAt']));
            $this->forceUuid($score, $item['scoreKey']);

            $manager->persist($session);
            $manager->persist($score);
            $this->addReference('GameSession-' . $item['sessionKey'], $session);
            $this->addReference('GameScore-' . $item['scoreKey'], $score);
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 43;
    }

    /**
     * @throws Throwable
     */
    private function forceUuid(object $entity, string $uuidKey): void
    {
        PhpUnitUtil::setProperty('id', UuidHelper::fromString(self::$uuids[$uuidKey]), $entity);
    }
}
