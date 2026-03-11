<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Badge;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Actions;
use App\Recruit\Application\DTO\Badge\BadgeCreate;
use App\Recruit\Application\DTO\Badge\BadgePatch;
use App\Recruit\Application\DTO\Badge\BadgeUpdate;
use App\Recruit\Application\Resource\BadgeResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: '/v1/recruit/{applicationSlug}/badge')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Recruit Management')]
class BadgeController extends Controller
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
        Controller::METHOD_CREATE => BadgeCreate::class,
        Controller::METHOD_UPDATE => BadgeUpdate::class,
        Controller::METHOD_PATCH => BadgePatch::class,
    ];

    public function __construct(BadgeResource $resource)
    {
        parent::__construct($resource);
    }
}
