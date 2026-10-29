<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Common\Channels\ChannelController;
use Common\Core\Controllers\HomeController;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FallbackRouteController
{
    static array $defaultRoutes = [
        'artist',
        'track',
        'album',
        'playlist',
        'tag',
        'user',
        'radio',
        'search',
        'library',
        'admin',
        'pricing',
        'checkout',
        'billing',
        'account-settings',
        'login',
        'register',
        'workspace',
        'password',
        'forgot-password',
    ];

    public function __invoke(string $path)
    {
        $parts = explode('/', $path);

        if (
            ($parts[0] === 'channel' || $parts[0] === 'channels') &&
            isset($parts[1])
        ) {
            $parts[0] = $parts[1];
            $parts[1] = $parts[2] ?? null;
            unset($parts[2]);
        }

        if (
            count($parts) > 2 ||
            count($parts) < 1 ||
            (count($parts) === 1 && in_array($parts[0], self::$defaultRoutes))
        ) {
            return $this->renderClient();
        }

        if (in_array($parts[0], ['blog', 'press']) && isset($parts[1])) {
            return $this->renderPageSection($parts[0], $parts[1]);
        }

        // first try to match a channel, if none is found, fallback to rendering client side app
        try {
            $slugOrId = $parts[0];
            $restriction = $parts[1] ?? null;
            return $this->renderChannel($slugOrId, $restriction);
        } catch (ModelNotFoundException) {
            return $this->renderClient();
        }
    }

    public function renderChannel(string $slugOrId, ?string $restriction = null)
    {
        $channel = app(Channel::class)->resolveRouteBinding($slugOrId);
        if ($restriction) {
            request()->merge(['restriction' => $restriction]);
        }
        return app(ChannelController::class)->show($channel);
    }

    public function renderPageSection(string $section, string $slug)
    {
        $response = $this->renderClient();
        $page = \Common\Pages\CustomPage::where('slug', $slug)->first();
        if (!$page) {
            return $response;
        }
        if ($response instanceof \Illuminate\Contracts\View\View) {
            $content = $response->render();
        } elseif ($response instanceof \Symfony\Component\HttpFoundation\Response) {
            $content = $response->getContent();
        } else {
            return $response;
        }
        $image = null;
        if ($page->body && preg_match('~<img[^>]+src="([^"]+)"~', $page->body, $m)) {
            $image = $m[1];
            if (!str_starts_with($image, 'http')) {
                $image = url($image);
            }
        }
        if (!$image) {
            $image = url('og-image.png');
        }
        $excerpt = \Illuminate\Support\Str::limit(trim(preg_replace('~\s+~', ' ', strip_tags($page->body ?? ''))), 180);
        $title = htmlspecialchars($page->title, ENT_QUOTES);
        $desc = htmlspecialchars($excerpt, ENT_QUOTES);
        $img = htmlspecialchars($image, ENT_QUOTES);
        $pageUrl = url($section . '/' . $page->slug);
        $tags = '<title>' . $title . ' - TuneMusics</title>'
            . '<meta name="description" content="' . $desc . '">'
            . '<meta property="og:site_name" content="TuneMusics">'
            . '<meta property="og:type" content="article">'
            . '<meta property="og:title" content="' . $title . '">'
            . '<meta property="og:description" content="' . $desc . '">'
            . '<meta property="og:url" content="' . $pageUrl . '">'
            . '<meta property="og:image" content="' . $img . '">'
            . '<meta name="twitter:card" content="summary_large_image">'
            . '<meta name="twitter:title" content="' . $title . '">'
            . '<meta name="twitter:description" content="' . $desc . '">'
            . '<meta name="twitter:image" content="' . $img . '">';
        $content = preg_replace('~<meta (?:property="og:|name="twitter:)[^>]*>\s*~', '', $content);
        $content = str_replace('<head>', '<head>' . $tags, $content);
        return response($content);
    }

    public function renderClient()
    {
        // no need to prerender channels here, use base HomeController
        request()->route()->action['uses'] = HomeController::class . '@show';
        return app(HomeController::class)->show();
    }
}
