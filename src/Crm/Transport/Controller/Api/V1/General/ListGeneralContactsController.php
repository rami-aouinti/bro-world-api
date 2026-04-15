<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\ContactReadService;
use App\Role\Domain\Enum\Role;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class ListGeneralContactsController
{
    public function __construct(private ContactReadService $contactReadService)
    {
    }

    /** @throws JsonException
     * @throws InvalidArgumentException
     */
    #[Route('/v1/crm/general/contacts', methods: [Request::METHOD_GET])]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->contactReadService->listGlobal($request));
    }
}
