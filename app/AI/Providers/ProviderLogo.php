<?php

namespace App\AI\Providers;

final class ProviderLogo
{
    /**
     * Public URL of `public/images/providers/{provider}.svg` when that file exists.
     */
    public static function url(string $provider): ?string
    {
        return self::existing("images/providers/{$provider}.svg");
    }

    /**
     * Public URL of the dark-mode variant `public/images/providers/{provider}-dark.svg` when that file exists.
     */
    public static function darkUrl(string $provider): ?string
    {
        return self::existing("images/providers/{$provider}-dark.svg");
    }

    private static function existing(string $path): ?string
    {
        return is_file(public_path($path)) ? asset($path) : null;
    }
}
