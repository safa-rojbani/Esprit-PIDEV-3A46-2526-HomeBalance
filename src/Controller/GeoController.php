<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/portal/geo')]
class GeoController extends AbstractController
{
    #[Route('/search', name: 'geo_search', methods: ['GET'])]
    public function search(Request $request, HttpClientInterface $client): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if ($query === '') {
            return $this->json([]);
        }

        $lang = trim((string) $request->query->get('lang', 'fr')) ?: 'fr';
        $country = trim((string) $request->query->get('country', ''));

        $doSearch = function (string $q, ?string $countryCodes = null, string $lang = 'fr') use ($client): array {
            $params = [
                'format' => 'json',
                'limit' => 3,
                'q' => $q,
                'accept-language' => $lang,
            ];
            if ($countryCodes) {
                $params['countrycodes'] = $countryCodes;
            }
            $response = $client->request('GET', 'https://nominatim.openstreetmap.org/search', [
                'query' => $params,
                'headers' => [
                    'User-Agent' => 'HomeBalance/1.0 (local dev)',
                    'Accept' => 'application/json',
                    'Accept-Language' => $lang,
                ],
            ]);

            return $response->toArray(false);
        };

        $countryCode = $country !== '' ? $country : null;
        $results = $doSearch($query, $countryCode, $lang);
        if (empty($results)) {
            $fallbackQuery = $query . ', Tunisia';
            $results = $doSearch($fallbackQuery, $countryCode ?? 'tn', $lang);
        }

        return $this->json($results);
    }

    #[Route('/reverse', name: 'geo_reverse', methods: ['GET'])]
    public function reverse(Request $request, HttpClientInterface $client): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lon = $request->query->get('lon');
        if ($lat === null || $lon === null || $lat === '' || $lon === '') {
            return $this->json([]);
        }

        $lang = trim((string) $request->query->get('lang', 'fr')) ?: 'fr';

        $response = $client->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
            'query' => [
                'format' => 'json',
                'lat' => $lat,
                'lon' => $lon,
                'accept-language' => $lang,
            ],
            'headers' => [
                'User-Agent' => 'HomeBalance/1.0 (local dev)',
                'Accept' => 'application/json',
                'Accept-Language' => $lang,
            ],
        ]);

        return $this->json($response->toArray(false));
    }
}
