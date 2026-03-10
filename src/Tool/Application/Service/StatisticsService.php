<?php

declare(strict_types=1);

namespace App\Tool\Application\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

class StatisticsService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobalStatistics(): array
    {
        $now = new DateTimeImmutable();
        $startOfWeek = $now->modify('monday this week')->setTime(0, 0, 0);
        $startOfMonth = $now->modify('first day of this month')->setTime(0, 0, 0);
        $startOfYear = $now->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0, 0);
        $lastSevenDays = $now->modify('-7 days');

        return [
            'users' => [
                'total' => $this->countAll('user'),
                'thisWeek' => $this->countSince('user', $startOfWeek),
                'thisMonth' => $this->countSince('user', $startOfMonth),
                'thisYear' => $this->countSince('user', $startOfYear),
            ],
            'applications' => [
                'total' => $this->countAll('platform_application'),
                'byPlatform' => $this->getApplicationsByPlatform(),
            ],
            'plugins' => [
                'total' => $this->countAll('platform_plugin'),
                'usage' => $this->getPluginUsage(),
            ],
            'posts' => [
                'total' => $this->countAll('blog_post'),
                'last7Days' => $this->countSince('blog_post', $lastSevenDays),
                'thisMonth' => $this->countSince('blog_post', $startOfMonth),
                'thisYear' => $this->countSince('blog_post', $startOfYear),
            ],
        ];
    }

    private function countAll(string $table): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }

    private function countSince(string $table, DateTimeImmutable $since): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE created_at >= :since',
            [
                'since' => $since,
            ],
            [
                'since' => 'datetime_immutable',
            ],
        );
    }

    /**
     * @return list<array{name: string, applicationCount: int}>
     */
    private function getApplicationsByPlatform(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT p.name AS name, COUNT(a.id) AS applicationCount
                FROM platform_platform p
                LEFT JOIN platform_application a ON a.platform_id = p.id
                GROUP BY p.id, p.name
                ORDER BY applicationCount DESC, p.name ASC
            SQL,
        );

        return array_map(
            static fn (array $row): array => [
                'name' => (string) $row['name'],
                'applicationCount' => (int) $row['applicationCount'],
            ],
            $rows,
        );
    }

    /**
     * @return list<array{name: string, usageCount: int}>
     */
    private function getPluginUsage(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT p.name AS name, COUNT(ap.id) AS usageCount
                FROM platform_plugin p
                LEFT JOIN platform_application_plugin ap ON ap.plugin_id = p.id
                GROUP BY p.id, p.name
                ORDER BY usageCount DESC, p.name ASC
            SQL,
        );

        return array_map(
            static fn (array $row): array => [
                'name' => (string) $row['name'],
                'usageCount' => (int) $row['usageCount'],
            ],
            $rows,
        );
    }
}
