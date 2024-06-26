<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * Possible link button styles
 * @package App\Enums
 */
final class LinkButtonStyle extends Enum
{
    public const PRIMARY   = 'primary';
    public const SECONDARY = 'secondary';
    public const SUCCESS   = 'success';
    public const DANGER    = 'danger';
    public const WARNING   = 'warning';
    public const INFO      = 'info';
    public const LIGHT     = 'light';
    public const DARK      = 'dark';
    public const LINK      = 'link';
}
