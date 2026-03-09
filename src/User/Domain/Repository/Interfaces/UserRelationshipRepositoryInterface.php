<?php

declare(strict_types=1);

namespace App\User\Domain\Repository\Interfaces;

use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserRelationship;

/**
 * @package App\User
 */
interface UserRelationshipRepositoryInterface
{
    public function findRelationBetweenUsers(User $firstUser, User $secondUser): ?UserRelationship;

    /**
     * @return array<int, UserRelationship>
     */
    public function findIncomingRequests(User $user): array;

    /**
     * @return array<int, UserRelationship>
     */
    public function findOutgoingRequests(User $user): array;


    /**
     * @return array<int, UserRelationship>
     */
    public function findAcceptedRelationships(User $user): array;

    public function hasActiveBlock(User $firstUser, User $secondUser): bool;
}
