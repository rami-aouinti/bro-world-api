<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Tag;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Actions;
use App\Recruit\Application\DTO\Tag\TagCreate;
use App\Recruit\Application\DTO\Tag\TagPatch;
use App\Recruit\Application\DTO\Tag\TagUpdate;
use App\Recruit\Application\Resource\TagResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: '/v1/recruit/tag')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Recruit Tag Management')]
class TagController extends Controller
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
        Controller::METHOD_CREATE => TagCreate::class,
        Controller::METHOD_UPDATE => TagUpdate::class,
        Controller::METHOD_PATCH => TagPatch::class,
    ];

    public function __construct(TagResource $resource)
    {
        parent::__construct($resource);
    }
}
