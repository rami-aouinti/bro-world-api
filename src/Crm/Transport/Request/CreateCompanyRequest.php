<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateCompanyRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\Length(max: 255)]
    public ?string $industry = null;

    #[Assert\Length(max: 255)]
    public ?string $website = null;

    #[Assert\Email]
    #[Assert\Length(max: 255)]
    public ?string $contactEmail = null;

    #[Assert\Length(max: 64)]
    public ?string $phone = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->name = isset($payload['name']) ? (string)$payload['name'] : null;
        $request->industry = isset($payload['industry']) ? (string)$payload['industry'] : null;
        $request->website = isset($payload['website']) ? (string)$payload['website'] : null;
        $request->contactEmail = isset($payload['contactEmail']) ? (string)$payload['contactEmail'] : null;
        $request->phone = isset($payload['phone']) ? (string)$payload['phone'] : null;

        return $request;
    }
}
