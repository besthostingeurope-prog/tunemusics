<?php

namespace App\Http\Controllers;

use Common\Core\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ConcertEventsController extends BaseController
{
    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:0|max:40',
        ]);

        $apiKey = config('services.ticketmaster.key');
        if (!$apiKey) {
            return $this->success(['configured' => false, 'events' => []]);
        }

        $params = array_filter([
            'keyword' => $data['q'] ?? null,
            'city' => $data['city'] ?? null,
            'page' => $data['page'] ?? 0,
        ]);

        $cacheKey = 'ticketmaster.search.' . md5(json_encode($params));

        $events = Cache::remember($cacheKey, now()->addHours(6), function () use (
            $apiKey,
            $params,
        ) {
            return $this->fetchEvents($apiKey, $params);
        });

        return $this->success([
            'configured' => true,
            'events' => $events,
        ]);
    }

    public function forArtist(string $name)
    {
        $apiKey = config('services.ticketmaster.key');
        if (!$apiKey) {
            return $this->success(['configured' => false, 'events' => []]);
        }

        $cacheKey = 'ticketmaster.artist.' . md5(mb_strtolower($name));

        $events = Cache::remember($cacheKey, now()->addHours(12), function () use (
            $apiKey,
            $name,
        ) {
            return $this->fetchEvents($apiKey, [
                'keyword' => $name,
                'classificationName' => 'music',
                'sort' => 'date,asc',
            ]);
        });

        return $this->success([
            'configured' => true,
            'events' => $events,
        ]);
    }

    protected function fetchEvents(string $apiKey, array $params): array
    {
        try {
            $response = Http::timeout(8)->get(
                'https://app.ticketmaster.com/discovery/v2/events.json',
                array_merge($params, [
                    'apikey' => $apiKey,
                    'size' => 40,
                ]),
            );

            if (!$response->successful()) {
                return [];
            }

            $events = $response->json('_embedded.events') ?? [];

            return collect($events)
                ->map(function (array $event) {
                    $venue = $event['_embedded']['venues'][0] ?? [];
                    $images = collect($event['images'] ?? []);
                    $image =
                        $images->firstWhere('ratio', '16_9')['url'] ??
                        ($images->first()['url'] ?? null);
                    return [
                        'id' => $event['id'] ?? null,
                        'name' => $event['name'] ?? null,
                        'url' => $event['url'] ?? null,
                        'image' => $image,
                        'date' => $event['dates']['start']['localDate'] ?? null,
                        'time' => $event['dates']['start']['localTime'] ?? null,
                        'venue' => $venue['name'] ?? null,
                        'city' => $venue['city']['name'] ?? null,
                        'country' => $venue['country']['name'] ?? null,
                    ];
                })
                ->filter(fn($e) => $e['name'] && $e['date'])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
