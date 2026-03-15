<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateEmployeeRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public ?string $firstName = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public ?string $lastName = null;

    #[Assert\Email]
    #[Assert\Length(max: 255)]
    public ?string $email = null;

    #[Assert\Length(max: 120)]
    public ?string $positionName = null;

    #[Assert\Length(max: 120)]
    public ?string $roleName = null;

    #[Assert\Uuid]
    public ?string $userId = null;

    public static function fromArray(array $payload): self
    {
        $r = new self();
        $r->firstName = isset($payload['firstName']) ? (string)$payload['firstName'] : null;
        $r->lastName = isset($payload['lastName']) ? (string)$payload['lastName'] : null;
        $r->email = isset($payload['email']) ? (string)$payload['email'] : null;
        $r->positionName = isset($payload['positionName']) ? (string)$payload['positionName'] : null;
        $r->roleName = isset($payload['roleName']) ? (string)$payload['roleName'] : null;
        $r->userId = isset($payload['userId']) ? (string)$payload['userId'] : null;

        return $r;
    }
}
