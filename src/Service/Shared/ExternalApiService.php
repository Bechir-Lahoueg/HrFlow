<?php

namespace App\Service\Shared;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Agrégateur d'APIs externes pour les dashboards welcome.
 * Tous les appels sont mis en cache pour éviter de spammer les APIs gratuites.
 */
final class ExternalApiService
{
    private const TUNIS_LAT = 36.8065;
    private const TUNIS_LON = 10.1815;
    private const COUNTRY_CODE = 'TN';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::OPENWEATHER_API_KEY)%')]
        private readonly ?string $openWeatherKey = null,
        #[Autowire('%env(default::NEWS_API_KEY)%')]
        private readonly ?string $newsApiKey = null,
    ) {}

    // ─────────────────────────────────────────────────────────
    //  MÉTÉO (OpenWeatherMap)
    // ─────────────────────────────────────────────────────────

    /**
     * @return array{city:string,temp:float,feels_like:float,description:string,icon:string,humidity:int,wind:float,main:string}|null
     */
    public function getWeatherTunis(bool $refresh = false): ?array
    {
        $key = 'external.weather.tunis';
        if ($refresh) {
            $this->cache->delete($key);
        }

        return $this->cache->get($key, function (ItemInterface $item): ?array {
            $item->expiresAfter(1800); // 30 min

            if (!$this->openWeatherKey) {
                return $this->weatherFallback();
            }

            try {
                $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/weather', [
                    'query' => [
                        'lat' => self::TUNIS_LAT,
                        'lon' => self::TUNIS_LON,
                        'units' => 'metric',
                        'lang' => 'fr',
                        'appid' => $this->openWeatherKey,
                    ],
                    'timeout' => 4,
                ]);
                $data = $response->toArray(false);

                if (!isset($data['main'])) {
                    return $this->weatherFallback();
                }

                return [
                    'city' => $data['name'] ?? 'Tunis',
                    'temp' => (float) ($data['main']['temp'] ?? 0),
                    'feels_like' => (float) ($data['main']['feels_like'] ?? 0),
                    'description' => ucfirst((string) ($data['weather'][0]['description'] ?? 'N/A')),
                    'icon' => (string) ($data['weather'][0]['icon'] ?? '01d'),
                    'humidity' => (int) ($data['main']['humidity'] ?? 0),
                    'wind' => (float) ($data['wind']['speed'] ?? 0),
                    'main' => (string) ($data['weather'][0]['main'] ?? 'Clear'),
                ];
            } catch (\Throwable $e) {
                $this->logger->warning('Weather API failed', ['exception' => $e->getMessage()]);
                return $this->weatherFallback();
            }
        });
    }

    /**
     * Prévisions 5 jours (un point par jour à midi).
     * @return array<int, array{date:string,day:string,temp_min:float,temp_max:float,icon:string,description:string}>
     */
    public function getForecastTunis(bool $refresh = false): array
    {
        $key = 'external.forecast.tunis';
        if ($refresh) {
            $this->cache->delete($key);
        }

        return $this->cache->get($key, function (ItemInterface $item): array {
            $item->expiresAfter(3600); // 1h

            if (!$this->openWeatherKey) {
                return [];
            }

            try {
                $response = $this->httpClient->request('GET', 'https://api.openweathermap.org/data/2.5/forecast', [
                    'query' => [
                        'lat' => self::TUNIS_LAT,
                        'lon' => self::TUNIS_LON,
                        'units' => 'metric',
                        'lang' => 'fr',
                        'appid' => $this->openWeatherKey,
                    ],
                    'timeout' => 5,
                ]);
                $data = $response->toArray(false);

                $daily = [];
                $frDays = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

                foreach ($data['list'] ?? [] as $row) {
                    $dt = new \DateTimeImmutable('@' . ($row['dt'] ?? time()));
                    $dt = $dt->setTimezone(new \DateTimeZone('Africa/Tunis'));
                    $dateKey = $dt->format('Y-m-d');

                    if (!isset($daily[$dateKey])) {
                        $daily[$dateKey] = [
                            'date' => $dateKey,
                            'day' => $frDays[((int) $dt->format('N')) - 1] ?? $dt->format('D'),
                            'temp_min' => 999.0,
                            'temp_max' => -999.0,
                            'icon' => (string) ($row['weather'][0]['icon'] ?? '01d'),
                            'description' => ucfirst((string) ($row['weather'][0]['description'] ?? '')),
                        ];
                    }
                    $tmin = (float) ($row['main']['temp_min'] ?? 0);
                    $tmax = (float) ($row['main']['temp_max'] ?? 0);
                    if ($tmin < $daily[$dateKey]['temp_min']) {
                        $daily[$dateKey]['temp_min'] = $tmin;
                    }
                    if ($tmax > $daily[$dateKey]['temp_max']) {
                        $daily[$dateKey]['temp_max'] = $tmax;
                    }
                    // Le point à midi représente mieux la journée
                    if ($dt->format('H') === '12') {
                        $daily[$dateKey]['icon'] = (string) ($row['weather'][0]['icon'] ?? $daily[$dateKey]['icon']);
                        $daily[$dateKey]['description'] = ucfirst((string) ($row['weather'][0]['description'] ?? $daily[$dateKey]['description']));
                    }
                }

                return array_slice(array_values($daily), 0, 5);
            } catch (\Throwable $e) {
                $this->logger->warning('Forecast API failed', ['exception' => $e->getMessage()]);
                return [];
            }
        });
    }

    // ─────────────────────────────────────────────────────────
    //  JOURS FÉRIÉS (Nager.Date)
    // ─────────────────────────────────────────────────────────

    /**
     * Prochains jours fériés tunisiens (3 max).
     * @return array<int, array{date:string,name:string,local:string,days_until:int,formatted:string}>
     */
    public function getUpcomingHolidaysTN(bool $refresh = false): array
    {
        $key = 'external.holidays.tn.' . date('Y');
        if ($refresh) {
            $this->cache->delete($key);
        }

        $all = $this->cache->get($key, function (ItemInterface $item): array {
            $item->expiresAfter(86400); // 24h

            $collected = [];
            foreach ([(int) date('Y'), (int) date('Y') + 1] as $year) {
                try {
                    $response = $this->httpClient->request('GET', "https://date.nager.at/api/v3/PublicHolidays/{$year}/" . self::COUNTRY_CODE, [
                        'timeout' => 4,
                    ]);
                    $data = $response->toArray(false);
                    foreach ($data as $h) {
                        $collected[] = [
                            'date' => $h['date'] ?? '',
                            'name' => $h['localName'] ?? ($h['name'] ?? ''),
                            'local' => $h['name'] ?? '',
                        ];
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning('Holidays API failed', ['exception' => $e->getMessage(), 'year' => $year]);
                }
            }
            return $collected;
        });

        $today = new \DateTimeImmutable('today');
        $upcoming = [];

        $frMonths = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];

        foreach ($all as $h) {
            if (empty($h['date'])) {
                continue;
            }
            try {
                $dt = new \DateTimeImmutable($h['date']);
            } catch (\Throwable) {
                continue;
            }
            if ($dt < $today) {
                continue;
            }
            $days = (int) $today->diff($dt)->format('%a');
            $h['days_until'] = $days;
            $h['formatted'] = $dt->format('j') . ' ' . ($frMonths[(int) $dt->format('n')] ?? '') . ' ' . $dt->format('Y');
            $upcoming[] = $h;
        }

        usort($upcoming, static fn ($a, $b) => $a['days_until'] <=> $b['days_until']);

        return array_slice($upcoming, 0, 3);
    }

    // ─────────────────────────────────────────────────────────
    //  TAUX DE CHANGE (exchangerate.host)
    // ─────────────────────────────────────────────────────────

    /**
     * @return array<int, array{code:string,symbol:string,rate:float,trend:string}>
     */
    public function getExchangeRates(bool $refresh = false): array
    {
        $key = 'external.rates.tnd';
        if ($refresh) {
            $this->cache->delete($key);
        }

        return $this->cache->get($key, function (ItemInterface $item): array {
            $item->expiresAfter(21600); // 6h

            // API publique gratuite sans clé : open.er-api.com
            try {
                $response = $this->httpClient->request('GET', 'https://open.er-api.com/v6/latest/TND', [
                    'timeout' => 4,
                ]);
                $data = $response->toArray(false);
                $rates = $data['rates'] ?? [];

                $pairs = [
                    ['code' => 'EUR', 'symbol' => '€', 'label' => 'Euro'],
                    ['code' => 'USD', 'symbol' => '$', 'label' => 'Dollar US'],
                    ['code' => 'GBP', 'symbol' => '£', 'label' => 'Livre'],
                    ['code' => 'CHF', 'symbol' => 'CHF', 'label' => 'Franc Suisse'],
                ];

                $out = [];
                foreach ($pairs as $p) {
                    $rate = $rates[$p['code']] ?? null;
                    if ($rate === null || $rate <= 0) {
                        continue;
                    }
                    // Inverse : 1 EUR = X TND (plus lisible pour un utilisateur tunisien)
                    $inverse = 1 / (float) $rate;
                    $out[] = [
                        'code' => $p['code'],
                        'symbol' => $p['symbol'],
                        'label' => $p['label'],
                        'rate' => round($inverse, 3),
                        'trend' => $inverse >= 1 ? 'up' : 'down',
                    ];
                }
                return $out;
            } catch (\Throwable $e) {
                $this->logger->warning('ExchangeRate API failed', ['exception' => $e->getMessage()]);
                return [];
            }
        });
    }

    // ─────────────────────────────────────────────────────────
    //  ACTUALITÉS (NewsAPI / GNews)
    // ─────────────────────────────────────────────────────────

    /**
     * @return array<int, array{title:string,description:string,url:string,image:?string,source:string,published_at:string,formatted_date:string}>
     */
    public function getBusinessNews(string $topic = 'business', bool $refresh = false): array
    {
        $key = 'external.news.' . md5($topic);
        if ($refresh) {
            $this->cache->delete($key);
        }

        return $this->cache->get($key, function (ItemInterface $item) use ($topic): array {
            $item->expiresAfter(3600); // 1h

            if (!$this->newsApiKey) {
                return $this->newsFallback($topic);
            }

            try {
                // GNews (gratuit, clé obligatoire, supporte lang=fr)
                $response = $this->httpClient->request('GET', 'https://gnews.io/api/v4/search', [
                    'query' => [
                        'q' => $topic,
                        'lang' => 'fr',
                        'country' => 'fr',
                        'max' => 5,
                        'apikey' => $this->newsApiKey,
                    ],
                    'timeout' => 5,
                ]);
                $data = $response->toArray(false);
                $articles = $data['articles'] ?? [];

                $out = [];
                foreach ($articles as $a) {
                    $publishedAt = (string) ($a['publishedAt'] ?? '');
                    $out[] = [
                        'title' => (string) ($a['title'] ?? ''),
                        'description' => (string) ($a['description'] ?? ''),
                        'url' => (string) ($a['url'] ?? '#'),
                        'image' => $a['image'] ?? null,
                        'source' => (string) ($a['source']['name'] ?? 'Source inconnue'),
                        'published_at' => $publishedAt,
                        'formatted_date' => $this->formatRelativeDateFr($publishedAt),
                    ];
                }
                return $out;
            } catch (\Throwable $e) {
                $this->logger->warning('News API failed', ['exception' => $e->getMessage()]);
                return $this->newsFallback($topic);
            }
        });
    }

    // ─────────────────────────────────────────────────────────
    //  CITATIONS (Quotable)
    // ─────────────────────────────────────────────────────────

    /**
     * @return array{content:string,author:string,tags:array<int,string>}|null
     */
    public function getDailyQuote(string $tag = 'motivational', bool $refresh = false): ?array
    {
        $key = 'external.quote.' . $tag;
        if ($refresh) {
            $this->cache->delete($key);
        }

        return $this->cache->get($key, function (ItemInterface $item) use ($tag): ?array {
            $item->expiresAfter(21600); // 6h

            try {
                $response = $this->httpClient->request('GET', 'https://api.quotable.io/random', [
                    'query' => [
                        'tags' => $tag,
                        'maxLength' => 160,
                    ],
                    'timeout' => 4,
                    'verify_peer' => false,
                    'verify_host' => false,
                ]);
                $data = $response->toArray(false);

                if (empty($data['content'])) {
                    return $this->quoteFallback($tag);
                }

                return [
                    'content' => (string) $data['content'],
                    'author' => (string) ($data['author'] ?? 'Anonyme'),
                    'tags' => (array) ($data['tags'] ?? []),
                ];
            } catch (\Throwable $e) {
                $this->logger->warning('Quote API failed', ['exception' => $e->getMessage()]);
                return $this->quoteFallback($tag);
            }
        });
    }

    // ─────────────────────────────────────────────────────────
    //  CONSEIL DU JOUR (Advice Slip)
    // ─────────────────────────────────────────────────────────

    public function getAdviceOfTheDay(bool $refresh = false): ?string
    {
        $key = 'external.advice.' . date('Y-m-d');
        if ($refresh) {
            $this->cache->delete($key);
        }

        return $this->cache->get($key, function (ItemInterface $item): ?string {
            $item->expiresAfter(21600);

            try {
                $response = $this->httpClient->request('GET', 'https://api.adviceslip.com/advice', [
                    'timeout' => 4,
                ]);
                $data = $response->toArray(false);
                return (string) ($data['slip']['advice'] ?? $this->adviceFallback());
            } catch (\Throwable $e) {
                $this->logger->warning('Advice API failed', ['exception' => $e->getMessage()]);
                return $this->adviceFallback();
            }
        });
    }

    // ─────────────────────────────────────────────────────────
    //  FALLBACKS
    // ─────────────────────────────────────────────────────────

    private function weatherFallback(): array
    {
        return [
            'city' => 'Tunis',
            'temp' => 24.0,
            'feels_like' => 24.0,
            'description' => 'Données indisponibles',
            'icon' => '01d',
            'humidity' => 0,
            'wind' => 0.0,
            'main' => 'Clear',
        ];
    }

    private function newsFallback(string $topic): array
    {
        return [
            [
                'title' => 'Actualités indisponibles',
                'description' => 'Le service d\'actualités est momentanément indisponible. Vérifiez votre configuration NEWS_API_KEY.',
                'url' => '#',
                'image' => null,
                'source' => 'HrFlow',
                'published_at' => date('c'),
                'formatted_date' => 'à l\'instant',
            ],
        ];
    }

    private function quoteFallback(string $tag): array
    {
        $fallbacks = [
            'motivational' => ['content' => 'Le succès est la somme de petits efforts répétés jour après jour.', 'author' => 'Robert Collier'],
            'wisdom' => ['content' => 'La connaissance s\'acquiert par l\'expérience, tout le reste n\'est que de l\'information.', 'author' => 'Albert Einstein'],
            'leadership' => ['content' => 'Un leader est celui qui connaît le chemin, qui suit le chemin et qui montre le chemin.', 'author' => 'John C. Maxwell'],
            'business' => ['content' => 'Le seul endroit où le succès vient avant le travail, c\'est dans le dictionnaire.', 'author' => 'Vidal Sassoon'],
        ];
        $q = $fallbacks[$tag] ?? $fallbacks['motivational'];
        return ['content' => $q['content'], 'author' => $q['author'], 'tags' => [$tag]];
    }

    private function adviceFallback(): string
    {
        $list = [
            'Prenez 5 minutes pour une pause respiration profonde entre deux tâches.',
            'Rédigez vos 3 priorités du jour avant d\'ouvrir vos emails.',
            'Buvez un grand verre d\'eau dès le réveil.',
            'Planifiez une vraie pause déjeuner, loin de votre écran.',
        ];
        return $list[array_rand($list)];
    }

    private function formatRelativeDateFr(string $isoDate): string
    {
        if (!$isoDate) {
            return '';
        }
        try {
            $dt = new \DateTimeImmutable($isoDate);
            $now = new \DateTimeImmutable();
            $diff = $now->getTimestamp() - $dt->getTimestamp();

            if ($diff < 60) return 'à l\'instant';
            if ($diff < 3600) return 'il y a ' . floor($diff / 60) . ' min';
            if ($diff < 86400) return 'il y a ' . floor($diff / 3600) . ' h';
            if ($diff < 604800) return 'il y a ' . floor($diff / 86400) . ' j';
            return $dt->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }
}
