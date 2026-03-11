<?php

declare(strict_types=1);

namespace App\Page\Transport\Controller\Api\V1\Faq;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Actions;
use App\Page\Application\DTO\Faq\FaqCreate;
use App\Page\Application\DTO\Faq\FaqPatch;
use App\Page\Application\DTO\Faq\FaqUpdate;
use App\Page\Application\Resource\FaqResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: '/v1/page/faq')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Page Management')]
class FaqController extends Controller
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
        Controller::METHOD_CREATE => FaqCreate::class,
        Controller::METHOD_UPDATE => FaqUpdate::class,
        Controller::METHOD_PATCH => FaqPatch::class,
    ];

    public function __construct(FaqResource $resource)
    {
        parent::__construct($resource);
    }
}
