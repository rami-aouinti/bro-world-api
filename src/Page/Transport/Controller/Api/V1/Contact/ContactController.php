<?php

declare(strict_types=1);

namespace App\Page\Transport\Controller\Api\V1\Contact;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Actions;
use App\Page\Application\DTO\Contact\ContactCreate;
use App\Page\Application\DTO\Contact\ContactPatch;
use App\Page\Application\DTO\Contact\ContactUpdate;
use App\Page\Application\Resource\ContactResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: '/v1/page/contact')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Page Contact Management')]
class ContactController extends Controller
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
        Controller::METHOD_CREATE => ContactCreate::class,
        Controller::METHOD_UPDATE => ContactUpdate::class,
        Controller::METHOD_PATCH => ContactPatch::class,
    ];

    public function __construct(ContactResource $resource)
    {
        parent::__construct($resource);
    }
}
