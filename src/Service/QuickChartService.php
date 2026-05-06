<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * QuickChart.io Chart Generation Service
 * 
 * Provides chart generation using QuickChart.io API
 * @see https://quickchart.io/documentation/
 */
class QuickChartService
{
    private const BASE_URL = 'https://quickchart.io';

    /** @var array<mixed> */
    private array $defaultConfig;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private CacheInterface $cache, // @phpstan-ignore property.onlyWritten
        private bool $useJsLibrary = false // @phpstan-ignore property.onlyWritten
    ) {
        $this->defaultConfig = [
            'width' => 600,
            'height' => 400,
            'devicePixelRatio' => 2.0,
            'backgroundColor' => 'white',
            'format' => 'png',
        ];
    }

    /**
     * ============ CHART URL GENERATION ============
     */

    /**
     * Generate a chart URL from Chart.js configuration
     * 
     * @param array<mixed> $chartConfig Chart.js configuration object
     * @param array<mixed> $options Additional options (width, height, format, etc.)
     * @return string The QuickChart URL
     */
    public function getChartUrl(array $chartConfig, array $options = []): string
    {
        $config = array_merge($this->defaultConfig, $options);
        
        $payload = [
            'chart' => json_encode($chartConfig),
            'width' => $config['width'],
            'height' => $config['height'],
            'devicePixelRatio' => $config['devicePixelRatio'],
            'backgroundColor' => $config['backgroundColor'],
            'format' => $config['format'],
        ];

        // For URLs, we use the /chart endpoint with GET
        $chartConfigEncoded = urlencode((string) json_encode($chartConfig));
        
        $url = self::BASE_URL . '/chart?' . http_build_query([
            'c' => json_encode($chartConfig),
            'w' => $config['width'],
            'h' => $config['height'],
            'devicePixelRatio' => $config['devicePixelRatio'],
            'bkg' => $config['backgroundColor'],
            'f' => $config['format'],
        ]);

        return $url;
    }

    /**
     * Generate a simple chart URL with minimal configuration
     * 
     * @param string $type Chart type: 'bar', 'line', 'pie', 'doughnut', 'radar', 'polarArea', 'bubble', 'scatter'
     * @param array<mixed> $data Array of data values
     * @param array<mixed> $labels Array of labels
     * @param array<mixed> $options Additional options
     * @return string The QuickChart URL
     */
    public function getSimpleChartUrl(string $type, array $data, array $labels = [], array $options = []): string
    {
        $datasets = [
            [
                'label' => $options['datasetLabel'] ?? 'Data',
                'data' => $data,
                'backgroundColor' => $options['backgroundColor'] ?? $this->getDefaultColors(),
                'borderColor' => $options['borderColor'] ?? $this->getDefaultBorderColors(),
                'borderWidth' => $options['borderWidth'] ?? 1,
                'fill' => $options['fill'] ?? false,
            ],
        ];

        if ($type === 'pie' || $type === 'doughnut' || $type === 'polarArea') {
            $datasets[0]['backgroundColor'] = $this->getDefaultColors();
        }

        $chartConfig = [
            'type' => $type,
            'data' => [
                'labels' => $labels,
                'datasets' => $datasets,
            ],
            'options' => [
                'plugins' => [
                    'title' => [
                        'display' => isset($options['title']),
                        'text' => $options['title'] ?? '',
                    ],
                    'legend' => [
                        'display' => $options['showLegend'] ?? true,
                    ],
                ],
            ],
        ];

        // Add scales for cartesian charts
        if (in_array($type, ['bar', 'line', 'scatter', 'bubble'])) {
            $chartConfig['options']['scales'] = [
                'y' => [
                    'beginAtZero' => $options['beginAtZero'] ?? true,
                ],
            ];
        }

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * Generate chart image as binary data
     * 
     * @param array<mixed> $chartConfig Chart.js configuration
     * @param array<mixed> $options Additional options
     * @return string Binary image data
     */
    public function getChartImage(array $chartConfig, array $options = []): string
    {
        $config = array_merge($this->defaultConfig, $options);
        
        $url = self::BASE_URL . '/chart';

        $this->logger->debug('QuickChart image request', [
            'width' => $config['width'],
            'height' => $config['height'],
            'format' => $config['format'],
        ]);

        $response = $this->httpClient->request('POST', $url, [
            'json' => [
                'chart' => json_encode($chartConfig),
                'width' => $config['width'],
                'height' => $config['height'],
                'devicePixelRatio' => $config['devicePixelRatio'],
                'backgroundColor' => $config['backgroundColor'],
                'format' => $config['format'],
            ],
        ]);

        return $response->getContent(false);
    }

    /**
     * ============ SHORT URL GENERATION ============
     */

    /**
     * Create a short URL for a chart
     * 
     * @param array<mixed> $chartConfig Chart.js configuration
     * @param array<mixed> $options Additional options
     * @return string Short URL
     */
    public function createShortUrl(array $chartConfig, array $options = []): string
    {
        $config = array_merge($this->defaultConfig, $options);
        
        $url = self::BASE_URL . '/chart/create';

        $this->logger->info('QuickChart short URL request');

        $response = $this->httpClient->request('POST', $url, [
            'json' => [
                'chart' => json_encode($chartConfig),
                'width' => $config['width'],
                'height' => $config['height'],
                'devicePixelRatio' => $config['devicePixelRatio'],
                'backgroundColor' => $config['backgroundColor'],
            ],
        ]);

        $data = $response->toArray(false);
        
        return $data['url'] ?? throw new \RuntimeException('Failed to create short URL');
    }

    /**
     * ============ PRE-BUILT CHART TYPES ============
     */

    /**
     * Create a bar chart
     * 
     * @param array<mixed> $labels X-axis labels
     * @param array<mixed> $datasets Array of datasets with 'label', 'data', and optional styling
     * @param array<mixed> $options Chart options
     * @return string Chart URL
     */
    public function createBarChart(array $labels, array $datasets, array $options = []): string
    {
        $chartConfig = [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => $this->normalizeDatasets($datasets, $options),
            ],
            'options' => $this->buildChartOptions($options, true),
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * Create a horizontal bar chart
     * @param array<mixed> $labels
     * @param array<mixed> $datasets
     * @param array<mixed> $options
     */
    public function createHorizontalBarChart(array $labels, array $datasets, array $options = []): string
    {
        $chartConfig = [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => $this->normalizeDatasets($datasets, $options),
            ],
            'options' => array_merge(
                $this->buildChartOptions($options, true),
                [
                    'indexAxis' => 'y',
                ]
            ),
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * Create a line chart
     * 
     * @param array<mixed> $labels X-axis labels
     * @param array<mixed> $datasets Array of datasets
     * @param array<mixed> $options Chart options including 'tension' for curve smoothing
     * @return string Chart URL
     */
    public function createLineChart(array $labels, array $datasets, array $options = []): string
    {
        $normalizedDatasets = $this->normalizeDatasets($datasets, $options);
        
        // Apply tension for smoothing if specified
        if (isset($options['tension'])) {
            foreach ($normalizedDatasets as &$dataset) {
                $dataset['tension'] = $options['tension'];
            }
        }

        $chartConfig = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => $normalizedDatasets,
            ],
            'options' => $this->buildChartOptions($options, true),
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * Create a pie chart
     * 
     * @param array<mixed> $labels Data labels
     * @param array<mixed> $data Data values
     * @param array<mixed> $options Chart options
     * @return string Chart URL
     */
    public function createPieChart(array $labels, array $data, array $options = []): string
    {
        $colors = $options['colors'] ?? $this->getDefaultColors(count($data));

        $chartConfig = [
            'type' => 'pie',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'data' => $data,
                        'backgroundColor' => $colors,
                        'borderColor' => $options['borderColor'] ?? '#ffffff',
                        'borderWidth' => $options['borderWidth'] ?? 2,
                    ],
                ],
            ],
            'options' => $this->buildChartOptions($options, false),
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * Create a doughnut chart
     * @param array<mixed> $labels
     * @param array<mixed> $data
     * @param array<mixed> $options
     */
    public function createDoughnutChart(array $labels, array $data, array $options = []): string
    {
        $colors = $options['colors'] ?? $this->getDefaultColors(count($data));

        $chartConfig = [
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'data' => $data,
                        'backgroundColor' => $colors,
                        'borderColor' => $options['borderColor'] ?? '#ffffff',
                        'borderWidth' => $options['borderWidth'] ?? 2,
                    ],
                ],
            ],
            'options' => array_merge(
                $this->buildChartOptions($options, false),
                [
                    'cutout' => $options['cutout'] ?? '50%',
                ]
            ),
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * Create a radar chart
     * @param array<mixed> $labels
     * @param array<mixed> $datasets
     * @param array<mixed> $options
     */
    public function createRadarChart(array $labels, array $datasets, array $options = []): string
    {
        $chartConfig = [
            'type' => 'radar',
            'data' => [
                'labels' => $labels,
                'datasets' => $this->normalizeDatasets($datasets, $options),
            ],
            'options' => $this->buildChartOptions($options, false),
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * Create a polar area chart
     * @param array<mixed> $labels
     * @param array<mixed> $data
     * @param array<mixed> $options
     */
    public function createPolarAreaChart(array $labels, array $data, array $options = []): string
    {
        $colors = $options['colors'] ?? $this->getDefaultColors(count($data));

        $chartConfig = [
            'type' => 'polarArea',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'data' => $data,
                        'backgroundColor' => $colors,
                        'borderColor' => $options['borderColor'] ?? '#ffffff',
                        'borderWidth' => $options['borderWidth'] ?? 2,
                    ],
                ],
            ],
            'options' => $this->buildChartOptions($options, false),
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * Create a scatter plot
     * @param array<mixed> $datasets
     * @param array<mixed> $options
     */
    public function createScatterChart(array $datasets, array $options = []): string
    {
        $chartConfig = [
            'type' => 'scatter',
            'data' => [
                'datasets' => $this->normalizeScatterDatasets($datasets),
            ],
            'options' => $this->buildChartOptions($options, true),
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * Create a bubble chart
     * @param array<mixed> $datasets
     * @param array<mixed> $options
     */
    public function createBubbleChart(array $datasets, array $options = []): string
    {
        $chartConfig = [
            'type' => 'bubble',
            'data' => [
                'datasets' => $this->normalizeBubbleDatasets($datasets),
            ],
            'options' => $this->buildChartOptions($options, true),
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * ============ SPECIALIZED CHARTS ============
     */

    /**
     * Create a gauge chart (using doughnut)
     * @param array<mixed> $options
     */
    public function createGaugeChart(float $value, float $max = 100, array $options = []): string
    {
        $labels = $options['labels'] ?? ['Value', 'Remaining'];
        $colors = $options['colors'] ?? [$this->getColorForValue($value, $max), '#e5e7eb'];

        $chartConfig = [
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'data' => [$value, $max - $value],
                        'backgroundColor' => $colors,
                        'borderWidth' => 0,
                    ],
                ],
            ],
            'options' => [
                'rotation' => -90,
                'circumference' => 180,
                'cutout' => '75%',
                'plugins' => [
                    'title' => [
                        'display' => isset($options['title']),
                        'text' => $options['title'] ?? '',
                    ],
                    'legend' => [
                        'display' => false,
                    ],
                    'datalabels' => [
                        'display' => true,
                        'formatter' => function($value, $context) {
                            return $context->dataIndex === 0 ? $value . '%' : '';
                        },
                    ],
                ],
            ],
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * Create a stacked bar chart
     * @param array<mixed> $labels
     * @param array<mixed> $datasets
     * @param array<mixed> $options
     */
    public function createStackedBarChart(array $labels, array $datasets, array $options = []): string
    {
        $normalizedDatasets = $this->normalizeDatasets($datasets, $options);
        
        // Set stacked option for all datasets
        foreach ($normalizedDatasets as &$dataset) {
            $dataset['stack'] = $options['stack'] ?? 'stack1';
        }

        $chartConfig = [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => $normalizedDatasets,
            ],
            'options' => [
                'scales' => [
                    'x' => [
                        'stacked' => true,
                    ],
                    'y' => [
                        'stacked' => true,
                        'beginAtZero' => $options['beginAtZero'] ?? true,
                    ],
                ],
                'plugins' => [
                    'title' => [
                        'display' => isset($options['title']),
                        'text' => $options['title'] ?? '',
                    ],
                    'legend' => [
                        'display' => $options['showLegend'] ?? true,
                    ],
                ],
            ],
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * Create an area chart (filled line chart)
     * @param array<mixed> $labels
     * @param array<mixed> $datasets
     * @param array<mixed> $options
     */
    public function createAreaChart(array $labels, array $datasets, array $options = []): string
    {
        $normalizedDatasets = $this->normalizeDatasets($datasets, $options);
        
        foreach ($normalizedDatasets as &$dataset) {
            $dataset['fill'] = true;
            $dataset['tension'] = $options['tension'] ?? 0.4;
        }

        $chartConfig = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => $normalizedDatasets,
            ],
            'options' => $this->buildChartOptions($options, true),
        ];

        return $this->getChartUrl($chartConfig, $options);
    }

    /**
     * ============ GRAPHVIZ/GRAPH CHARTS ============
     */

    /**
     * Create a graph/chart using Graphviz notation
     * 
     * @param string $graph Graphviz DOT notation
     * @param array<mixed> $options Layout options
     * @return string Chart URL
     */
    public function createGraphChart(string $graph, array $options = []): string
    {
        $config = array_merge([
            'layout' => $options['layout'] ?? 'dot',
            'width' => $options['width'] ?? 600,
            'height' => $options['height'] ?? 400,
        ], $options);

        $url = self::BASE_URL . '/graphviz?' . http_build_query([
            'graph' => $graph,
            'layout' => $config['layout'],
            'width' => $config['width'],
            'height' => $config['height'],
        ]);

        return $url;
    }

    /**
     * ============ UTILITY METHODS ============
     */

    /**
     * Generate QR code URL for a chart
     * 
     * @param array<mixed> $chartConfig Chart.js configuration
     * @param array<mixed> $options QR code options
     * @return string QR code image URL
     */
    public function getChartQrCode(array $chartConfig, array $options = []): string
    {
        $chartUrl = $this->getChartUrl($chartConfig, array_merge($options, ['format' => 'png']));
        
        return 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($chartUrl);
    }

    /**
     * Validate Chart.js configuration
     * @param array<mixed> $config
     */
    public function validateConfig(array $config): bool
    {
        if (!isset($config['type'])) {
            return false;
        }

        $validTypes = ['bar', 'line', 'pie', 'doughnut', 'radar', 'polarArea', 'bubble', 'scatter'];
        if (!in_array($config['type'], $validTypes)) {
            return false;
        }

        if (!isset($config['data']['datasets']) || !is_array($config['data']['datasets'])) {
            return false;
        }

        return true;
    }

    /**
     * ============ PRIVATE HELPERS ============
     */

    /** @param array<mixed> $datasets @param array<mixed> $options @return array<mixed> */
    private function normalizeDatasets(array $datasets, array $options): array
    {
        $defaultColors = $this->getDefaultColors();
        $normalized = [];

        foreach ($datasets as $index => $dataset) {
            $normalized[] = [
                'label' => $dataset['label'] ?? ('Dataset ' . ($index + 1)),
                'data' => $dataset['data'] ?? [],
                'backgroundColor' => $dataset['backgroundColor'] ?? ($defaultColors[$index % count($defaultColors)]),
                'borderColor' => $dataset['borderColor'] ?? $this->getDefaultBorderColors()[$index % count($defaultColors)],
                'borderWidth' => $dataset['borderWidth'] ?? 2,
                'fill' => $dataset['fill'] ?? false,
            ];
        }

        return $normalized;
    }

    /** @param array<mixed> $datasets @return array<mixed> */
    private function normalizeScatterDatasets(array $datasets): array
    {
        $normalized = [];

        foreach ($datasets as $index => $dataset) {
            $normalized[] = [
                'label' => $dataset['label'] ?? ('Dataset ' . ($index + 1)),
                'data' => array_map(function($point) {
                    return ['x' => $point['x'] ?? $point[0], 'y' => $point['y'] ?? $point[1]];
                }, $dataset['data'] ?? []),
                'backgroundColor' => $dataset['backgroundColor'] ?? $this->getDefaultColors()[$index % 10],
            ];
        }

        return $normalized;
    }

    /** @param array<mixed> $datasets @return array<mixed> */
    private function normalizeBubbleDatasets(array $datasets): array
    {
        $normalized = [];

        foreach ($datasets as $index => $dataset) {
            $normalized[] = [
                'label' => $dataset['label'] ?? ('Dataset ' . ($index + 1)),
                'data' => array_map(function($point) {
                    return [
                        'x' => $point['x'] ?? $point[0],
                        'y' => $point['y'] ?? $point[1],
                        'r' => $point['r'] ?? $point[2] ?? 5,
                    ];
                }, $dataset['data'] ?? []),
                'backgroundColor' => $dataset['backgroundColor'] ?? $this->getDefaultColors()[$index % 10],
            ];
        }

        return $normalized;
    }

    /** @param array<mixed> $options @return array<mixed> */
    private function buildChartOptions(array $options, bool $showScales): array
    {
        $chartOptions = [
            'plugins' => [
                'title' => [
                    'display' => isset($options['title']) && $options['title'] !== '',
                    'text' => $options['title'] ?? '',
                    'font' => [
                        'size' => $options['titleFontSize'] ?? 16,
                    ],
                ],
                'legend' => [
                    'display' => $options['showLegend'] ?? true,
                    'position' => $options['legendPosition'] ?? 'top',
                ],
                'tooltip' => [
                    'enabled' => $options['showTooltips'] ?? true,
                ],
            ],
            'animation' => [
                'duration' => $options['animationDuration'] ?? 1000,
            ],
        ];

        if ($showScales) {
            $chartOptions['scales'] = [
                'x' => [
                    'display' => $options['showXAxis'] ?? true,
                    'title' => [
                        'display' => isset($options['xAxisTitle']),
                        'text' => $options['xAxisTitle'] ?? '',
                    ],
                ],
                'y' => [
                    'display' => $options['showYAxis'] ?? true,
                    'beginAtZero' => $options['beginAtZero'] ?? true,
                    'title' => [
                        'display' => isset($options['yAxisTitle']),
                        'text' => $options['yAxisTitle'] ?? '',
                    ],
                ],
            ];
        }

        return $chartOptions;
    }

    /** @return array<mixed> */
    private function getDefaultColors(int $count = 10): array
    {
        $colors = [
            '#4f46e5', // Indigo
            '#0ea5e9', // Sky
            '#10b981', // Emerald
            '#f59e0b', // Amber
            '#ef4444', // Red
            '#8b5cf6', // Violet
            '#ec4899', // Pink
            '#06b6d4', // Cyan
            '#84cc16', // Lime
            '#f97316', // Orange
        ];

        return array_slice($colors, 0, max($count, 10));
    }

    /** @return array<mixed> */
    private function getDefaultBorderColors(): array
    {
        return [
            '#4338ca',
            '#0284c7',
            '#059669',
            '#d97706',
            '#dc2626',
            '#7c3aed',
            '#db2777',
            '#0891b2',
            '#65a30d',
            '#ea580c',
        ];
    }

    private function getColorForValue(float $value, float $max): string
    {
        $percentage = ($value / $max) * 100;

        if ($percentage < 30) {
            return '#ef4444'; // Red
        } elseif ($percentage < 70) {
            return '#f59e0b'; // Amber
        } else {
            return '#10b981'; // Green
        }
    }

    /**
     * Create a word cloud (if supported by QuickChart)
     * 
     * @param array<mixed> $words Array of ['text' => string, 'size' => int]
     * @param array<mixed> $options Options for the word cloud
     * @return string Chart URL
     */
    public function createWordCloud(array $words, array $options = []): string
    {
        // QuickChart doesn't natively support word clouds, 
        // but we can simulate it with a custom chart configuration
        // This is a placeholder for a proper implementation
        
        $this->logger->warning('Word cloud not natively supported by QuickChart, using fallback');
        
        // Fallback to a bar chart showing word frequencies
        $labels = array_column($words, 'text');
        $data = array_column($words, 'size');
        
        return $this->createBarChart($labels, [
            [
                'label' => $options['datasetLabel'] ?? 'Word Frequency',
                'data' => $data,
            ],
        ], array_merge($options, [
            'title' => $options['title'] ?? 'Word Frequency',
        ]));
    }

    /**
     * Create a sparkline chart (mini chart)
     * @param array<mixed> $data
     * @param array<mixed> $options
     */
    public function createSparkline(array $data, array $options = []): string
    {
        $chartConfig = [
            'type' => 'line',
            'data' => [
                'datasets' => [
                    [
                        'data' => $data,
                        'borderColor' => $options['color'] ?? '#4f46e5',
                        'borderWidth' => $options['lineWidth'] ?? 2,
                        'pointRadius' => 0,
                        'fill' => $options['fill'] ?? false,
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'legend' => [
                        'display' => false,
                    ],
                    'tooltip' => [
                        'enabled' => $options['showTooltips'] ?? false,
                    ],
                ],
                'scales' => [
                    'x' => [
                        'display' => false,
                    ],
                    'y' => [
                        'display' => false,
                    ],
                ],
            ],
        ];

        $sparkOptions = array_merge([
            'width' => 100,
            'height' => 30,
        ], $options);

        return $this->getChartUrl($chartConfig, $sparkOptions);
    }

    /**
     * Create a multi-axis chart
     * @param array<mixed> $labels
     * @param array<mixed> $datasets
     * @param array<mixed> $options
     */
    public function createMultiAxisChart(array $labels, array $datasets, array $options = []): string
    {
        $normalizedDatasets = [];
        
        foreach ($datasets as $index => $dataset) {
            $normalizedDatasets[] = [
                'label' => $dataset['label'] ?? ('Dataset ' . ($index + 1)),
                'data' => $dataset['data'] ?? [],
                'type' => $dataset['type'] ?? 'line',
                'yAxisID' => $dataset['yAxisID'] ?? 'y',
                'backgroundColor' => $dataset['backgroundColor'] ?? $this->getDefaultColors()[$index % 10],
                'borderColor' => $dataset['borderColor'] ?? $this->getDefaultBorderColors()[$index % 10],
                'borderWidth' => $dataset['borderWidth'] ?? 2,
                'fill' => $dataset['fill'] ?? false,
            ];
        }

        $chartConfig = [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => $normalizedDatasets,
            ],
            'options' => [
                'scales' => [
                    'y' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'left',
                    ],
                    'y1' => [
                        'type' => 'linear',
                        'display' => true,
                        'position' => 'right',
                        'grid' => [
                            'drawOnChartArea' => false,
                        ],
                    ],
                ],
                'plugins' => [
                    'title' => [
                        'display' => isset($options['title']),
                        'text' => $options['title'] ?? '',
                    ],
                    'legend' => [
                        'display' => $options['showLegend'] ?? true,
                    ],
                ],
            ],
        ];

        return $this->getChartUrl($chartConfig, $options);
    }
}
