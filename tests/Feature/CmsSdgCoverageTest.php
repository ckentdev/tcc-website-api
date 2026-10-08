<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ResearchPublication;
use App\Models\User;
use App\Support\CmsModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CmsSdgCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_counts_per_sdg_for_news_research_and_publications(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $author = User::factory()->create([
            'is_admin' => false,
            'cms_modules' => [CmsModule::POSTS, CmsModule::RESEARCH],
        ]);

        Article::query()->create([
            'user_id' => $author->id,
            'slug' => 'quality-education-story',
            'title' => 'Quality education story',
            'sdg_goals' => [4, 17],
            'published_at' => now()->subDay(),
        ]);
        Article::query()->create([
            'user_id' => $author->id,
            'slug' => 'untagged-news',
            'title' => 'Untagged news',
            'sdg_goals' => null,
            'published_at' => now()->subDay(),
        ]);
        ResearchPublication::query()->create([
            'user_id' => $author->id,
            'slug' => 'climate-research',
            'title' => 'Climate research',
            'academic_program_id' => 'bachelor-science-information-technology',
            'output_type' => ResearchPublication::OUTPUT_RESEARCH,
            'sdg_goals' => [13, 4],
            'published_at' => now()->subMonth(),
        ]);
        ResearchPublication::query()->create([
            'user_id' => $author->id,
            'slug' => 'partnership-paper',
            'title' => 'Partnership paper',
            'academic_program_id' => 'bachelor-science-information-technology',
            'output_type' => ResearchPublication::OUTPUT_PUBLICATION,
            'sdg_goals' => [17],
            'published_at' => now()->subWeek(),
        ]);

        Sanctum::actingAs($admin, ['cms:access']);

        $response = $this->getJson('/api/v1/cms/sdg-coverage');

        $response->assertOk()
            ->assertJsonPath('scope', 'all')
            ->assertJsonPath('summary.articles.total', 2)
            ->assertJsonPath('summary.articles.tagged', 1)
            ->assertJsonPath('summary.articles.untagged', 1)
            ->assertJsonPath('summary.research.tagged', 1)
            ->assertJsonPath('summary.publications.tagged', 1)
            ->assertJsonPath('summary.goals_with_content', 3);

        $goals = collect($response->json('goals'))->keyBy('id');
        $this->assertSame(1, $goals[4]['articles']['total']);
        $this->assertSame(1, $goals[4]['research']['total']);
        $this->assertSame(0, $goals[4]['publications']['total']);
        $this->assertSame(2, $goals[4]['total']);
        $this->assertSame(1, $goals[17]['articles']['total']);
        $this->assertSame(1, $goals[17]['publications']['total']);
        $this->assertSame(1, $goals[13]['research']['total']);

        $detail = $this->getJson('/api/v1/cms/sdg-coverage/4');
        $detail->assertOk()
            ->assertJsonPath('id', 4)
            ->assertJsonCount(1, 'articles')
            ->assertJsonCount(1, 'research')
            ->assertJsonCount(0, 'publications')
            ->assertJsonPath('articles.0.title', 'Quality education story')
            ->assertJsonPath('research.0.title', 'Climate research');
    }

    public function test_authors_only_see_their_own_sdg_coverage(): void
    {
        $author = User::factory()->create([
            'is_admin' => false,
            'cms_modules' => [CmsModule::POSTS],
        ]);
        $other = User::factory()->create([
            'is_admin' => false,
            'cms_modules' => [CmsModule::POSTS],
        ]);

        Article::query()->create([
            'user_id' => $author->id,
            'slug' => 'mine',
            'title' => 'Mine',
            'sdg_goals' => [1],
            'published_at' => now(),
        ]);
        Article::query()->create([
            'user_id' => $other->id,
            'slug' => 'theirs',
            'title' => 'Theirs',
            'sdg_goals' => [1],
            'published_at' => now(),
        ]);

        Sanctum::actingAs($author, ['cms:access']);

        $this->getJson('/api/v1/cms/sdg-coverage')
            ->assertOk()
            ->assertJsonPath('scope', 'own')
            ->assertJsonPath('summary.articles.total', 1)
            ->assertJsonPath('summary.articles.tagged', 1);

        $this->getJson('/api/v1/cms/sdg-coverage/1')
            ->assertOk()
            ->assertJsonCount(1, 'articles')
            ->assertJsonPath('articles.0.title', 'Mine');
    }

    public function test_assistant_only_authors_cannot_view_sdg_coverage(): void
    {
        $author = User::factory()->create([
            'is_admin' => false,
            'cms_modules' => [CmsModule::ASSISTANT],
        ]);

        Sanctum::actingAs($author, ['cms:access']);

        $this->getJson('/api/v1/cms/sdg-coverage')->assertForbidden();
        $this->getJson('/api/v1/cms/sdg-coverage/4')->assertForbidden();
    }

    public function test_unknown_goal_returns_not_found(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin, ['cms:access']);

        $this->getJson('/api/v1/cms/sdg-coverage/18')->assertNotFound();
    }
}
