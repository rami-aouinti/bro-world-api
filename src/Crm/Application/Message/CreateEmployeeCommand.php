<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class CreateEmployeeCommand implements MessageHighInterface
{
    public function __construct(
        public string $id,
        public string $crmId,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $positionName,
        public ?string $roleName,
        public ?string $userId,
        public string $applicationSlug,
    ) {
    }
}
