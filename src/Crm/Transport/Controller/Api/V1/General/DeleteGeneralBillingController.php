<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Billing;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class DeleteGeneralBillingController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[OA\Delete(summary: 'General - Delete Billing', responses: [new OA\Response(response: 204, description: 'Billing supprimée')])]
    public function __invoke(Billing $billing): JsonResponse
    {
        $this->entityManager->remove($billing);
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
