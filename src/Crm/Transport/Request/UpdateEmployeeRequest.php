<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateEmployeeRequest
{
    #[Assert\NotBlank(groups: ['put'])]
    #[Assert\Length(max: 120)]
    public ?string $firstName = null;

    #[Assert\NotBlank(groups: ['put'])]
    #[Assert\Length(max: 120)]
    public ?string $lastName = null;

    #[Assert\Email]
    #[Assert\Length(max: 255)]
    public ?string $email = null;

    #[Assert\Length(max: 120)]
    public ?string $positionName = null;

    #[Assert\Uuid]
    public ?string $userId = null;

    public bool $hasFirstName = false;
    public bool $hasLastName = false;
    public bool $hasEmail = false;
    public bool $hasPositionName = false;
    public bool $hasUserId = false;

    public static function fromPutArray(array $payload): self
    {
        $request = new self();

        $request->hasFirstName = true;
        $request->firstName = isset($payload['firstName']) ? (string)$payload['firstName'] : null;

        $request->hasLastName = true;
        $request->lastName = isset($payload['lastName']) ? (string)$payload['lastName'] : null;

        $request->hasEmail = true;
        $request->email = isset($payload['email']) ? (string)$payload['email'] : null;

        $request->hasPositionName = true;
        $request->positionName = isset($payload['positionName']) ? (string)$payload['positionName'] : null;

        $request->hasUserId = true;
        $request->userId = isset($payload['userId']) ? (string)$payload['userId'] : null;

        return $request;
    }

    public static function fromPatchArray(array $payload): self
    {
        $request = new self();

        if (array_key_exists('firstName', $payload)) {
            $request->hasFirstName = true;
            $request->firstName = $payload['firstName'] !== null ? (string)$payload['firstName'] : null;
        }

        if (array_key_exists('lastName', $payload)) {
            $request->hasLastName = true;
            $request->lastName = $payload['lastName'] !== null ? (string)$payload['lastName'] : null;
        }

        if (array_key_exists('email', $payload)) {
            $request->hasEmail = true;
            $request->email = $payload['email'] !== null ? (string)$payload['email'] : null;
        }

        if (array_key_exists('positionName', $payload)) {
            $request->hasPositionName = true;
            $request->positionName = $payload['positionName'] !== null ? (string)$payload['positionName'] : null;
        }

        if (array_key_exists('userId', $payload)) {
            $request->hasUserId = true;
            $request->userId = $payload['userId'] !== null ? (string)$payload['userId'] : null;
        }

        return $request;
    }
}
