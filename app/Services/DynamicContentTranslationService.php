<?php

namespace App\Services;

use Common\Pages\ContentTranslator;
use Common\Pages\CustomPage;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stichoza\GoogleTranslate\GoogleTranslate;

class DynamicContentTranslationService implements ContentTranslator
{
    public function translateCustomPage(CustomPage $page): CustomPage
    {
        if (!$this->shouldTranslate()) {
            return $page;
        }

        try {
            $stringsToTranslate = [
                'title' => $page->title,
                'body' => $page->body,
            ];
            $translations = $this->translateBatch($stringsToTranslate, App::getLocale());

            if (isset($translations['title'])) {
                $page->title = $translations['title'];
            }
            if (isset($translations['body'])) {
                $page->body = $translations['body'];
            }
        } catch (\Exception $e) {
            Log::error('Google Translate Error (Custom Page): ' . $e->getMessage());
        }

        return $page;
    }

    public function translateLandingData(array $data): array
    {
        if (!$this->shouldTranslate()) {
            return $data;
        }

        try {
            $targetLocale = App::getLocale();
            $stringsToTranslate = [];
            $appearance = settings('homepage.appearance');

            if (isset($data['sections'])) {
                foreach ($data['sections'] as $i => $section) {
                    if (isset($section['title'])) $stringsToTranslate["sections.$i.title"] = $section['title'];
                    if (isset($section['description'])) $stringsToTranslate["sections.$i.description"] = $section['description'];
                    if (isset($section['badge'])) $stringsToTranslate["sections.$i.badge"] = $section['badge'];
                    if (isset($section['features']) && is_array($section['features'])) {
                        foreach ($section['features'] as $j => $feature) {
                            if (isset($feature['title'])) $stringsToTranslate["sections.$i.features.$j.title"] = $feature['title'];
                            if (isset($feature['description'])) $stringsToTranslate["sections.$i.features.$j.description"] = $feature['description'];
                        }
                    }
                }
            }

            if (isset($data['products'])) {
                foreach ($data['products'] as $i => $product) {
                    if ($product->name) $stringsToTranslate["products.$i.name"] = $product->name;
                    if ($product->description) $stringsToTranslate["products.$i.description"] = $product->description;
                    if ($product->feature_list && is_array($product->feature_list)) {
                        foreach ($product->feature_list as $j => $feature) {
                            $stringsToTranslate["products.$i.feature_list.$j"] = $feature;
                        }
                    }
                }
            }

            if ($appearance) {
                if (isset($appearance['headerTitle'])) $stringsToTranslate["appearance.headerTitle"] = $appearance['headerTitle'];
                if (isset($appearance['headerSubtitle'])) $stringsToTranslate["appearance.headerSubtitle"] = $appearance['headerSubtitle'];
                if (isset($appearance['footerTitle'])) $stringsToTranslate["appearance.footerTitle"] = $appearance['footerTitle'];
                if (isset($appearance['footerSubtitle'])) $stringsToTranslate["appearance.footerSubtitle"] = $appearance['footerSubtitle'];
            }

            if (empty($stringsToTranslate)) {
                return $data;
            }

            $translations = $this->translateBatch($stringsToTranslate, $targetLocale);

            if (isset($data['sections'])) {
                foreach ($data['sections'] as $i => &$section) {
                    if (isset($translations["sections.$i.title"])) $section['title'] = $translations["sections.$i.title"];
                    if (isset($translations["sections.$i.description"])) $section['description'] = $translations["sections.$i.description"];
                    if (isset($translations["sections.$i.badge"])) $section['badge'] = $translations["sections.$i.badge"];
                    if (isset($section['features']) && is_array($section['features'])) {
                        foreach ($section['features'] as $j => &$feature) {
                            if (isset($translations["sections.$i.features.$j.title"])) $feature['title'] = $translations["sections.$i.features.$j.title"];
                            if (isset($translations["sections.$i.features.$j.description"])) $feature['description'] = $translations["sections.$i.features.$j.description"];
                        }
                    }
                }
            }

            if (isset($data['products'])) {
                foreach ($data['products'] as $i => $product) {
                    if (isset($translations["products.$i.name"])) $product->name = $translations["products.$i.name"];
                    if (isset($translations["products.$i.description"])) $product->description = $translations["products.$i.description"];
                    if ($product->feature_list && is_array($product->feature_list)) {
                        $translatedFeatures = [];
                        foreach ($product->feature_list as $j => $feature) {
                            $translatedFeatures[] = $translations["products.$i.feature_list.$j"] ?? $feature;
                        }
                        $product->feature_list = $translatedFeatures;
                    }
                }
            }

            if ($appearance) {
                $translatedAppearance = $appearance;
                if (isset($translations["appearance.headerTitle"])) $translatedAppearance['headerTitle'] = $translations["appearance.headerTitle"];
                if (isset($translations["appearance.headerSubtitle"])) $translatedAppearance['headerSubtitle'] = $translations["appearance.headerSubtitle"];
                if (isset($translations["appearance.footerTitle"])) $translatedAppearance['footerTitle'] = $translations["appearance.footerTitle"];
                if (isset($translations["appearance.footerSubtitle"])) $translatedAppearance['footerSubtitle'] = $translations["appearance.footerSubtitle"];
                $data['appearance'] = $translatedAppearance;
            }
        } catch (\Exception $e) {
            Log::error('Google Translate Error: ' . $e->getMessage());
        }

        return $data;
    }

