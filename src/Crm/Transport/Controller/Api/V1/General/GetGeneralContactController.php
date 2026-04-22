<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\ContactReadService;
use App\Crm\Domain\Entity\Contact;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class GetGeneralContactController
{
    public function __construct(private ContactReadService $contactReadService)
    {
    }

    /** @throws InvalidArgumentException */
    public function __invoke(Contact $contact): JsonResponse
    {
        $payload = $this->contactReadService->getGlobalDetail($contact->getId());
        if ($payload === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Contact not found.');
        }

        return new JsonResponse($payload);
    }
}
