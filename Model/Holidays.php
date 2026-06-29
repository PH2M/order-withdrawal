<?php

declare(strict_types=1);

namespace PH2M\OrderWithdrawal\Model;

use DateTimeImmutable;
use Magento\Framework\App\CacheInterface;
use PH2M\OrderWithdrawal\Exception\GetHolidaysApiFailed;
use Psr\Log\LoggerInterface;

class Holidays
{
    private const CACHE_ID       = 'ORDER_WITHDRAWAL_HOLIDAYS_%s_%s';
    private const CACHE_TAG      = 'order_withdrawal_holidays';
    private const API_URL        = 'https://date.nager.at/api/v3/PublicHolidays/%s/%s';
    private const CACHE_LIFETIME = 86400 * 365;

    public function __construct(
        private readonly CacheInterface  $cache,
        private readonly LoggerInterface $logger,
    )
    {
    }

    /**
     * @param string[] $years
     * @return array<string, DateTimeImmutable[]>
     * @throws GetHolidaysApiFailed
     */
    public function getHolidays(array $years, string $countryCode): array
    {
        $result = [];

        foreach ($years as $year) {
            $result[(string)$year] = $this->getYearHolidays((string)$year, $countryCode);
        }

        return $result;
    }

    /**
     * Tries the cache first; falls back to the public API on a miss.
     * @return DateTimeImmutable[]
     * @throws GetHolidaysApiFailed
     */
    private function getYearHolidays(string $year, string $countryCode): array
    {
        $cacheId = sprintf(self::CACHE_ID, $year, $countryCode);
        $cached = $this->cache->load($cacheId);

        if ($cached !== false) {
            $dates = (array)json_decode((string)$cached, true);
            if (!empty($dates)) {
                return $this->toDateTimeImmutable($dates);
            }
        }

        $dates = $this->fetchFromApi($year, $countryCode);

        $this->cache->save(
            (string)json_encode($dates),
            $cacheId,
            [self::CACHE_TAG],
            self::CACHE_LIFETIME,
        );

        return $this->toDateTimeImmutable($dates);
    }

    /**
     * @param string[] $dates ISO-8601 date strings
     * @return DateTimeImmutable[]
     */
    private function toDateTimeImmutable(array $dates): array
    {
        $result = [];

        foreach ($dates as $date) {
            try {
                $result[] = new DateTimeImmutable((string)$date);
            } catch (\Exception) {
                continue;
            }
        }

        return $result;
    }

    /**
     * @return string[] ISO-8601 date strings
     * @throws GetHolidaysApiFailed
     */
    private function fetchFromApi(string $year, string $countryCode): array
    {
        $url = sprintf(self::API_URL, $year, $countryCode);
        $ch = curl_init($url);

        if ($ch === false) {
            throw new GetHolidaysApiFailed(
                __('Unable to initialize a cURL session for the holidays API.')
            );
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($response) || $httpCode !== 200) {
            $message = __(
                'Holidays API returned HTTP %1 for country "%2" / year "%3".',
                $httpCode,
                $countryCode,
                $year,
            );
            $this->logger->warning((string)$message);

            throw new GetHolidaysApiFailed($message);
        }

        return array_column((array)json_decode($response, true), 'date');
    }
}
