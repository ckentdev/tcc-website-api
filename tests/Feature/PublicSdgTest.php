<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\CampusEvent;
use App\Models\ResearchPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSdgTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_sdg_page_returns_tagged_news_events_research_and_publications(): void
    {
        $author = User::factory()->create();

        Article::query()->create([
            'user_id' => $author->id,
            'slug' => 'quality-education-story',
            'title' => 'Quality education story',
            'sdg_goals' => [4, 17],
            'published_at' => now()->subDay(),
        ]);
        Article::query()->create([
            'user_id' => $author->id,
            'slug' => 'other-news',
            'title' => 'Other news',
            'sdg_goals' => [1],
            'published_at' => now()->subDay(),
        ]);
        CampusEvent::query()->create([
            'user_id' => $author->id,
            'slug' => 'education-fair',
            'title' => 'Education fair',
            'starts_at' => now()->addWeek(),
            'sdg_goals' => [4],
        ]);
        CampusEvent::query()->create([
            'user_id' => $author->id,
            'slug' => 'climate-walk',
            'title' => 'Climate walk',
            'starts_at' => now()->addDays(3),
            'sdg_goals' => [13],
        ]);
        ResearchPublication::query()->create([
            'user_id' => $author->id,
            'slug' => 'teaching-study',
            'title' => 'Teaching study',
            'academic_program_id' => 'bachelor-science-information-technology',
            'output_type' => ResearchPublication::OUTPUT_RESEARCH,
            'sdg_goals' => [4],
            'published_at' => now()->subMonth(),
        ]);
        ResearchPublication::query()->create([
            'user_id' => $author->id,
            'slug' => 'education-paper',
            'title' => 'Education paper',
            'academic_program_id' => 'bachelor-science-information-technology',
            'output_type' => ResearchPublication::OUTPUT_PUBLICATION,
            'sdg_goals' => [4, 10],
            'published_at' => now()->subWeek(),
        ]);

        $response = $this->getJson('/api/v1/sdgs/4');

        $response->assertOk()
            ->assertJsonPath('goal', 4)
            ->assertJsonPath('counts.news', 1)
            ->assertJsonPath('counts.events', 1)
            ->assertJsonPath('counts.research', 1)
            ->assertJsonPath('counts.publications', 1)
            ->assertJsonPath('news.0.slug', 'quality-education-story')
            ->assertJsonPath('events.0.slug', 'education-fair')
            ->assertJsonPath('research.0.slug', 'teaching-study')
            ->assertJsonPath('publications.0.slug', 'education-paper');
    }

    public function test_unknown_sdg_is_not_found(): void
    {
        $this->getJson('/api/v1/sdgs/18')->assertNotFound();
    }

    public function test_public_sdg_index_returns_per_goal_totals(): void
    {
        $author = User::factory()->create();

        Article::query()->create([
            'user_id' => $author->id,
            'slug' => 'quality-education-story',
            'title' => 'Quality education story',
            'sdg_goals' => [4, 17],
            'published_at' => now()->subDay(),
        ]);
        CampusEvent::query()->create([
            'user_id' => $author->id,
            'slug' => 'education-fair',
            'title' => 'Education fair',
            'starts_at' => now()->addWeek(),
            'sdg_goals' => [4],
        ]);
        ResearchPublication::query()->create([
            'user_id' => $author->id,
            'slug' => 'teaching-study',
            'title' => 'Teaching study',
            'academic_program_id' => 'bachelor-science-information-technology',
            'output_type' => ResearchPublication::OUTPUT_RESEARCH,
            'sdg_goals' => [4],
            'published_at' => now()->subMonth(),
        ]);

        $response = $this->getJson('/api/v1/sdgs');

        $response->assertOk();
        $goals = collect($response->json('goals'))->keyBy('id');
        $this->assertCount(17, $response->json('goals'));
        $this->assertSame(3, $goals[4]['total']);
        $this->assertSame(1, $goals[17]['total']);
        $this->assertSame(0, $goals[1]['total']);
    }

    public function test_public_sdg_page_returns_all_news_without_nine_item_limit(): void
    {
        $author = User::factory()->create();

        for ($i = 1; $i <= 14; $i++) {
            Article::query()->create([
                'user_id' => $author->id,
                'slug' => "goal-4-news-{$i}",
                'title' => "Goal 4 News {$i}",
                'sdg_goals' => [4],
                'published_at' => now()->subHours($i),
            ]);
        }

        $response = $this->getJson('/api/v1/sdgs/4');

        $response->assertOk()
            ->assertJsonPath('counts.news', 14)
            ->assertJsonCount(14, 'news');

        $limited = $this->getJson('/api/v1/sdgs/4?news_limit=5');
        $limited->assertOk()
            ->assertJsonPath('counts.news', 14)
            ->assertJsonCount(5, 'news');
    }
}

