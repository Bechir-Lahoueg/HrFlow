<?php

namespace App\Service\Rh;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PublicHolidayService
{
    private const COUNTRY_CODE = 'TN';

    /** @var array<int, array<string, bool>> */
    private array $holidaysByYear = [];

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function hasHolidayInRange(DateTimeImmutable $startDate, DateTimeImmutable $endDate): bool
    {
        return count($this->getHolidayDatesInRange($startDate, $endDate)) > 0;
    }

    /**
     * @return string[]
     */
    public function getHolidayDatesInRange(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $holidays = [];
        $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate->modify('+1 day'));

        foreach ($period as $day) {
            $dateKey = $day->format('Y-m-d');
            if ($this->isHoliday($day)) {
                $holidays[] = $dateKey;
            }
        }

        return $holidays;
    }

    public function countWorkingDays(DateTimeImmutable $startDate, DateTimeImmutable $endDate): int
    {
        $workingDays = 0;
        $period = new DatePeriod($startDate, new DateInterval('P1D'), $endDate->modify('+1 day'));

        foreach ($period as $day) {
            $dayOfWeek = (int) $day->format('N');
            if ($dayOfWeek >= 6) {
                continue;
            }

            if ($this->isHoliday($day)) {
                continue;
            }

            ++$workingDays;
        }

        return $workingDays;
    }

    private function isHoliday(DateTimeImmutable $day): bool
    {
        $year = (int) $day->format('Y');
        $dateKey = $day->format('Y-m-d');

        if (!isset($this->holidaysByYear[$year])) {
            $this->holidaysByYear[$year] = $this->loadHolidaysForYear($year);
        }

        return isset($this->holidaysByYear[$year][$dateKey]);
    }

    /**
     * @return array<string, bool>
     */
    private function loadHolidaysForYear(int $year): array
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                sprintf('https://date.nager.at/api/v3/PublicHolidays/%d/%s', $year, self::COUNTRY_CODE),
                ['timeout' => 5.0]
            );

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $payload = $response->toArray(false);

            $holidays = [];
            foreach ($payload as $item) {
                if (!is_array($item) || !isset($item['date']) || !is_string($item['date'])) {
                    continue;
                }

                $holidays[$item['date']] = true;
            }

            return $holidays;
        } catch (\Throwable) {
            // Fail-open when API is unavailable to avoid blocking all submissions.
            return [];
        }
    }
}
