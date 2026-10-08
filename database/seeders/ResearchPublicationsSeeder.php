<?php

namespace Database\Seeders;

use App\Models\ResearchPublication;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ResearchPublicationsSeeder extends Seeder
{
    /** Matches `cms/src/data/academicPrograms.ts` ids */
    private const PROGRAM_IDS = [
        'bachelor-science-community-development',
        'bachelor-science-information-technology',
        'bachelor-science-business-administration',
        'bachelor-science-hospitality-management',
        'bachelor-science-engineering-technology',
        'bachelor-library-information-science',
        'bachelor-science-criminology',
        'bachelor-science-midwifery',
        'bachelor-elementary-education',
        'bachelor-secondary-education',
        'bachelor-physical-education',
        'bachelor-arts-sociology',
        'graduate-midwifery',
    ];

    public function run(): void
    {
        $faker = fake();
        $start = Carbon::create(2021, 1, 1)->startOfDay();
        $end = Carbon::now()->endOfDay();
        $tsMin = $start->timestamp;
        $tsMax = $end->timestamp;

        $sdgPool = range(1, 17);

        $randomProgramId = static function () use ($faker): string {
            return $faker->randomElement(self::PROGRAM_IDS);
        };

        $randomPublishedAt = static function () use ($tsMin, $tsMax): Carbon {
            return Carbon::createFromTimestamp(random_int($tsMin, $tsMax));
        };

        $start2019 = Carbon::create(2019, 1, 1)->startOfDay();
        $end2026 = Carbon::create(2026, 12, 31)->endOfDay();
        $ts2019 = $start2019->timestamp;
        $ts2026 = $end2026->timestamp;

        $randomPublishedAt2019to2026 = static function () use ($ts2019, $ts2026): Carbon {
            return Carbon::createFromTimestamp(random_int($ts2019, $ts2026));
        };

        $randomSdgs = static function () use ($faker, $sdgPool): array {
            return collect($sdgPool)
                ->shuffle()
                ->take($faker->numberBetween(2, 4))
                ->sort()
                ->values()
                ->all();
        };

        for ($n = 1; $n <= 25; $n++) {
            $slug = 'seed-publication-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            ResearchPublication::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => 'Publication '.$n.': '.$faker->sentence($faker->numberBetween(5, 9)),
                    'excerpt' => $faker->optional(0.85)->sentence(12),
                    'body' => '<p>'.$faker->paragraph(3).'</p>',
                    'academic_program_id' => $randomProgramId(),
                    'output_type' => ResearchPublication::OUTPUT_PUBLICATION,
                    'authors' => $faker->name().', '.$faker->name(),
                    'venue_or_journal' => $faker->randomElement([
                        'TCC Research Digest',
                        'Mindanao Education Review',
                        'Philippine Journal of Extension',
                        'ASEAN Higher Education Notes',
                        'Regional Development Quarterly',
                    ]),
                    'published_at' => $randomPublishedAt(),
                    'sdg_goals' => $randomSdgs(),
                ]
            );
        }

        for ($n = 1; $n <= 25; $n++) {
            $slug = 'seed-research-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            ResearchPublication::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => 'Research project '.$n.': '.$faker->sentence($faker->numberBetween(5, 9)),
                    'excerpt' => $faker->optional(0.85)->sentence(12),
                    'body' => '<p>'.$faker->paragraph(3).'</p>',
                    'academic_program_id' => $randomProgramId(),
                    'output_type' => ResearchPublication::OUTPUT_RESEARCH,
                    'authors' => $faker->name().($faker->boolean(40) ? ', '.$faker->name() : ''),
                    'venue_or_journal' => $faker->randomElement([
                        'TCC Field Research Series',
                        'Community Extension Monograph',
                        'Capstone & Thesis Repository',
                        'Action Research Briefs',
                        'Campus Innovation Lab Reports',
                    ]),
                    'published_at' => $randomPublishedAt(),
                    'sdg_goals' => $randomSdgs(),
                ]
            );
        }

        for ($n = 26; $n <= 75; $n++) {
            $slug = 'seed-publication-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            ResearchPublication::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => 'Publication '.$n.': '.$faker->sentence($faker->numberBetween(5, 9)),
                    'excerpt' => $faker->optional(0.85)->sentence(12),
                    'body' => '<p>'.$faker->paragraph(3).'</p>',
                    'academic_program_id' => $randomProgramId(),
                    'output_type' => ResearchPublication::OUTPUT_PUBLICATION,
                    'authors' => $faker->name().', '.$faker->name(),
                    'venue_or_journal' => $faker->randomElement([
                        'TCC Research Digest',
                        'Mindanao Education Review',
                        'Philippine Journal of Extension',
                        'ASEAN Higher Education Notes',
                        'Regional Development Quarterly',
                    ]),
                    'published_at' => $randomPublishedAt2019to2026(),
                    'sdg_goals' => $randomSdgs(),
                ]
            );
        }

        for ($n = 26; $n <= 75; $n++) {
            $slug = 'seed-research-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            ResearchPublication::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => 'Research project '.$n.': '.$faker->sentence($faker->numberBetween(5, 9)),
                    'excerpt' => $faker->optional(0.85)->sentence(12),
                    'body' => '<p>'.$faker->paragraph(3).'</p>',
                    'academic_program_id' => $randomProgramId(),
                    'output_type' => ResearchPublication::OUTPUT_RESEARCH,
                    'authors' => $faker->name().($faker->boolean(40) ? ', '.$faker->name() : ''),
                    'venue_or_journal' => $faker->randomElement([
                        'TCC Field Research Series',
                        'Community Extension Monograph',
                        'Capstone & Thesis Repository',
                        'Action Research Briefs',
                        'Campus Innovation Lab Reports',
                    ]),
                    'published_at' => $randomPublishedAt2019to2026(),
                    'sdg_goals' => $randomSdgs(),
                ]
            );
        }
    }
}