    public function translateAuthLines(array $lines): array
    {
        if (!settings('i18n.enable', true)) {
            return $lines;
        }
        $path = request()->path();
        $isAuthPage = str_contains($path, 'login') || str_contains($path, 'register') || str_contains($path, 'forgot-password');
        $isNotDefaultLocale = App::getLocale() !== config('app.fallback_locale', 'en');
        if (!$isAuthPage || !$isNotDefaultLocale) {
            return $lines;
        }

        try {
            $defaultPath = resource_path('lang/' . config('app.fallback_locale', 'en') . '.json');
            $defaultLines = (file_exists($defaultPath) ? json_decode(file_get_contents($defaultPath), true) : null) ?? [];
            $clientPath = resource_path('client-translations.json');
            $serverPath = resource_path('server-translations.json');
            $clientLines = (file_exists($clientPath) ? json_decode(file_get_contents($clientPath), true) : null) ?? [];
            $serverLines = (file_exists($serverPath) ? json_decode(file_get_contents($serverPath), true) : null) ?? [];
            $merged = array_merge($clientLines, $serverLines, $defaultLines);
            $toTranslate = [];
            foreach ($merged as $key => $defaultVal) {
                $val = $lines[$key] ?? null;
                if (($val === null || $val === $key) && is_string($defaultVal)) {
                    $toTranslate[$key] = $defaultVal;
                }
            }
            if (empty($toTranslate)) {
                return $lines;
            }
            $translations = $this->translateBatch($toTranslate, App::getLocale());
            return array_merge($lines, $translations);
        } catch (\Exception $e) {
            Log::error('Google Translate Error (Auth): ' . $e->getMessage());
            return $lines;
        }
    }

    // generic helper for controllers (press/blog lists and articles)
    public function translateStrings(array $strings): array
    {
        if (
            App::getLocale() === config('app.fallback_locale', 'en') ||
            empty($strings)
        ) {
            return $strings;
        }
        try {
            return array_merge(
                $strings,
                $this->translateBatch($strings, App::getLocale()),
            );
        } catch (\Exception $e) {
            Log::error('Google Translate Error (strings): ' . $e->getMessage());
            return $strings;
        }
    }

    private function translateBatch(array $strings, string $targetLocale): array
    {
        $delimiter = ' ||| ';
        $translations = [];
        $toTranslate = [];
        $map = [];
        $placeholderMap = [];

        foreach ($strings as $key => $text) {
            $cacheKey = "gt_v4_{$targetLocale}_" . md5($text);
            $cached = Cache::get($cacheKey);

            if ($cached) {
                $translations[$key] = $cached;
            } else {
                [$protected, $phMap] = $this->protectPlaceholders($text);
                $toTranslate[] = $protected;
                $map[] = $key;
                $placeholderMap[$key] = $phMap;
            }
        }

        if (!empty($toTranslate)) {
            $gt = new GoogleTranslate($targetLocale);
            $combined = implode($delimiter, $toTranslate);
            $translatedCombined = $gt->translate($combined);
            $translatedParts = explode($delimiter, $translatedCombined);

            foreach ($map as $index => $key) {
                $translatedText = $translatedParts[$index] ?? $toTranslate[$index];
                $translatedText = $this->restorePlaceholders($translatedText, $placeholderMap[$key] ?? []);
                $translations[$key] = $translatedText;
                $cacheKey = "gt_v4_{$targetLocale}_" . md5($strings[$key]);
                Cache::put($cacheKey, $translatedText, 86400);
            }
        }

        return $translations;
    }

    private function protectPlaceholders(string $text): array
    {
        $map = [];
        $i = 0;
        $protected = preg_replace_callback('/:([a-zA-Z][a-zA-Z0-9_]*)/', function ($m) use (&$i, &$map) {
            $token = '〚' . $i . '〛';
            $map[$token] = ':' . $m[1];
            $i++;
            return $token;
        }, $text);
        return [$protected, $map];
    }

    private function restorePlaceholders(string $text, array $map): string
    {
        foreach ($map as $token => $placeholder) {
            $text = str_replace($token, $placeholder, $text);
        }
        return $text;
    }

    private function shouldTranslate(): bool
    {
        $path = request()->path();
        // admin editor loads custom-pages/{numeric id}; must get ORIGINAL text
        if (preg_match('#custom-pages/\\d+$#', $path)) {
            return false;
        }
        $isLandingPage = in_array($path, ['', '/'], true) || str_contains($path, 'landing-page-data');
        $isCustomPage = str_contains($path, 'pages/') || str_contains($path, 'custom-page') || str_contains($path, 'pages-section');
        $isNotDefaultLocale = App::getLocale() !== config('app.fallback_locale', 'en');

        return ($isLandingPage || $isCustomPage) && $isNotDefaultLocale;
    }
}
