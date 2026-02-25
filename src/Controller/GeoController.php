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

        $response = $client->request('GET', 'https://nominatim.openstreetmap.org/search', [
            'query' => [
                'format' => 'json',
                'limit' => 1,
                'q' => $query,
            ],
            'headers' => [
                'User-Agent' => 'HomeBalance/1.0 (local dev)',
                'Accept' => 'application/json',
            ],
        ]);

        return $this->json($response->toArray(false));
    }

    #[Route('/reverse', name: 'geo_reverse', methods: ['GET'])]
    public function reverse(Request $request, HttpClientInterface $client): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lon = $request->query->get('lon');
        if ($lat === null || $lon === null || $lat === '' || $lon === '') {
            return $this->json([]);
        }

        $response = $client->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
            'query' => [
                'format' => 'json',
                'lat' => $lat,
                'lon' => $lon,
            ],
            'headers' => [
                'User-Agent' => 'HomeBalance/1.0 (local dev)',
                'Accept' => 'application/json',
            ],
        ]);

        return $this->json($response->toArray(false));
    }
}
