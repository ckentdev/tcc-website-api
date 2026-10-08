<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

/**
 * CMS pages for every internal landing nav target (main nav submenus + top-level content pages).
 * Slug convention: path without leading slash, "/" replaced by "-" (e.g. /about/philosophy → about-philosophy).
 */
class LandingCmsPagesSeeder extends Seeder
{
    public function run(): void
    {
        $defaultBody = '';

        $pages = [
            ['about-intro', 'About — introduction (Who we are)'],
            ['about-philosophy', 'Philosophy of education'],
            ['about-history', 'History & milestones'],
            ['about-seal-colors', 'Official seal & colors'],
            ['about-hymn', 'College hymn'],
            ['about-board', 'Board of Trustees'],
            ['about-president', 'College President'],
            ['about-management', 'Management officials'],
            ['about-oup', 'Office of the College President'],
            ['about-academic-officials', 'Academic leadership'],
            ['about-admin-cluster', 'Administrative offices'],
            ['about-finance-cluster', 'Finance office'],
            ['about-reqalia', 'Research, extension, quality assurance & partnerships'],
            ['about-student-life', 'Student affairs & services'],
            ['about-mission', 'Vision, mission & quality policy'],
            ['about-citizens-charter', "Citizen's charter"],
            ['about-organizational', 'Organization & structure'],
            ['admissions-intro', 'Admissions — page introduction'],
            ['admissions-programs', 'Programs we offer'],
            ['admissions-requirements', 'Requirements & how to apply'],
            ['admissions-scholarship', 'Scholarships & financial aid'],
            ['admissions-handbook', 'Student handbook'],
            ['admissions-privacy', 'Applicant data privacy'],
            ['academics-intro', 'Academic programs — introduction'],
            ['academics-bachelor-arts-sociology', 'College of Arts and Social Sciences'],
            ['academics-bachelor-science-business-administration', 'College of Business Administration'],
            ['academics-bachelor-science-community-development', 'College of Community Development'],
            ['academics-bachelor-science-criminology', 'College of Criminal Justice'],
            ['academics-bachelor-elementary-education', 'College of Elementary Education'],
            ['academics-bachelor-science-engineering-technology', 'College of Engineering Technology'],
            ['academics-bachelor-science-hospitality-management', 'College of Hospitality Management'],
            ['academics-bachelor-science-information-technology', 'College of Information Technology'],
            ['academics-bachelor-library-information-science', 'College of Library and Information Science'],
            ['academics-bachelor-science-midwifery', 'College of Midwifery'],
            ['academics-bachelor-physical-education', 'College of Physical Education and Sports'],
            ['academics-bachelor-secondary-education', 'College of Secondary Education'],
            ['academics-graduate-midwifery', 'Graduate Program in Midwifery'],
            ['opportunities', 'Careers & opportunities'],
            ['research-publication', 'Research & Publication — hub introduction'],
            ['scholarship', 'Scholarships (Green / campus)'],
        ];

        foreach ($pages as [$slug, $title]) {
            CmsPage::query()->firstOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'body' => $defaultBody]
            );
        }
    }
}
