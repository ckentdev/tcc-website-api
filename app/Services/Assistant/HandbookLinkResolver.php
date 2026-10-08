<?php

namespace App\Services\Assistant;

class HandbookLinkResolver
{
    /** @var array<string, string> */
    private array $byTitle;

    public function __construct()
    {
        $this->byTitle = $this->buildTitleIndex();
    }

    public function resolve(?string $sectionTitle): ?string
    {
        if ($sectionTitle === null || trim($sectionTitle) === '') {
            return null;
        }

        $key = $this->normalize($sectionTitle);

        return $this->byTitle[$key] ?? $this->fuzzyMatch($key);
    }

    /**
     * @return array<string, string>
     */
    private function buildTitleIndex(): array
    {
        $map = [];
        $titles = [
            'Brief History' => 'about-history',
            'Vision, Mission, Goal' => 'about-vision-mission-goals',
            'Tagoloan Community College Seal' => 'about-seal',
            'Board of Trustees' => 'about-board',
            'Program Offerings' => 'about-program-offerings',
            'Admission' => 'admissions-admission',
            'Registration' => 'admissions-registration',
            'Registration Flow' => 'admissions-registration-flow',
            'Temporary and Late Registration' => 'admissions-temporary-late-registration',
            'Changing, Adding, Dropping of Subjects' => 'admissions-changing-adding-dropping',
            'Free Higher Education Program' => 'admissions-free-higher-education',
            'Benefits of Free Higher Education Program' => 'admissions-free-higher-education-benefits',
            'Exceptions to Free Higher Education Program' => 'admissions-free-higher-education-exceptions',
            'Classification of Student' => 'admissions-classification',
            'Shifting and Withdrawal of Programs' => 'admissions-shifting-withdrawal',
            'Student Clearance' => 'admissions-student-clearance',
            'Honorable Dismissal' => 'admissions-honorable-dismissal',
            'Student Grade' => 'academics-student-grade',
            'General Retention Policy for Non-Board Courses' => 'academics-retention-non-board',
            'Retention Policy for Board Courses' => 'academics-retention-board',
            'Study Load' => 'academics-study-load',
            'Tardiness and Absences' => 'academics-tardiness-absences',
            'Examinations' => 'academics-examinations',
            'Grading System' => 'academics-grading-system',
            'Quality Point Average (QPA)' => 'academics-qpa',
            'Other Descriptive Equivalents' => 'academics-descriptive-equivalents',
            'Releasing of Grades' => 'academics-releasing-grades',
            'Requirements for Graduation' => 'academics-graduation-requirements',
            'Academic Honors' => 'academics-academic-honors',
            'Leadership Awards' => 'academics-leadership-awards',
            'Guidelines for Educational Tours' => 'academics-educational-tours',
        ];

        $byId = config('handbook_links.by_document_id', []);

        foreach ($titles as $title => $documentId) {
            $path = $byId[$documentId] ?? null;
            if ($path) {
                $map[$this->normalize($title)] = $path;
            }
        }

        return $map;
    }

    private function fuzzyMatch(string $normalizedTitle): ?string
    {
        foreach ($this->byTitle as $title => $path) {
            if (str_contains($normalizedTitle, $title) || str_contains($title, $normalizedTitle)) {
                return $path;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
