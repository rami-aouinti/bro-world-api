<?php

declare(strict_types=1);

namespace App\Game\Infrastructure\DataFixtures\ORM;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameCategory;
use App\Game\Domain\Entity\GameLevelOption;
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
        'category-puzzle' => '21000000-0000-1000-8000-000000000004',
        'category-strategy' => '21000000-0000-1000-8000-000000000005',
        'category-trivia' => '21000000-0000-1000-8000-000000000006',
        'level-beginner' => '21000000-0000-1000-8000-000000000021',
        'level-intermediate' => '21000000-0000-1000-8000-000000000022',
        'level-advanced' => '21000000-0000-1000-8000-000000000023',
        'game-card-memory-duel' => '21000000-0000-1000-8000-000000000101',
        'game-board-kingdom-conquest' => '21000000-0000-1000-8000-000000000102',
        'game-arcade-neon-runner' => '21000000-0000-1000-8000-000000000103',
        'game-puzzle-cube-logic' => '21000000-0000-1000-8000-000000000104',
        'game-strategy-fleet-command' => '21000000-0000-1000-8000-000000000105',
        'game-trivia-rapid-fire' => '21000000-0000-1000-8000-000000000106',
        'game-card-royal-flush' => '21000000-0000-1000-8000-000000000107',
        'game-arcade-sky-dash' => '21000000-0000-1000-8000-000000000108',
        'session-card-john-root-1' => '21000000-0000-1000-8000-000000000201',
        'session-card-john-admin-1' => '21000000-0000-1000-8000-000000000202',
        'session-card-john-user-1' => '21000000-0000-1000-8000-000000000203',
        'session-board-john-root-1' => '21000000-0000-1000-8000-000000000204',
        'session-board-john-admin-1' => '21000000-0000-1000-8000-000000000205',
        'session-board-john-user-1' => '21000000-0000-1000-8000-000000000206',
        'session-arcade-john-root-1' => '21000000-0000-1000-8000-000000000207',
        'session-arcade-john-admin-1' => '21000000-0000-1000-8000-000000000208',
        'session-puzzle-john-user-1' => '21000000-0000-1000-8000-000000000209',
        'session-strategy-john-root-1' => '21000000-0000-1000-8000-000000000210',
        'session-trivia-john-admin-1' => '21000000-0000-1000-8000-000000000211',
        'session-card-john-user-2' => '21000000-0000-1000-8000-000000000212',
        'score-card-john-root-1' => '21000000-0000-1000-8000-000000000301',
        'score-card-john-admin-1' => '21000000-0000-1000-8000-000000000302',
        'score-card-john-user-1' => '21000000-0000-1000-8000-000000000303',
        'score-board-john-root-1' => '21000000-0000-1000-8000-000000000304',
        'score-board-john-admin-1' => '21000000-0000-1000-8000-000000000305',
        'score-board-john-user-1' => '21000000-0000-1000-8000-000000000306',
        'score-arcade-john-root-1' => '21000000-0000-1000-8000-000000000307',
        'score-arcade-john-admin-1' => '21000000-0000-1000-8000-000000000308',
        'score-puzzle-john-user-1' => '21000000-0000-1000-8000-000000000309',
        'score-strategy-john-root-1' => '21000000-0000-1000-8000-000000000310',
        'score-trivia-john-admin-1' => '21000000-0000-1000-8000-000000000311',
        'score-card-john-user-2' => '21000000-0000-1000-8000-000000000312',
    ];

    /**
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $levels = [
            'beginner' => ['BEGINNER', 'Beginner', 'Découverte et prise en main rapide.'],
            'intermediate' => ['INTERMEDIATE', 'Intermediate', 'Mécaniques plus riches pour joueurs réguliers.'],
            'advanced' => ['ADVANCED', 'Advanced', 'Défi élevé avec optimisation et stratégie.'],
        ];

        foreach ($levels as $key => [$value, $label, $description]) {
            $level = (new GameLevelOption())
                ->setValue($value)
                ->setLabel($label)
                ->setDescription($description);
            $this->forceUuid($level, 'level-' . $key);

            $manager->persist($level);
            $this->addReference('GameLevel-' . $key, $level);
        }

        $categories = [
            'card' => ['Card', 'Jeux de cartes compétitifs et rapides.'],
            'board' => ['Board', 'Jeux de plateau avec objectifs et stratégie.'],
            'arcade' => ['Arcade', 'Jeux d\'arcade orientés performance en temps réel.'],
            'puzzle' => ['Puzzle', 'Jeux d\'énigmes, logique et réflexion.'],
            'strategy' => ['Strategy', 'Jeux de planification, tactique et gestion.'],
            'trivia' => ['Trivia', 'Jeux de quiz et culture générale multijoueur.'],
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
            ['key' => 'card-memory-duel', 'name' => 'Memory Duel', 'category' => 'card', 'level' => GameLevel::BEGINNER, 'metadata' => ['mode' => 'solo', 'max_rounds' => 6]],
            ['key' => 'board-kingdom-conquest', 'name' => 'Kingdom Conquest', 'category' => 'board', 'level' => GameLevel::INTERMEDIATE, 'metadata' => ['board_size' => '8x8', 'max_players' => 4]],
            ['key' => 'arcade-neon-runner', 'name' => 'Neon Runner', 'category' => 'arcade', 'level' => GameLevel::ADVANCED, 'metadata' => ['mode' => 'time-attack', 'duration_sec' => 90]],
            ['key' => 'puzzle-cube-logic', 'name' => 'Cube Logic', 'category' => 'puzzle', 'level' => GameLevel::BEGINNER, 'metadata' => ['grid' => '6x6', 'hints' => 3]],
            ['key' => 'strategy-fleet-command', 'name' => 'Fleet Command', 'category' => 'strategy', 'level' => GameLevel::ADVANCED, 'metadata' => ['factions' => 5, 'match_length_min' => 30]],
            ['key' => 'trivia-rapid-fire', 'name' => 'Rapid Fire Trivia', 'category' => 'trivia', 'level' => GameLevel::INTERMEDIATE, 'metadata' => ['questions' => 15, 'timer_sec' => 10]],
            ['key' => 'card-royal-flush', 'name' => 'Royal Flush Rush', 'category' => 'card', 'level' => GameLevel::INTERMEDIATE, 'metadata' => ['deck_count' => 2, 'round_time' => 45]],
            ['key' => 'arcade-sky-dash', 'name' => 'Sky Dash', 'category' => 'arcade', 'level' => GameLevel::BEGINNER, 'metadata' => ['track_count' => 4, 'boost_enabled' => true]],
        ];

        foreach ($games as $item) {
            $game = (new Game())
                ->setKey($item['key'])
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
            ['sessionKey' => 'session-card-john-root-1', 'scoreKey' => 'score-card-john-root-1', 'gameRef' => 'Game-card-memory-duel', 'userRef' => 'User-john-root', 'startedAt' => '2026-02-01 10:00:00', 'endedAt' => '2026-02-01 10:06:00', 'context' => ['is_win' => true, 'cards' => [12, 18, 20], 'bonus' => 10], 'score' => 60],
            ['sessionKey' => 'session-card-john-admin-1', 'scoreKey' => 'score-card-john-admin-1', 'gameRef' => 'Game-card-memory-duel', 'userRef' => 'User-john-admin', 'startedAt' => '2026-02-02 09:00:00', 'endedAt' => '2026-02-02 09:07:00', 'context' => ['is_win' => true, 'cards' => [10, 15, 17], 'bonus' => 8], 'score' => 50],
            ['sessionKey' => 'session-card-john-user-1', 'scoreKey' => 'score-card-john-user-1', 'gameRef' => 'Game-card-memory-duel', 'userRef' => 'User-john-user', 'startedAt' => '2026-02-03 08:00:00', 'endedAt' => '2026-02-03 08:05:00', 'context' => ['is_win' => false, 'cards' => [8, 7, 9], 'bonus' => 0], 'score' => 24],
            ['sessionKey' => 'session-board-john-root-1', 'scoreKey' => 'score-board-john-root-1', 'gameRef' => 'Game-board-kingdom-conquest', 'userRef' => 'User-john-root', 'startedAt' => '2026-02-04 17:00:00', 'endedAt' => '2026-02-04 17:25:00', 'context' => ['is_win' => true, 'objectives' => [20, 25, 18], 'turn_penalty' => 4], 'score' => 59],
            ['sessionKey' => 'session-board-john-admin-1', 'scoreKey' => 'score-board-john-admin-1', 'gameRef' => 'Game-board-kingdom-conquest', 'userRef' => 'User-john-admin', 'startedAt' => '2026-02-05 17:00:00', 'endedAt' => '2026-02-05 17:22:00', 'context' => ['is_win' => false, 'objectives' => [14, 12, 10], 'turn_penalty' => 6], 'score' => 30],
            ['sessionKey' => 'session-board-john-user-1', 'scoreKey' => 'score-board-john-user-1', 'gameRef' => 'Game-board-kingdom-conquest', 'userRef' => 'User-john-user', 'startedAt' => '2026-02-06 17:00:00', 'endedAt' => '2026-02-06 17:24:00', 'context' => ['is_win' => true, 'objectives' => [17, 19, 21], 'turn_penalty' => 5], 'score' => 52],
            ['sessionKey' => 'session-arcade-john-root-1', 'scoreKey' => 'score-arcade-john-root-1', 'gameRef' => 'Game-arcade-neon-runner', 'userRef' => 'User-john-root', 'startedAt' => '2026-02-07 15:00:00', 'endedAt' => '2026-02-07 15:05:00', 'context' => ['is_win' => true, 'distance' => 3800, 'combo' => 22], 'score' => 88],
            ['sessionKey' => 'session-arcade-john-admin-1', 'scoreKey' => 'score-arcade-john-admin-1', 'gameRef' => 'Game-arcade-sky-dash', 'userRef' => 'User-john-admin', 'startedAt' => '2026-02-08 15:00:00', 'endedAt' => '2026-02-08 15:09:00', 'context' => ['is_win' => true, 'distance' => 2400, 'combo' => 9], 'score' => 49],
            ['sessionKey' => 'session-puzzle-john-user-1', 'scoreKey' => 'score-puzzle-john-user-1', 'gameRef' => 'Game-puzzle-cube-logic', 'userRef' => 'User-john-user', 'startedAt' => '2026-02-09 11:00:00', 'endedAt' => '2026-02-09 11:16:00', 'context' => ['is_win' => true, 'moves' => 84, 'hints_used' => 1], 'score' => 42],
            ['sessionKey' => 'session-strategy-john-root-1', 'scoreKey' => 'score-strategy-john-root-1', 'gameRef' => 'Game-strategy-fleet-command', 'userRef' => 'User-john-root', 'startedAt' => '2026-02-10 18:00:00', 'endedAt' => '2026-02-10 18:46:00', 'context' => ['is_win' => true, 'territories' => 14, 'resource_efficiency' => 0.82], 'score' => 94],
            ['sessionKey' => 'session-trivia-john-admin-1', 'scoreKey' => 'score-trivia-john-admin-1', 'gameRef' => 'Game-trivia-rapid-fire', 'userRef' => 'User-john-admin', 'startedAt' => '2026-02-11 12:00:00', 'endedAt' => '2026-02-11 12:07:00', 'context' => ['is_win' => true, 'correct_answers' => 13, 'streak' => 7], 'score' => 71],
            ['sessionKey' => 'session-card-john-user-2', 'scoreKey' => 'score-card-john-user-2', 'gameRef' => 'Game-card-royal-flush', 'userRef' => 'User-john-user', 'startedAt' => '2026-02-12 20:00:00', 'endedAt' => '2026-02-12 20:10:00', 'context' => ['is_win' => false, 'hands_won' => 2, 'bonus' => 3], 'score' => 19],
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
