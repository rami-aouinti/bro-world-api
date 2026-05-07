<?php
declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\CoverLetter;

use App\Recruit\Domain\Entity\CoverLetter;
use App\Recruit\Domain\Entity\Template;
use App\Recruit\Infrastructure\Repository\CoverLetterRepository;
use App\Recruit\Infrastructure\Repository\TemplateRepository;
use App\User\Domain\Entity\User;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class MyCoverLetterController
{
    public function __construct(
        private CoverLetterRepository $repo,
        private TemplateRepository $templates,
    ) {
    }

    #[Route(path: '/v1/recruit/private/me/cover-letters', methods: [Request::METHOD_GET])]
    public function list(User $loggedInUser): JsonResponse
    {
        $rows = $this->repo->findBy(['owner' => $loggedInUser], ['createdAt' => 'DESC']);

        return new JsonResponse(array_map($this->normalize(...), $rows));
    }

    #[Route(path: '/v1/recruit/private/me/cover-letters', methods: [Request::METHOD_POST])]
    public function create(Request $request, User $loggedInUser): JsonResponse
    {
        $p = $request->toArray();
        $entity = (new CoverLetter())
            ->setOwner($loggedInUser)
            ->setFullName($p['fullName'] ?? null)
            ->setRole($p['role'] ?? null)
            ->setPhoto($p['photo'] ?? null)
            ->setSenderDate(isset($p['senderDate']) ? new \DateTimeImmutable((string) $p['senderDate']) : null)
            ->setLocation($p['location'] ?? null)
            ->setHeader($p['header'] ?? 'Motivation Letter')
            ->setDescription1($p['description1'] ?? null)
            ->setDescription2($p['description2'] ?? null);

        $this->applyTemplate($entity, $p['templateId'] ?? null);
        $this->repo->save($entity);

        return new JsonResponse($this->normalize($entity), JsonResponse::HTTP_CREATED);
    }

    #[Route(path: '/v1/recruit/private/me/cover-letters/{id}', methods: [Request::METHOD_PATCH])]
    public function patch(string $id, Request $request, User $loggedInUser): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid id');
        }

        $entity = $this->repo->find($id);
        if (!$entity instanceof CoverLetter) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Not found');
        }

        if ($entity->getOwner()->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Forbidden');
        }

        $p = $request->toArray();
        if (array_key_exists('fullName', $p)) { $entity->setFullName($p['fullName']); }
        if (array_key_exists('role', $p)) { $entity->setRole($p['role']); }
        if (array_key_exists('photo', $p)) { $entity->setPhoto($p['photo']); }
        if (array_key_exists('senderDate', $p)) { $entity->setSenderDate($p['senderDate'] !== null ? new \DateTimeImmutable((string) $p['senderDate']) : null); }
        if (array_key_exists('location', $p)) { $entity->setLocation($p['location']); }
        if (array_key_exists('header', $p)) { $entity->setHeader($p['header']); }
        if (array_key_exists('description1', $p)) { $entity->setDescription1($p['description1']); }
        if (array_key_exists('description2', $p)) { $entity->setDescription2($p['description2']); }
        if (array_key_exists('templateId', $p)) { $this->applyTemplate($entity, $p['templateId']); }

        $this->repo->save($entity);

        return new JsonResponse($this->normalize($entity));
    }

    #[Route(path: '/v1/recruit/private/me/cover-letters/{id}', methods: [Request::METHOD_DELETE])]
    public function delete(string $id, User $loggedInUser): JsonResponse
    {
        $entity = $this->repo->find($id);
        if (!$entity instanceof CoverLetter) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Not found');
        }

        if ($entity->getOwner()->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Forbidden');
        }

        $this->repo->remove($entity);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function applyTemplate(CoverLetter $entity, mixed $id): void
    {
        if ($id === null) {
            $entity->setTemplate(null);

            return;
        }

        $template = $this->templates->find((string) $id);
        if (!$template instanceof Template || $template->getType() !== 'cover_letter') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid template');
        }

        $entity->setTemplate($template);
    }

    private function normalize(CoverLetter $entity): array
    {
        return [
            'id' => $entity->getId(),
            'template' => $entity->getTemplate() !== null ? ['id' => $entity->getTemplate()?->getId(),'name' => $entity->getTemplate()?->getName(),'type' => $entity->getTemplate()?->getType(),'version' => $entity->getTemplate()?->getVersion(),'layout' => $entity->getTemplate()?->getLayout(),'structure' => $entity->getTemplate()?->getStructure(),'sections' => $entity->getTemplate()?->getSections(),'theme' => $entity->getTemplate()?->getTheme(),'aside' => $entity->getTemplate()?->getAside(),'photo' => $entity->getTemplate()?->getPhoto(),'decor' => $entity->getTemplate()?->getDecor(),'layoutOptions' => $entity->getTemplate()?->getLayoutOptions(),'decorOptions' => $entity->getTemplate()?->getDecorOptions(),'sectionTitleStyle' => $entity->getTemplate()?->getSectionTitleStyle(),'headerType' => $entity->getTemplate()?->getHeaderType(),'fakeData' => $entity->getTemplate()?->getFakeData(),'textStyles' => $entity->getTemplate()?->getTextStyles(),'typography' => $entity->getTemplate()?->getTypography(),'sectionBar' => $entity->getTemplate()?->getSectionBar(),'items' => $entity->getTemplate()?->getItems()] : null,
            'fullName' => $entity->getFullName(),
            'role' => $entity->getRole(),
            'photo' => $entity->getPhoto(),
            'senderDate' => $entity->getSenderDate()?->format('Y-m-d'),
            'location' => $entity->getLocation(),
            'header' => $entity->getHeader(),
            'description1' => $entity->getDescription1(),
            'description2' => $entity->getDescription2(),
        ];
    }
}
