<?php

declare(strict_types=1);

/**
 * Benchmark protocol for EventListService query path when Elasticsearch returns > 1000 hits
 * and service falls back to DB filters (count + find).
 */

$databasePath = __DIR__ . '/../../var/benchmark/event-list-service.sqlite';
@mkdir(dirname($databasePath), 0777, true);

$pdo = new PDO('sqlite:' . $databasePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec('PRAGMA synchronous = NORMAL');
$pdo->exec('PRAGMA temp_store = MEMORY');
$pdo->exec('PRAGMA cache_size = -200000');

$pdo->exec('DROP TABLE IF EXISTS event');
$pdo->exec('DROP TABLE IF EXISTS calendar');
$pdo->exec('DROP TABLE IF EXISTS application');

$pdo->exec('CREATE TABLE application (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE
)');

$pdo->exec('CREATE TABLE calendar (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id INTEGER NOT NULL,
    user_id TEXT NOT NULL,
    FOREIGN KEY(application_id) REFERENCES application(id)
)');

$pdo->exec('CREATE TABLE event (
    id TEXT PRIMARY KEY,
    calendar_id INTEGER NOT NULL,
    user_id TEXT,
    title TEXT NOT NULL,
    description TEXT,
    location TEXT,
    visibility TEXT NOT NULL,
    is_cancelled INTEGER NOT NULL DEFAULT 0,
    start_at TEXT NOT NULL,
    FOREIGN KEY(calendar_id) REFERENCES calendar(id)
)');

$pdo->exec('CREATE INDEX idx_application_slug ON application(slug)');
$pdo->exec('CREATE INDEX idx_calendar_app ON calendar(application_id)');
$pdo->exec('CREATE INDEX idx_event_calendar ON event(calendar_id)');
$pdo->exec('CREATE INDEX idx_event_start_at ON event(start_at)');
$pdo->exec('CREATE INDEX idx_event_visibility_cancelled ON event(visibility, is_cancelled)');

$pdo->exec("INSERT INTO application(slug) VALUES ('bro-world')");
$appId = (int)$pdo->lastInsertId();

$insertCalendar = $pdo->prepare('INSERT INTO calendar(application_id, user_id) VALUES (:app, :user)');
$calendarCount = 120;
for ($i = 1; $i <= $calendarCount; ++$i) {
    $insertCalendar->execute([
        ':app' => $appId,
        ':user' => sprintf('user-%04d', $i),
    ]);
}

$calendarIds = $pdo->query('SELECT id FROM calendar')->fetchAll(PDO::FETCH_COLUMN);

$insertEvent = $pdo->prepare(
    'INSERT INTO event(id, calendar_id, user_id, title, description, location, visibility, is_cancelled, start_at)
     VALUES (:id, :calendar_id, :user_id, :title, :description, :location, :visibility, :is_cancelled, :start_at)'
);

$totalEvents = 140000;
$heavyHitRatio = 0.22; // ensures >1000 hits for filter token.
$locations = ['Paris', 'Lyon', 'Marseille', 'Bordeaux', 'Lille'];
$start = strtotime('2026-01-01 08:00:00');

$pdo->beginTransaction();
for ($i = 1; $i <= $totalEvents; ++$i) {
    $isHeavy = mt_rand() / mt_getrandmax() < $heavyHitRatio;
    $token = $isHeavy ? 'conference' : 'meeting';

    $insertEvent->execute([
        ':id' => sprintf('evt-%06d', $i),
        ':calendar_id' => (int)$calendarIds[array_rand($calendarIds)],
        ':user_id' => sprintf('user-%04d', random_int(1, $calendarCount)),
        ':title' => $token . ' title ' . ($i % 500),
        ':description' => ($isHeavy ? 'quarterly conference update ' : 'routine sync ') . ($i % 1000),
        ':location' => ($isHeavy ? 'conference center ' : 'office ') . $locations[$i % count($locations)],
        ':visibility' => 'public',
        ':is_cancelled' => ($i % 40 === 0) ? 1 : 0,
        ':start_at' => date('c', $start + ($i * 3600)),
    ]);

    if ($i % 5000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit();

$filters = [
    ':slug' => 'bro-world',
    ':visibility' => 'public',
    ':title' => '%conference%',
    ':description' => '%conference%',
    ':location' => '%conference%',
];

$limit = 50;

$countSql = <<<SQL
SELECT COUNT(DISTINCT event.id)
FROM event
INNER JOIN calendar ON event.calendar_id = calendar.id
INNER JOIN application ON calendar.application_id = application.id
WHERE application.slug = :slug
  AND event.visibility = :visibility
  AND event.is_cancelled = 0
  AND (
    LOWER(event.title) LIKE LOWER(:title)
    OR LOWER(event.description) LIKE LOWER(:description)
    OR LOWER(event.location) LIKE LOWER(:location)
  )
SQL;

$findSql = <<<SQL
SELECT DISTINCT event.id, event.title, event.start_at
FROM event
INNER JOIN calendar ON event.calendar_id = calendar.id
INNER JOIN application ON calendar.application_id = application.id
WHERE application.slug = :slug
  AND event.visibility = :visibility
  AND event.is_cancelled = 0
  AND (
    LOWER(event.title) LIKE LOWER(:title)
    OR LOWER(event.description) LIKE LOWER(:description)
    OR LOWER(event.location) LIKE LOWER(:location)
  )
ORDER BY event.start_at ASC
LIMIT :limit OFFSET :offset
SQL;

$countStmt = $pdo->prepare($countSql);
$findStmt = $pdo->prepare($findSql);

$countStmt->execute($filters);
$totalHits = (int)$countStmt->fetchColumn();

$iterations = 90;
$latencyAllMs = [];
$countMs = [];
$findMs = [];

for ($i = 0; $i < $iterations; ++$i) {
    $offset = ($i % 25) * 50;

    $countStart = microtime(true);
    $countStmt->execute($filters);
    $countStmt->fetchColumn();
    $countEnd = microtime(true);

    $findStart = microtime(true);
    $findStmt->bindValue(':slug', $filters[':slug']);
    $findStmt->bindValue(':visibility', $filters[':visibility']);
    $findStmt->bindValue(':title', $filters[':title']);
    $findStmt->bindValue(':description', $filters[':description']);
    $findStmt->bindValue(':location', $filters[':location']);
    $findStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $findStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $findStmt->execute();
    $findStmt->fetchAll();
    $findEnd = microtime(true);

    $countDuration = ($countEnd - $countStart) * 1000;
    $findDuration = ($findEnd - $findStart) * 1000;

    $countMs[] = $countDuration;
    $findMs[] = $findDuration;
    $latencyAllMs[] = $countDuration + $findDuration;
}

$percentile = static function (array $values, float $percent): float {
    sort($values);
    $index = (int)ceil(($percent / 100) * count($values)) - 1;
    return $values[max(0, min($index, count($values) - 1))];
};

$explainCount = $pdo->prepare('EXPLAIN QUERY PLAN ' . $countSql);
$explainCount->execute($filters);
$countPlan = $explainCount->fetchAll();

$explainFind = $pdo->prepare('EXPLAIN QUERY PLAN ' . $findSql);
$explainFind->bindValue(':slug', $filters[':slug']);
$explainFind->bindValue(':visibility', $filters[':visibility']);
$explainFind->bindValue(':title', $filters[':title']);
$explainFind->bindValue(':description', $filters[':description']);
$explainFind->bindValue(':location', $filters[':location']);
$explainFind->bindValue(':limit', $limit, PDO::PARAM_INT);
$explainFind->bindValue(':offset', 0, PDO::PARAM_INT);
$explainFind->execute();
$findPlan = $explainFind->fetchAll();

$report = [
    'dataset' => [
        'totalEvents' => $totalEvents,
        'calendarCount' => $calendarCount,
        'totalHits' => $totalHits,
    ],
    'latencyMs' => [
        'p50' => round($percentile($latencyAllMs, 50), 2),
        'p95' => round($percentile($latencyAllMs, 95), 2),
    ],
    'dbMs' => [
        'countP50' => round($percentile($countMs, 50), 2),
        'countP95' => round($percentile($countMs, 95), 2),
        'findP50' => round($percentile($findMs, 50), 2),
        'findP95' => round($percentile($findMs, 95), 2),
    ],
    'costMs' => [
        'countMean' => round(array_sum($countMs) / count($countMs), 2),
        'findMean' => round(array_sum($findMs) / count($findMs), 2),
    ],
    'queryPlan' => [
        'count' => $countPlan,
        'find' => $findPlan,
    ],
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
