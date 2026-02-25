<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/portal/api')]
class WeatherController extends AbstractController
{
    #[Route('/weather', name: 'api_weather', methods: ['GET'])]
    public function weather(Request $request, HttpClientInterface $client): JsonResponse
    {
        $apiKey = (string) ($_ENV['OPENWEATHER_API_KEY'] ?? getenv('OPENWEATHER_API_KEY') ?: '');
        if ($apiKey === '') {
            return $this->json(['error' => 'missing_api_key'], 500);
        }

        try {
            $latParam = $request->query->get('lat');
            $lonParam = $request->query->get('lon');

            if ($latParam !== null && $lonParam !== null && $latParam !== '' && $lonParam !== '') {
                $lat = $latParam;
                $lon = $lonParam;
                $resolvedName = null;
            } else {
                $place = trim((string) $request->query->get('place', ''));
                if ($place === '') {
                    return $this->json(['error' => 'missing_place'], 400);
                }

            $geo = $client->request('GET', 'https://api.openweathermap.org/geo/1.0/direct', [
                'query' => [
                    'q' => $place,
                    'limit' => 1,
                    'appid' => $apiKey,
                ],
                'timeout' => 5.0,
            ])->toArray(false);

            if (!is_array($geo) || count($geo) === 0 || !isset($geo[0]['lat'], $geo[0]['lon'])) {
                $geo = $client->request('GET', 'https://nominatim.openstreetmap.org/search', [
                    'query' => [
                        'format' => 'json',
                        'limit' => 1,
                        'q' => $place,
                    ],
                    'headers' => [
                        'User-Agent' => 'HomeBalance/1.0 (local dev)',
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 5.0,
                ])->toArray(false);

                if (!is_array($geo) || count($geo) === 0 || !isset($geo[0]['lat'], $geo[0]['lon'])) {
                    return $this->json(['error' => 'not_found'], 404);
                }

                    $lat = $geo[0]['lat'];
                    $lon = $geo[0]['lon'];
                    $resolvedName = $geo[0]['display_name'] ?? $place;
            } else {
                $lat = $geo[0]['lat'];
                $lon = $geo[0]['lon'];
                $resolvedName = $geo[0]['name'] ?? $place;
            }
            }

            $data = $client->request('GET', 'https://api.openweathermap.org/data/2.5/weather', [
                'query' => [
                    'lat' => $lat,
                    'lon' => $lon,
                    'units' => 'metric',
                    'appid' => $apiKey,
                    'lang' => 'fr',
                ],
                'timeout' => 5.0,
            ])->toArray(false);
        } catch (TransportExceptionInterface | ClientExceptionInterface | ServerExceptionInterface $e) {
            return $this->json(['error' => 'weather_unavailable'], 502);
        }
        if (!isset($data['main']['temp'], $data['weather'][0]['description'], $data['weather'][0]['icon'])) {
            return $this->json(['error' => 'not_found'], 404);
        }

        return $this->json([
            'place' => $data['name'] ?? $resolvedName ?? ($place ?? ''),
            'temp' => round((float) $data['main']['temp'], 1),
            'description' => $data['weather'][0]['description'],
            'icon' => $data['weather'][0]['icon'],
        ]);
    }
}
