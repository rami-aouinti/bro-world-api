<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crm\Application\Service;

use App\Crm\Application\Service\CrmApiNormalizer;
use App\Crm\Application\Service\CrmBlogNormalizer;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;

final class CrmApiNormalizerTest extends TestCase
{
    private CrmApiNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new CrmApiNormalizer(new CrmBlogNormalizer());
    }

    public function testNormalizeTaskKeepsAssigneeConventionWithUsername(): void
    {
        $task = new Task();
        $task->setTitle('Task title');

        $user = (new User())
            ->setUsername('alice')
            ->setEmail('alice@example.com')
            ->setFirstName('Alice')
            ->setLastName('Doe');

        $task->addAssignee($user);

        $normalized = $this->normalizer->normalizeTask($task);

        self::assertSame([
            'id' => $user->getId(),
            'username' => 'alice',
            'firstName' => 'Alice',
            'lastName' => 'Doe',
            'photo' => null,
        ], $normalized['assignees'][0]);
    }

    public function testNormalizeTaskRequestProjectionKeepsBusinessStatusAsStringAndAssigneeShape(): void
    {
        $normalized = $this->normalizer->normalizeTaskRequestProjection([
            'id' => 'tr-1',
            'taskId' => 't-1',
            'title' => 'Request title',
            'status' => 'approved',
            'assignees' => [
                [
                    'id' => 'u-1',
                    'email' => 'bob@example.com',
                    'firstName' => 'Bob',
                    'lastName' => 'Smith',
                    'photo' => '/img.png',
                ],
            ],
        ]);

        self::assertSame('approved', $normalized['status']);
        self::assertSame([
            'id' => 'u-1',
            'username' => 'bob@example.com',
            'firstName' => 'Bob',
            'lastName' => 'Smith',
            'photo' => '/img.png',
        ], $normalized['assignees'][0]);
    }

    public function testNormalizeProjectAndSprintProjectionKeepStringStatus(): void
    {
        $normalizedProject = $this->normalizer->normalizeProjectProjection([
            'id' => 'p-1',
            'name' => 'Project A',
            'status' => 'archived',
        ]);

        $normalizedSprint = $this->normalizer->normalizeSprintProjection([
            'id' => 's-1',
            'name' => 'Sprint 1',
            'status' => 'in_progress',
        ]);

        self::assertSame('archived', $normalizedProject['status']);
        self::assertSame('in_progress', $normalizedSprint['status']);
    }

    public function testNormalizeTaskRequestUsesSameAssigneeConventionAsTask(): void
    {
        $request = new TaskRequest();
        $request->setTitle('Need review');
        $request->setStatus(TaskRequestStatus::APPROVED);

        $user = (new User())
            ->setUsername('charlie')
            ->setEmail('charlie@example.com')
            ->setFirstName('Charlie')
            ->setLastName('Johnson');

        $request->addAssignee($user);

        $normalized = $this->normalizer->normalizeTaskRequest($request);

        self::assertSame('approved', $normalized['status']);
        self::assertSame([
            'id' => $user->getId(),
            'username' => 'charlie',
            'firstName' => 'Charlie',
            'lastName' => 'Johnson',
            'photo' => null,
        ], $normalized['assignees'][0]);
    }
}
