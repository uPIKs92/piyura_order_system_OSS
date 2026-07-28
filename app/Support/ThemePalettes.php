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
        'claude-plus',
        'light-green',
        'zen-inspired-theme',
        'astrovista',
        'tiesen',
        'designbyte',
        'qrafthive',
        'mx-brutalist',
        'sage-green',
        'apple-liquid-glass',
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
