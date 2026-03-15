<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateCompanyRequest
{
    #[Assert\NotBlank(groups: ['put'])]
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

    public bool $hasName = false;
    public bool $hasIndustry = false;
    public bool $hasWebsite = false;
    public bool $hasContactEmail = false;
    public bool $hasPhone = false;

    public static function fromPutArray(array $payload): self
    {
        $request = new self();
        $request->hasName = true;
        $request->name = isset($payload['name']) ? (string)$payload['name'] : null;

        $request->hasIndustry = true;
        $request->industry = isset($payload['industry']) ? (string)$payload['industry'] : null;

        $request->hasWebsite = true;
        $request->website = isset($payload['website']) ? (string)$payload['website'] : null;

        $request->hasContactEmail = true;
        $request->contactEmail = isset($payload['contactEmail']) ? (string)$payload['contactEmail'] : null;

        $request->hasPhone = true;
        $request->phone = isset($payload['phone']) ? (string)$payload['phone'] : null;

        return $request;
    }

    public static function fromPatchArray(array $payload): self
    {
        $request = new self();

        if (array_key_exists('name', $payload)) {
            $request->hasName = true;
            $request->name = $payload['name'] !== null ? (string)$payload['name'] : null;
        }

        if (array_key_exists('industry', $payload)) {
            $request->hasIndustry = true;
            $request->industry = $payload['industry'] !== null ? (string)$payload['industry'] : null;
        }

        if (array_key_exists('website', $payload)) {
            $request->hasWebsite = true;
            $request->website = $payload['website'] !== null ? (string)$payload['website'] : null;
        }

        if (array_key_exists('contactEmail', $payload)) {
            $request->hasContactEmail = true;
            $request->contactEmail = $payload['contactEmail'] !== null ? (string)$payload['contactEmail'] : null;
        }

        if (array_key_exists('phone', $payload)) {
            $request->hasPhone = true;
            $request->phone = $payload['phone'] !== null ? (string)$payload['phone'] : null;
        }

        return $request;
    }
}
