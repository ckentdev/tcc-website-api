<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Support\PlainText;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillArticleMeta extends Command
{
    protected $signature = 'articles:backfill-meta {--force-excerpt : Replace excerpts with text from the body}';

    protected $description = 'Fill slug, excerpt, and SEO fields on existing news posts from title and body.';

    public function handle(): int
    {
        $slugCount = 0;
        $excerptCount = 0;
        $seoTitleCount = 0;
        $seoDescCount = 0;
        $ogAltCount = 0;

        Article::query()->orderBy('id')->each(function (Article $article) use (
            &$slugCount,
            &$excerptCount,
            &$seoTitleCount,
            &$seoDescCount,
            &$ogAltCount,
        ): void {
            $plainTitle = PlainText::compatible($article->title);
            $changed = false;

            if (Article::isPlaceholderSlug($article->slug)) {
                $article->slug = Article::slugFromTitle($article->title, $article->id);
                $slugCount++;
                $changed = true;
            }

            if ($this->option('force-excerpt') || ! filled($article->excerpt)) {
                $excerpt = PlainText::excerptFromBody($article->body);
                if ($excerpt !== '' && $excerpt !== $article->excerpt) {
                    $article->excerpt = $excerpt;
                    $excerptCount++;
                    $changed = true;
                }
            }

            if (! filled($article->seo_title) && $plainTitle !== '') {
                $article->seo_title = Str::limit($plainTitle, 255, '');
                $seoTitleCount++;
                $changed = true;
            }

            if ($this->option('force-excerpt') || ! filled($article->seo_description)) {
                $source = filled($article->excerpt) ? $article->excerpt : $article->body;
                $description = PlainText::snippet($source, 160);
                if ($description !== '' && $description !== $article->seo_description) {
                    $article->seo_description = $description;
                    $seoDescCount++;
                    $changed = true;
                }
            }

            if (! filled($article->og_image_alt) && $plainTitle !== '') {
                $article->og_image_alt = Str::limit($plainTitle, 255, '');
                $ogAltCount++;
                $changed = true;
            }

            if ($changed) {
                $article->save();
            }
        });

        $this->info("Updated slugs: {$slugCount}");
        $this->info("Filled excerpts: {$excerptCount}");
        $this->info("Filled SEO titles: {$seoTitleCount}");
        $this->info("Filled SEO descriptions: {$seoDescCount}");
        $this->info("Filled image alt: {$ogAltCount}");

        return self::SUCCESS;
    }
}
