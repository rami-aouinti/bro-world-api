<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Enum;

enum OfferStatus: string
{
    case DRAFT = 'DRAFT';
    case SENT = 'SENT';
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
    case WITHDRAWN = 'WITHDRAWN';
}
