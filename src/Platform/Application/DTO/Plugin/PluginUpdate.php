<?php

declare(strict_types=1);

namespace App\Platform\Application\DTO\Plugin;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @package App\Platform
 */
class PluginUpdate extends Plugin
{
    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Length(min: 2, max: 255)]
    protected string $name = '';

    #[Assert\NotNull]
    protected string $description = '';

    #[Assert\NotNull]
    protected bool $private = false;

    #[Assert\NotNull]
    protected bool $enabled = true;
}
