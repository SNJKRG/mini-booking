<?php

declare(strict_types=1);

namespace App\Enums;

enum SlotStatus: string
{
    case Available = 'available';
    case Booked = 'booked';
    case Past = 'past';
}
