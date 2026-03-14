<?php

declare(strict_types=1);

namespace App\Quiz\Application\Service;

use App\Quiz\Domain\Entity\Quiz;
use App\User\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function in_array;

final class QuizEditorAccessService
{
    public function assertCanEdit(Quiz $quiz, User $actor): void
    {
        $roles = $actor->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_ROOT', $roles, true)) {
            return;
        }

        if ($quiz->getOwner()->getId() !== $actor->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only quiz owner or admin can edit quiz content.');
        }
    }
}
