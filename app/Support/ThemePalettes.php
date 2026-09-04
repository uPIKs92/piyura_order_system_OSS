<?php

namespace App\Support;

class ThemePalettes
{
    /** @var list<string> */
    public const BASIC = [
        'neutral',
        'blue',
        'green',
        'orange',
        'violet',
    ];

    /** @var list<string> */
    public const PRESETS = [
        'luma-lime',
        'sera-taupe',
        'lyra-zinc',
        'vega-emerald',
        'luma-teal',
        'luma-neutral',
        'nova-mauve',
        'sera-amber',
        'luma-yellow',
        'vega-sky',
        'nova-violet',
        'luma-red',
    ];

    /** @var list<string> */
    public const ALL = [
        ...self::BASIC,
        ...self::PRESETS,
    ];

    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::ALL);
    }
}
