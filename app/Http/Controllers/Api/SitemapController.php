<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\CampusEvent;
use App\Models\ResearchPublication;
use App\Support\ProgramOfferingMap;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $base = rtrim((string) config('app.frontend_url'), '/') ?: 'https://tcc.edu.ph';
        $urls = [];

        $add = function (string $path, float $priority, string $changefreq, ?Carbon $lastmod = null) use (&$urls, $base): void {
            $urls[] = [
                'loc' => $base.$path,
                'priority' => number_format($priority, 1, '.', ''),
                'changefreq' => $changefreq,
                'lastmod' => $lastmod?->toAtomString(),
            ];
        };

        $add('/', 1.0, 'daily', Carbon::now());

        $add('/news', 0.9, 'daily');
        $add('/events', 0.9, 'weekly');
        $add('/news-events', 0.9, 'daily');
        $add('/research-publication', 0.9, 'weekly');
        $add('/sdgs', 0.8, 'weekly');
        for ($goal = 1; $goal <= 17; $goal++) {
            $add('/sdgs/'.$goal, 0.7, 'weekly');
        }

        foreach (ProgramOfferingMap::offeringSlugs() as $slug) {
            $add('/programs/'.$slug, 1.0, 'monthly');
        }

        Article::query()
            ->published()
            ->where('no_index', false)
            ->orderByDesc('published_at')
            ->get(['slug', 'published_at', 'updated_at'])
            ->each(function (Article $article) use ($add): void {
                $add(
                    '/news/'.$article->slug,
                    1.0,
                    'weekly',
                    $article->updated_at ?? $article->published_at,
                );
            });

        CampusEvent::query()
            ->orderByDesc('starts_at')
            ->get(['slug', 'starts_at', 'updated_at'])
            ->each(function (CampusEvent $event) use ($add): void {
                $add(
                    '/events/'.$event->slug,
                    1.0,
                    'weekly',
                    $event->updated_at ?? $event->starts_at,
                );
            });

        ResearchPublication::query()
            ->published()
            ->orderByDesc('published_at')
            ->get(['slug', 'published_at', 'updated_at'])
            ->each(function (ResearchPublication $item) use ($add): void {
                $add(
                    '/research-publication/'.$item->slug,
                    1.0,
                    'weekly',
                    $item->updated_at ?? $item->published_at,
                );
            });

        $add('/about', 0.6, 'monthly');
        $add('/admissions', 0.6, 'monthly');
        $add('/academics', 0.6, 'monthly');
        $add('/contact', 0.5, 'yearly');
        $add('/scholarship', 0.5, 'monthly');
        $add('/downloadables', 0.5, 'monthly');

        return response($this->toXml($urls), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=600',
        ]);
    }

    /**
     * @param  list<array{loc: string, priority: string, changefreq: string, lastmod: string|null}>  $urls
     */
    private function toXml(array $urls): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.$this->xml($url['loc']).'</loc>';
            if ($url['lastmod']) {
                $lines[] = '    <lastmod>'.$this->xml($url['lastmod']).'</lastmod>';
            }
            $lines[] = '    <changefreq>'.$this->xml($url['changefreq']).'</changefreq>';
            $lines[] = '    <priority>'.$this->xml($url['priority']).'</priority>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
