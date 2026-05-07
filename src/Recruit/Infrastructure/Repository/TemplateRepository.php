<?php
declare(strict_types=1);
namespace App\Recruit\Infrastructure\Repository;
use App\General\Infrastructure\Repository\BaseRepository;
use App\Recruit\Domain\Entity\Template as Entity;
use Doctrine\Persistence\ManagerRegistry;
class TemplateRepository extends BaseRepository { protected static string $entityName = Entity::class; protected static array $searchColumns=['id','name','type']; public function __construct(protected ManagerRegistry $managerRegistry){} }
