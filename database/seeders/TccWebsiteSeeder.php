<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\CampusEvent;
use App\Models\CmsPage;
use App\Models\HeroSlide;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class TccWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        HeroSlide::query()->delete();
        Article::query()->delete();
        CampusEvent::query()->delete();

        $admin = User::query()->updateOrCreate(
            ['email' => config('cms.admin_email')],
            [
                'name' => config('cms.admin_name'),
                'password' => Hash::make((string) config('cms.admin_password')),
                'is_admin' => true,
            ]
        );

        HeroSlide::query()->insert([
            [
                'title' => 'Tagoloan Community College',
                'subtitle' => 'Public higher education for Misamis Oriental — accessible, relevant, and community-centered since 2003.',
                'image_url' => 'https://tcc.edu.ph/images/hero-campus.jpg',
                'link_url' => null,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Affordable pathways to a degree',
                'subtitle' => 'Serving low-income residents of Tagoloan with programs that respond to local and regional needs.',
                'image_url' => 'https://tcc.edu.ph/images/hero-admissions.jpg',
                'link_url' => '/admissions',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Research, extension, and service',
                'subtitle' => 'Partnering with communities to strengthen livelihoods, governance, and sustainable development.',
                'image_url' => 'https://tcc.edu.ph/images/hero-research-extension.jpg',
                'link_url' => '/academics',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $articles = [
            [
                'slug' => 'tcc-opens-enrollment-for-first-semester-2025',
                'title' => 'TCC opens enrollment for First Semester 2025',
                'excerpt' => 'Application schedules, documentary requirements, and campus orientation dates are now available for new and returning students.',
                'body' => "<p>Tagoloan Community College announces the opening of enrollment for First Semester 2025. Prospective students are encouraged to review program offerings, prepare complete documentary requirements, and attend scheduled campus orientations.</p><p>Details on admission pathways, scholarship information, and flexible learning options are published on the Admissions page.</p>",
                'image_url' => 'https://tcc.edu.ph/images/news-enrollment.jpg',
                'featured' => true,
                'sdg_goals' => [4, 17],
                'published_at' => Carbon::now()->subDays(5),
            ],
            [
                'slug' => 'community-extension-brigada-eskwela-2025',
                'title' => 'Faculty and students join Brigada Eskwela partner schools',
                'excerpt' => 'Volunteers supported classroom repairs, learning corners, and reading camps in neighboring barangays.',
                'body' => "<p>Teams from TCC joined partner public schools for Brigada Eskwela activities, contributing manpower and learning materials.</p><p>The initiative reflects the college's commitment to service and youth development in Tagoloan and nearby communities.</p>",
                'image_url' => 'https://tcc.edu.ph/images/news-brigada-eskwela.jpg',
                'featured' => true,
                'sdg_goals' => [1, 4, 11],
                'published_at' => Carbon::now()->subDays(12),
            ],
            [
                'slug' => 'research-forum-highlights-local-development',
                'title' => 'Annual research forum highlights local development themes',
                'excerpt' => 'Faculty and student presenters shared studies on coastal resources, livelihoods, and inclusive education.',
                'body' => "<p>The annual research forum showcased studies aligned with regional development priorities, including sustainable coastal resource use and inclusive teaching practices.</p>",
                'image_url' => 'https://tcc.edu.ph/images/news-research-forum.jpg',
                'featured' => false,
                'sdg_goals' => [9, 13, 14],
                'published_at' => Carbon::now()->subDays(20),
            ],
            [
                'slug' => 'library-hours-and-digital-resources',
                'title' => 'Updated library hours and expanded digital resources',
                'excerpt' => 'Students can now access additional e-journals and learning modules through the campus learning portal.',
                'body' => "<p>The TCC library has updated its service hours and expanded licensed digital collections to support research and coursework.</p>",
                'image_url' => 'https://tcc.edu.ph/images/news-library.jpg',
                'featured' => false,
                'sdg_goals' => [4, 10],
                'published_at' => Carbon::now()->subDays(28),
            ],
        ];

        foreach ($articles as $row) {
            Article::query()->create([...$row, 'user_id' => $admin->id]);
        }

        $events = [
            [
                'slug' => 'foundation-day-program-2025',
                'title' => 'TCC Foundation Day program',
                'excerpt' => 'A campus-wide celebration honoring TCC’s mission and community partners.',
                'body' => "<p>Join students, faculty, alumni, and local leaders for a day of recognition, cultural performances, and service exhibits.</p>",
                'starts_at' => Carbon::now()->addWeeks(3)->setTime(8, 0),
                'ends_at' => Carbon::now()->addWeeks(3)->setTime(17, 0),
                'location' => 'TCC Main Campus grounds',
                'image_url' => 'https://tcc.edu.ph/images/events-foundation-day.jpg',
                'sdg_goals' => [11, 16, 17],
            ],
            [
                'slug' => 'career-guidance-fair',
                'title' => 'Career guidance fair for graduating students',
                'excerpt' => 'Employers, civil service desks, and graduate school representatives will be on site.',
                'body' => "<p>Graduating students can explore employment opportunities, licensure exam guidance, and further studies options.</p>",
                'starts_at' => Carbon::now()->addWeeks(5)->setTime(9, 0),
                'ends_at' => Carbon::now()->addWeeks(5)->setTime(15, 0),
                'location' => 'TCC Multipurpose Hall',
                'image_url' => 'https://tcc.edu.ph/images/events-career-guidance.jpg',
                'sdg_goals' => [4, 8],
            ],
            [
                'slug' => 'faculty-development-workshop',
                'title' => 'Faculty development workshop on outcomes-based education',
                'excerpt' => 'Internal training on syllabus design, assessment alignment, and flexible learning modalities.',
                'body' => "<p>An internal workshop for faculty focusing on OBE principles and student-centered instruction.</p>",
                'starts_at' => Carbon::now()->addDays(10)->setTime(13, 0),
                'ends_at' => Carbon::now()->addDays(10)->setTime(17, 0),
                'location' => 'Faculty room / hybrid',
                'image_url' => 'https://tcc.edu.ph/images/events-faculty-development.jpg',
                'sdg_goals' => [4],
            ],
        ];

        foreach ($events as $row) {
            CampusEvent::query()->create([...$row, 'user_id' => $admin->id]);
        }

        $cmsPages = [
            [
                'slug' => 'home-hero-sub',
                'title' => 'Home — hero subtitle',
                'body' => 'Tagoloan Community College upholds the premise that education is a success if and when the people live a decent and prosperous life through adherence to standards of morality, employment in enterprise and competent practice of entrepreneurial skills.',
            ],
            [
                'slug' => 'about-mission-lead',
                'title' => 'About — mission lead',
                'body' => 'Tagoloan Community College widens access to quality higher education for residents of Tagoloan and neighboring communities.',
            ],
            [
                'slug' => 'contact-intro',
                'title' => 'Contact — page intro',
                'body' => 'Reach the registrar, admissions office, or ICT help desk. Messages sent through the form are stored for staff follow-up.',
            ],
        ];

        foreach ($cmsPages as $row) {
            CmsPage::query()->updateOrCreate(
                ['slug' => $row['slug']],
                ['title' => $row['title'], 'body' => $row['body']]
            );
        }
    }
}
