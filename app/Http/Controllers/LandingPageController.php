<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Common\Billing\Models\Product;
use Common\Core\BaseController;
use Common\Pages\ContentTranslator;

class LandingPageController extends BaseController
{
    public function __invoke(ContentTranslator $translator)
    {
        return $this->renderClientOrApi([
            'pageName' => 'landing-page',
            'data' => $translator->translateLandingData(self::getData()),
        ]);
    }

    public static function getData()
    {
        $sections = settings('landingPage.sections') ?? [];

        $channels = collect($sections)
            ->filter(fn($s) => isset($s['channelId']))
            ->map(function ($s) {
                $channel = Channel::find($s['channelId']);
                if ($channel) {
                    $channel->loadContent(['perPage' => 10]);
                    return $channel->toApiResource(isNested: true);
                }
                return null;
            })
            ->filter()
            ->values();

        return [
            'loader' => 'landingPage',
            'products' => Product::with(['permissions', 'prices'])
                ->limit(15)
                ->orderBy('position', 'asc')
                ->get(),
            'sections' => $sections,
            'channels' => $channels,
        ];
    }
}
