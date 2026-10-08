<?php

namespace App\Support;

final class CmsModule
{
    public const POSTS = 'posts';

    public const RESEARCH = 'research';

    public const PROGRAMS = 'programs';

    public const ASSISTANT = 'assistant';

    /** @var list<string> */
    public const ALL = [
        self::POSTS,
        self::RESEARCH,
        self::PROGRAMS,
        self::ASSISTANT,
    ];

    /**
     * @param  list<string>|null  $modules
     * @return list<string>
     */
    public static function normalize(?array $modules): array
    {
        if ($modules === null) {
            return self::ALL;
        }

        $valid = array_values(array_unique(array_filter(
            $modules,
            static fn ($module) => is_string($module) && in_array($module, self::ALL, true),
        )));

        return $valid === [] ? self::ALL : $valid;
    }

    /**
     * @return list<string>
     */
    public static function validateRequestModules(mixed $raw): array
    {
        if (! is_array($raw)) {
            abort(422, 'Module permissions must be an array.');
        }

        $normalized = self::normalize($raw);

        if ($normalized === []) {
            abort(422, 'Select at least one CMS module.');
        }

        return $normalized;
    }
}
