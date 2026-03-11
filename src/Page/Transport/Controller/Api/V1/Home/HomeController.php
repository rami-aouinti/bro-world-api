<?php

declare(strict_types=1);

namespace App\Page\Transport\Controller\Api\V1\Home;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Actions;
use App\Page\Application\DTO\Home\HomeCreate;
use App\Page\Application\DTO\Home\HomePatch;
use App\Page\Application\DTO\Home\HomeUpdate;
use App\Page\Application\Resource\HomeResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: '/v1/page/home')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Page Management')]
class HomeController extends Controller
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
        Controller::METHOD_CREATE => HomeCreate::class,
        Controller::METHOD_UPDATE => HomeUpdate::class,
        Controller::METHOD_PATCH => HomePatch::class,
    ];

    public function __construct(HomeResource $resource)
    {
        parent::__construct($resource);
    }
}
