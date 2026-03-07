<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Company;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Actions;
use App\Recruit\Application\DTO\Company\CompanyCreate;
use App\Recruit\Application\DTO\Company\CompanyPatch;
use App\Recruit\Application\DTO\Company\CompanyUpdate;
use App\Recruit\Application\Resource\CompanyResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: '/v1/recruit/company')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Recruit Company Management')]
class CompanyController extends Controller
{
    use Actions\Root\CountAction;
    use Actions\Root\FindAction;
    use Actions\Root\FindOneAction;
    use Actions\Root\IdsAction;
    use Actions\Root\CreateAction;
    use Actions\Root\DeleteAction;
    use Actions\Root\PatchAction;
    use Actions\Root\UpdateAction;

    protected static array $dtoClasses = [
        Controller::METHOD_CREATE => CompanyCreate::class,
        Controller::METHOD_UPDATE => CompanyUpdate::class,
        Controller::METHOD_PATCH => CompanyPatch::class,
    ];

    public function __construct(CompanyResource $resource)
    {
        parent::__construct($resource);
    }
}
