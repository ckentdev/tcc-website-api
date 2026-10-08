<?php

namespace App\Support;

/** Landing `/programs/{slug}` keys and CMS college ids that belong to the same offering. */
final class ProgramOfferingMap
{
    /** @var array<string, string> offering slug → academic_program_id */
    private const SLUG_TO_ACADEMIC = [
        'bsba' => 'bachelor-science-business-administration',
        'bshm' => 'bachelor-science-hospitality-management',
        'bscrim' => 'bachelor-science-criminology',
        'bsit' => 'bachelor-science-information-technology',
        'blis' => 'bachelor-library-information-science',
        'bset' => 'bachelor-science-engineering-technology',
        'beed' => 'bachelor-elementary-education',
        'bped' => 'bachelor-physical-education',
        'bsed' => 'bachelor-secondary-education',
        'basoc' => 'bachelor-arts-sociology',
        'bscd' => 'bachelor-science-community-development',
        'midwifery' => 'bachelor-science-midwifery',
    ];

    /** @return list<string> */
    public static function offeringSlugs(): array
    {
        return array_keys(self::SLUG_TO_ACADEMIC);
    }

    public static function isKnown(string $program): bool
    {
        return self::offeringSlug($program) !== null;
    }

    /** Canonical landing slug (`bsit`) for a CMS or handbook key. */
    public static function offeringSlug(string $program): ?string
    {
        $program = trim($program);
        if ($program === '') {
            return null;
        }
        if (isset(self::SLUG_TO_ACADEMIC[$program])) {
            return $program;
        }
        $academicToSlug = array_flip(self::SLUG_TO_ACADEMIC);

        return $academicToSlug[$program] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function filterKeys(string $program): array
    {
        $program = trim($program);
        if ($program === '') {
            return [];
        }

        $keys = [$program];
        if (isset(self::SLUG_TO_ACADEMIC[$program])) {
            $keys[] = self::SLUG_TO_ACADEMIC[$program];
        }

        $academicToSlug = array_flip(self::SLUG_TO_ACADEMIC);
        if (isset($academicToSlug[$program])) {
            $keys[] = $academicToSlug[$program];
        }

        return array_values(array_unique($keys));
    }
}
