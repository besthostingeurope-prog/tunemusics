<?php

namespace App\Http\Controllers;

use App\Services\DynamicContentTranslationService;
use Common\Core\BaseController;
use Common\Pages\CustomPage;
use Illuminate\Support\Str;

class PageSectionController extends BaseController
{
    /**
     * Lists custom pages belonging to a "section" (press / blog).
     * A page belongs to a section when its slug starts with the
     * section name - e.g. "press-release-launch" or "blog-my-post",
     * which happens automatically when the page title starts with
     * "Press ..." or "Blog ...".
     */
    public function index(string $section)
    {
        abort_unless(in_array($section, ['press', 'blog']), 404);
        $this->applyRequestedLocale();

        // existing pages that belong to a section but keep their original
        // slug for SEO reasons (renaming would break indexed urls)
        $extraSlugs = [
            'blog' => [
                'ai-music-streaming-platform',
                'upload-ai-generated-music',
                'platform-for-virtual-artists',
            ],
            'press' => [],
        ];

        $pages = CustomPage::where('type', 'default')
            ->where(function ($query) use ($section, $extraSlugs) {
                $query
                    ->where('slug', 'like', $section . '%')
                    ->orWhereIn('slug', $extraSlugs[$section]);
            })
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function (CustomPage $page) {
                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'image' => $this->extractImage($page),
                    'excerpt' => Str::limit(
                        trim(
                            preg_replace(
                                '/\s+/',
                                ' ',
                                strip_tags($page->body ?? ''),
                            ),
                        ),
                        180,
                    ),
                    'created_at' => $page->created_at,
                ];
            });

        $strings = [];
        foreach ($pages as $i => $page) {
            $strings["$i.title"] = $page['title'];
            $strings["$i.excerpt"] = $page['excerpt'];
        }
        $translated = app(
            DynamicContentTranslationService::class,
        )->translateStrings($strings);
        $pages = $pages->map(function ($page, $i) use ($translated) {
            $page['title'] = $translated["$i.title"] ?? $page['title'];
            $page['excerpt'] = $translated["$i.excerpt"] ?? $page['excerpt'];
            return $page;
        });

        return $this->success(['pages' => $pages]);
    }

    public function show(string $section, string $slug)
    {
        abort_unless(in_array($section, ['press', 'blog']), 404);
        $this->applyRequestedLocale();

        $page = CustomPage::where('slug', $slug)->firstOrFail();

        $page = app(
            DynamicContentTranslationService::class,
        )->translateCustomPage($page);

        return $this->success([
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'body' => $page->body,
                'created_at' => $page->created_at,
            ],
        ]);
    }

    protected function applyRequestedLocale(): void
    {
        $locale = request('locale');
        if (is_string($locale) && preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $locale)) {
            app()->setLocale(strtolower($locale));
        }
    }

    protected function extractImage(CustomPage $page): ?string
    {
        if (!empty($page->meta['image'])) {
            return $page->meta['image'];
        }
        if (
            $page->body &&
            preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $page->body, $m)
        ) {
            return $m[1];
        }
        return null;
    }
}
