<?php

declare(strict_types=1);

namespace App\Page\Transport\Controller\Api\V1\About;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Actions;
use App\Page\Application\DTO\About\AboutCreate;
use App\Page\Application\DTO\About\AboutPatch;
use App\Page\Application\DTO\About\AboutUpdate;
use App\Page\Application\Resource\AboutResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: '/v1/page/about')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Page Management')]
class AboutController extends Controller
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
        Controller::METHOD_CREATE => AboutCreate::class,
        Controller::METHOD_UPDATE => AboutUpdate::class,
        Controller::METHOD_PATCH => AboutPatch::class,
    ];

    public function __construct(AboutResource $resource)
    {
        parent::__construct($resource);
    }
}
