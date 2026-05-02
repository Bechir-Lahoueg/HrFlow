<?php

namespace App\Tests\Service\Conger;

use App\Service\Rh\PublicHolidayService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[AllowMockObjectsWithoutExpectations]
class PublicHolidayServiceTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private PublicHolidayService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->service    = new PublicHolidayService($this->httpClient);
    }

    // ──────────────────────── countWorkingDays ───────────────────────────

    public function testCountWorkingDaysDuLundiAuVendredi(): void
    {
        // Trouver le prochain lundi
        $monday = new \DateTimeImmutable('next monday');
        $friday = $monday->modify('+4 days');

        // Pas de jours fériés
        $this->mockNoHolidays($monday->format('Y'));

        $result = $this->service->countWorkingDays($monday, $friday);

        $this->assertSame(5, $result);
    }

    public function testCountWorkingDaysExclutLeWeekEnd(): void
    {
        // Semaine + week-end : lundi au dimanche suivant
        $monday = new \DateTimeImmutable('next monday');
        $sunday = $monday->modify('+6 days');

        $this->mockNoHolidays($monday->format('Y'));

        $result = $this->service->countWorkingDays($monday, $sunday);

        $this->assertSame(5, $result); // 5 jours ouvrables, sam+dim exclus
    }

    public function testCountWorkingDaysExclutJoursFeries(): void
    {
        // Lundi au vendredi, avec le mercredi déclaré comme jour férié
        $monday    = new \DateTimeImmutable('next monday');
        $friday    = $monday->modify('+4 days');
        $wednesday = $monday->modify('+2 days');

        $this->mockHolidays($monday->format('Y'), [$wednesday->format('Y-m-d')]);

        $result = $this->service->countWorkingDays($monday, $friday);

        $this->assertSame(4, $result); // 5 - 1 jour férié
    }

    public function testCountWorkingDaysMemeJourRetourneUn(): void
    {
        $monday = new \DateTimeImmutable('next monday');

        $this->mockNoHolidays($monday->format('Y'));

        $result = $this->service->countWorkingDays($monday, $monday);

        $this->assertSame(1, $result);
    }

    public function testCountWorkingDaysSamediRetourneZero(): void
    {
        $saturday = new \DateTimeImmutable('next saturday');

        $this->mockNoHolidays($saturday->format('Y'));

        $result = $this->service->countWorkingDays($saturday, $saturday);

        $this->assertSame(0, $result);
    }

    public function testCountWorkingDaysRetourneZeroQuandApiEchoue(): void
    {
        $monday = new \DateTimeImmutable('next monday');
        $friday = $monday->modify('+4 days');

        // L'API plante : le service doit fail-open (pas de jours fériés → retour normal)
        $this->httpClient
            ->method('request')
            ->willThrowException(new \RuntimeException('Network error'));

        $result = $this->service->countWorkingDays($monday, $friday);

        // Fail-open : API injoignable = aucun jour férié comptabilisé
        $this->assertSame(5, $result);
    }

    // ──────────────────────── hasHolidayInRange ──────────────────────────

    public function testHasHolidayInRangeRetourneFalseQuandAucunFerie(): void
    {
        $monday = new \DateTimeImmutable('next monday');
        $friday = $monday->modify('+4 days');

        $this->mockNoHolidays($monday->format('Y'));

        $result = $this->service->hasHolidayInRange($monday, $friday);

        $this->assertFalse($result);
    }

    public function testHasHolidayInRangeRetourneTrueQuandFerieDansLaPeriode(): void
    {
        $monday    = new \DateTimeImmutable('next monday');
        $friday    = $monday->modify('+4 days');
        $wednesday = $monday->modify('+2 days');

        $this->mockHolidays($monday->format('Y'), [$wednesday->format('Y-m-d')]);

        $result = $this->service->hasHolidayInRange($monday, $friday);

        $this->assertTrue($result);
    }

    public function testHasHolidayInRangeRetourneFalseQuandFerieHorsPeriode(): void
    {
        $monday = new \DateTimeImmutable('next monday');
        $friday = $monday->modify('+4 days');

        // Jour férié 10 jours plus tard → hors de la période
        $laterHoliday = $monday->modify('+10 days');
        $this->mockHolidays($monday->format('Y'), [$laterHoliday->format('Y-m-d')]);

        $result = $this->service->hasHolidayInRange($monday, $friday);

        $this->assertFalse($result);
    }

    // ──────────────────────── getHolidayDatesInRange ─────────────────────

    public function testGetHolidayDatesInRangeRetourneListeDesFeries(): void
    {
        $monday    = new \DateTimeImmutable('next monday');
        $friday    = $monday->modify('+4 days');
        $wednesday = $monday->modify('+2 days');
        $thursday  = $monday->modify('+3 days');

        $this->mockHolidays($monday->format('Y'), [
            $wednesday->format('Y-m-d'),
            $thursday->format('Y-m-d'),
        ]);

        $dates = $this->service->getHolidayDatesInRange($monday, $friday);

        $this->assertCount(2, $dates);
        $this->assertContains($wednesday->format('Y-m-d'), $dates);
        $this->assertContains($thursday->format('Y-m-d'), $dates);
    }

    public function testGetHolidayDatesInRangeRetourneTableauVideSansJoursFeries(): void
    {
        $monday = new \DateTimeImmutable('next monday');
        $friday = $monday->modify('+4 days');

        $this->mockNoHolidays($monday->format('Y'));

        $dates = $this->service->getHolidayDatesInRange($monday, $friday);

        $this->assertSame([], $dates);
    }

    // ──────────────────────── Helpers ────────────────────────────────────

    /**
     * Configure le mock HTTP pour retourner aucun jour férié pour l'année donnée.
     */
    private function mockNoHolidays(string $year): void
    {
        $this->mockHolidays($year, []);
    }

    /**
     * Configure le mock HTTP pour retourner une liste de dates de jours fériés.
     *
     * @param string[] $holidayDates
     */
    private function mockHolidays(string $year, array $holidayDates): void
    {
        $payload = array_map(fn(string $d) => ['date' => $d, 'name' => 'Férié'], $holidayDates);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn($payload);

        $this->httpClient
            ->method('request')
            ->willReturn($response);
    }
}
