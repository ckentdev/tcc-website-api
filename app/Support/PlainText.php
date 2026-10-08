<?php

namespace App\Support;

use Illuminate\Support\Str;

final class PlainText
{
    public static function compatible(string $text, bool $preserveNewlines = false): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_KC);
            if (is_string($normalized) && $normalized !== '') {
                $text = $normalized;
            }
        }
        if ($preserveNewlines) {
            $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
            $text = preg_replace('/\n+/u', "\n", $text) ?? $text;
        } else {
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        }

        return trim($text);
    }

    public static function fromHtml(?string $html, bool $preserveNewlines = false): string
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        $withBreaks = preg_replace('/<\s*(br|\/p|\/div|\/h[1-6]|\/li|\/blockquote)\s*\/?>/i', "\n", $html) ?? $html;

        return self::compatible(strip_tags($withBreaks), $preserveNewlines);
    }

    public static function snippet(?string $html, int $max = 160): string
    {
        $text = self::fromHtml($html);
        if ($text === '') {
            return '';
        }

        return self::limit($text, $max);
    }

    /** First body paragraph(s) as a listing / social excerpt. */
    public static function excerptFromBody(?string $html, int $max = 480): string
    {
        $text = self::fromHtml($html, true);
        if ($text === '') {
            return '';
        }

        $parts = [];
        foreach (preg_split('/\n+/', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $parts[] = $paragraph;
            $joined = implode(' ', $parts);
            $length = function_exists('mb_strlen') ? mb_strlen($joined) : strlen($joined);
            if ($length >= 60) {
                return self::limit($joined, $max);
            }
        }

        return self::limit(implode(' ', $parts), $max);
    }

    public static function limit(string $text, int $max): string
    {
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($length <= $max) {
            return $text;
        }

        $cut = function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
        if (preg_match('/^(.+?[.!?])(?:\s|$)/u', $cut, $match)) {
            $sentence = rtrim($match[1]);
            $sentenceLen = function_exists('mb_strlen') ? mb_strlen($sentence) : strlen($sentence);
            if ($sentenceLen >= 80 && ! preg_match('/\b[A-Z]\.$/', $sentence)) {
                return $sentence;
            }
        }

        $cut = function_exists('mb_substr') ? mb_substr($text, 0, $max - 1) : substr($text, 0, $max - 1);
        if (preg_match('/^(.*)\s\S*$/u', $cut, $match) && trim($match[1]) !== '') {
            $cut = $match[1];
        }

        return rtrim($cut).'…';
    }

    public static function slugBase(string $title): string
    {
        $slug = Str::slug(self::compatible($title));
        if (strlen($slug) > 80) {
            $slug = rtrim(substr($slug, 0, 80), '-');
        }

        return $slug !== '' ? $slug : 'post';
    }
}
