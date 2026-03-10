<?php

declare(strict_types=1);

namespace App\Page\Application\Resource;

use App\General\Application\Rest\RestResource;
use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Application\Service\CacheInvalidationService;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Page\Application\DTO\About\About as AboutDto;
use App\Page\Domain\Entity\About;
use App\Page\Domain\Entity\PageLanguage;
use App\Page\Domain\Repository\Interfaces\AboutRepositoryInterface as Repository;
use App\Page\Domain\Repository\Interfaces\PageLanguageRepositoryInterface;
use RuntimeException;

class AboutResource extends RestResource
{
    public function __construct(
        Repository $repository,
        private readonly PageLanguageRepositoryInterface $pageLanguageRepository,
        private readonly CacheInvalidationService $cacheInvalidationService,
    )
    {
        parent::__construct($repository);
    }

    public function beforeCreate(RestDtoInterface $dto, EntityInterface $entity): void { $this->applyLanguage($dto, $entity); }
    public function beforeUpdate(string &$id, RestDtoInterface $dto, EntityInterface $entity): void { $this->applyLanguage($dto, $entity); }
    public function beforePatch(string &$id, RestDtoInterface $dto, EntityInterface $entity): void { $this->applyLanguage($dto, $entity); }
    public function afterCreate(RestDtoInterface $dto, EntityInterface $entity): void { $this->cacheInvalidationService->invalidatePublicPageCaches(); }
    public function afterUpdate(string &$id, RestDtoInterface $dto, EntityInterface $entity): void { $this->cacheInvalidationService->invalidatePublicPageCaches(); }
    public function afterPatch(string &$id, RestDtoInterface $dto, EntityInterface $entity): void { $this->cacheInvalidationService->invalidatePublicPageCaches(); }
    public function afterDelete(string &$id, EntityInterface $entity): void { $this->cacheInvalidationService->invalidatePublicPageCaches(); }

    private function applyLanguage(RestDtoInterface $dto, EntityInterface $entity): void
    {
        if (!$dto instanceof AboutDto || !$entity instanceof About) {
            return;
        }

        /** @var PageLanguage|null $language */
        $language = $this->pageLanguageRepository->find($dto->getLanguageId());

        if ($language === null) {
            throw new RuntimeException('Invalid page language id provided.');
        }

        $entity->setLanguage($language);
    }
}
