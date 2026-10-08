<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\CampusEvent;
use App\Models\ResearchPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_landing_and_published_content_at_first_priority(): void
    {
        Article::query()->create([
            'slug' => 'campus-news',
            'title' => 'Campus news',
            'published_at' => now()->subDay(),
            'no_index' => false,
        ]);
        Article::query()->create([
            'slug' => 'draft-hidden',
            'title' => 'Draft',
            'published_at' => null,
        ]);
        Article::query()->create([
            'slug' => 'noindex-post',
            'title' => 'Private',
            'published_at' => now(),
            'no_index' => true,
        ]);
        CampusEvent::query()->create([
            'slug' => 'foundation-day',
            'title' => 'Foundation Day',
            'starts_at' => now()->addWeek(),
        ]);
        ResearchPublication::query()->create([
            'slug' => 'climate-study',
            'title' => 'Climate study',
            'academic_program_id' => 'bachelor-science-information-technology',
            'output_type' => ResearchPublication::OUTPUT_RESEARCH,
            'published_at' => now()->subMonth(),
        ]);

        $response = $this->get('/api/v1/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('xml', strtolower((string) $response->headers->get('Content-Type')));

        $body = (string) $response->getContent();
        $this->assertStringContainsString('<loc>https://tcc.edu.ph/</loc>', $body);
        $this->assertStringContainsString('<loc>https://tcc.edu.ph/news/campus-news</loc>', $body);
        $this->assertStringContainsString('<loc>https://tcc.edu.ph/events/foundation-day</loc>', $body);
        $this->assertStringContainsString('<loc>https://tcc.edu.ph/research-publication/climate-study</loc>', $body);
        $this->assertStringContainsString('<loc>https://tcc.edu.ph/programs/bsit</loc>', $body);
        $this->assertStringContainsString('<priority>1.0</priority>', $body);
        $this->assertStringNotContainsString('draft-hidden', $body);
        $this->assertStringNotContainsString('noindex-post', $body);
        $this->assertStringNotContainsString('/cms', $body);
        $this->assertStringNotContainsString('/chats', $body);
    }
}
