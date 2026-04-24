<?php

declare(strict_types=1);

namespace App\Abonnement\Application\Resource;

use App\Abonnement\Domain\Entity\News as Entity;
use App\Abonnement\Domain\Repository\Interfaces\NewsRepositoryInterface as Repository;
use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Application\Rest\RestResource;
use App\General\Domain\Entity\Interfaces\EntityInterface;

/**
 * @method Entity create(RestDtoInterface $dto, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 * @method Entity update(string $id, RestDtoInterface $dto, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 * @method Entity patch(string $id, RestDtoInterface $dto, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 * @method Entity delete(string $id, ?bool $flush = null, ?string $entityManagerName = null)
 * @method Entity save(EntityInterface $entity, ?bool $flush = null, ?bool $skipValidation = null, ?string $entityManagerName = null)
 */
class NewsResource extends RestResource
{
    public function __construct(
        Repository $repository,
    ) {
        parent::__construct($repository);
    }
}
