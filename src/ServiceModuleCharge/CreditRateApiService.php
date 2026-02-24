<?php

namespace App\ServiceModuleCharge;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CreditRateApiService
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array{rate: float, year: int}|null
     */
    public function fetchLendingRate(string $countryCode = 'TN'): ?array
    {
        $country = strtoupper(trim($countryCode));
        if ($country === '') {
            $country = 'TN';
        }

        $url = sprintf(
            'https://api.worldbank.org/v2/country/%s/indicator/FR.INR.LEND?format=json&per_page=70',
            rawurlencode($country)
        );

        try {
            $response = $this->httpClient->request('GET', $url, ['timeout' => 8]);
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface) {
            return null;
        } catch (\Throwable) {
            return null;
        }

        if (!\is_array($payload) || !isset($payload[1]) || !\is_array($payload[1])) {
            return null;
        }

        foreach ($payload[1] as $row) {
            if (!\is_array($row) || !isset($row['value'], $row['date'])) {
                continue;
            }

            if (!is_numeric($row['value'])) {
                continue;
            }

            return [
                'rate' => round((float) $row['value'], 2),
                'year' => (int) $row['date'],
            ];
        }

        return null;
    }
}
