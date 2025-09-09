<?php

namespace App\Services;

use Vimeo\Vimeo;
use Throwable;

class VimeoService
{
    private Vimeo $client;

    public function __construct()
{
    $client = config('services.vimeo.client');
    $secret = config('services.vimeo.secret');
    $access = config('services.vimeo.access');

    $this->client = new Vimeo($client, $secret, $access);
}

   

    /**
     * Fetch ALL videos for the authenticated user (handles pagination).
     * @param int $perPage up to 100
     * @param string|null $fields comma-separated fields to shrink payload
     * @return array{videos: array<int, array>, total: int}
     * @throws \RuntimeException on API errors
     */
    public function listAllMyVideos(int $perPage = 100, ?string $fields = null): array
    {
        $videos = [];
        $query  = ['per_page' => $perPage];
        if ($fields) $query['fields'] = $fields;

        $uri = '/me/videos';

        try {
            while ($uri) {
                $resp = $this->client->request($uri, $query, 'GET');

                // Basic error guard
                if (($resp['status'] ?? 0) < 200 || ($resp['status'] ?? 0) >= 300) {
                    $err = $resp['body']['error'] ?? 'Vimeo API error';
                    throw new \RuntimeException($err . " (HTTP {$resp['status']})");
                }

                $body = $resp['body'] ?? [];
                $videos = array_merge($videos, $body['data'] ?? []);

                // Vimeo returns absolute URLs for next page (or null)
                $uri    = $body['paging']['next'] ?? null;
                $query  = []; // after first call, pass no query; next already includes params
            }

            return ['videos' => $videos, 'total' => count($videos)];
        } catch (Throwable $e) {
            throw new \RuntimeException('Failed to fetch videos: ' . $e->getMessage(), 0, $e);
        }
    }
}
