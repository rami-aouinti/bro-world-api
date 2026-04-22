<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Billing;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final class GetGeneralBillingController
{
    use GeneralCrudApiTrait;

    #[OA\Get(summary: 'General - Get Billing', responses: [new OA\Response(response: 200, description: 'Détail billing', content: new OA\JsonContent(example: ['id' => 'uuid', 'label' => 'Abonnement avril', 'amount' => 99.9]))])]
    public function __invoke(Billing $billing): JsonResponse
    {
        return new JsonResponse($this->serializeBilling($billing));
    }
}
