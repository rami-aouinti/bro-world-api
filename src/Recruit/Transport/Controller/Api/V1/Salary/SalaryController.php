<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Salary;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Actions;
use App\Recruit\Application\DTO\Salary\SalaryCreate;
use App\Recruit\Application\DTO\Salary\SalaryPatch;
use App\Recruit\Application\DTO\Salary\SalaryUpdate;
use App\Recruit\Application\Resource\SalaryResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: '/v1/recruit/salary')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Recruit Salary Management')]
class SalaryController extends Controller
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
        Controller::METHOD_CREATE => SalaryCreate::class,
        Controller::METHOD_UPDATE => SalaryUpdate::class,
        Controller::METHOD_PATCH => SalaryPatch::class,
    ];

    public function __construct(SalaryResource $resource)
    {
        parent::__construct($resource);
    }
}
