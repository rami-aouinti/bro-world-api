<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Resume;
use App\Recruit\Infrastructure\Repository\ResumeRepository;
use App\User\Domain\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

readonly class GeneralResumeService
{
    public function __construct(
        private ResumeRepository $resumeRepository,
        private ResumeDocumentUploaderService $resumeDocumentUploaderService,
        private ResumePayloadService $resumePayloadService,
        private ResumeNormalizerService $resumeNormalizerService,
    ) {
    }

    /**
     * @return array{id: string, documentUrl: ?string}
     */
    public function create(Request $request, User $loggedInUser): array
    {
        $payload = $this->resumePayloadService->extractPayload($request);

        $resume = new Resume()->setOwner($loggedInUser);

        /** @var UploadedFile|null $document */
        $document = $request->files->get('document');
        if ($document instanceof UploadedFile) {
            $documentUrl = $this->resumeDocumentUploaderService->upload($request, $document, '/uploads/resumes');
            $resume->setDocumentUrl($documentUrl);
        }

        $this->resumePayloadService->hydrateResumeSections($resume, $payload);

        $this->resumeRepository->save($resume);

        return [
            'id' => $resume->getId(),
            'documentUrl' => $resume->getDocumentUrl(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMyResumes(User $loggedInUser): array
    {
        $resumes = $this->resumeRepository->findBy([
            'owner' => $loggedInUser,
        ], [
            'createdAt' => 'DESC',
        ]);

        return $this->resumeNormalizerService->normalizeCollection($resumes);
    }
}
