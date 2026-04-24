<?php

declare(strict_types=1);

namespace App\Abonnement\Transport\Controller\Api\V1\News;

use App\Abonnement\Application\DTO\News\NewsCreate;
use App\Abonnement\Application\DTO\News\NewsPatch;
use App\Abonnement\Application\DTO\News\NewsUpdate;
use App\Abonnement\Application\Resource\NewsResource;
use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\ResponseHandler;
use App\General\Transport\Rest\Traits\Actions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @method NewsResource getResource()
 * @method ResponseHandler getResponseHandler()
 */
#[AsController]
#[Route(path: '/v1/admin/abonnement/news')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Abonnement News')]
class NewsController extends Controller
{
    use Actions\Admin\CountAction;
    use Actions\Admin\FindAction;
    use Actions\Admin\FindOneAction;
    use Actions\Admin\IdsAction;
    use Actions\Root\CreateAction;
    use Actions\Root\DeleteAction;
    use Actions\Root\PatchAction;
    use Actions\Root\UpdateAction;

    protected static array $dtoClasses = [
        Controller::METHOD_CREATE => NewsCreate::class,
        Controller::METHOD_UPDATE => NewsUpdate::class,
        Controller::METHOD_PATCH => NewsPatch::class,
    ];

    public function __construct(NewsResource $resource)
    {
        parent::__construct($resource);
    }
}
