<?php

namespace App\Service\AI\Tool\Reporting;

use App\Service\AI\Tool\ToolInterface;

class RenderChartTool implements ToolInterface
{
    public function getName(): string
    {
        return 'render_chart';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'render_chart',
            'description' => 'Renders a chart in the chat interface using data you have retrieved and processed. Supports flexible design options including color schemes, chart types, and visualization preferences based on user intent.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'type' => [
                        'type' => 'string',
                        'enum' => ['bar', 'horizontalBar', 'pie', 'line', 'doughnut', 'radar'],
                        'description' => 'The type of chart. Use horizontalBar for long labels (job titles), pie/doughnut for status distribution, line for time trends, radar for skill comparisons'
                    ],
                    'labels' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Labels for the X-axis or slices'
                    ],
                    'values' => [
                        'type' => 'array',
                        'items' => ['type' => 'number'],
                        'description' => 'Numerical values for each label'
                    ],
                    'label' => [
                        'type' => 'string',
                        'description' => 'The dataset label (e.g. "Candidatures par mois")'
                    ],
                    'title' => [
                        'type' => 'string',
                        'description' => 'Optional title displayed above the chart'
                    ],
                    'color_scheme' => [
                        'type' => 'string',
                        'enum' => ['hrflow', 'modern', 'warm', 'professional', 'custom'],
                        'description' => 'Color palette: hrflow=teal/purple (default), modern=blue/indigo, warm=orange/red, professional=gray/blue, custom=use colors parameter'
                    ],
                    'colors' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Custom hex colors array (e.g., ["#ff0000", "#00ff00"]). Only used when color_scheme=custom'
                    ],
                    'legend_position' => [
                        'type' => 'string',
                        'enum' => ['bottom', 'top', 'left', 'right', 'none'],
                        'description' => 'Position of the legend. Use none to hide legend'
                    ],
                    'height' => [
                        'type' => 'integer',
                        'description' => 'Chart height in pixels (default: 240)',
                        'default' => 240
                    ],
                    'stacked' => [
                        'type' => 'boolean',
                        'description' => 'For bar charts: stack multiple datasets on top of each other',
                        'default' => false
                    ],
                    'show_values' => [
                        'type' => 'boolean',
                        'description' => 'Display values on chart segments/bars',
                        'default' => true
                    ]
                ],
                'required' => ['type', 'labels', 'values', 'label'],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $colorScheme = $args['color_scheme'] ?? 'hrflow';
        $customColors = $args['colors'] ?? null;
        $chartType = $args['type'];
        
        // Build chart options based on user preferences
        $options = [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => ($args['legend_position'] ?? 'bottom') !== 'none',
                    'position' => ($args['legend_position'] ?? 'bottom') === 'none' ? 'bottom' : ($args['legend_position'] ?? 'bottom'),
                    'labels' => [
                        'boxWidth' => 12,
                        'font' => ['family' => 'DM Sans', 'size' => 11]
                    ]
                ],
                'title' => [
                    'display' => !empty($args['title']),
                    'text' => $args['title'] ?? '',
                    'font' => ['family' => 'DM Sans', 'size' => 14, 'weight' => 'bold']
                ]
            ]
        ];

        // Add scales for bar/line charts
        if (in_array($chartType, ['bar', 'horizontalBar', 'line'])) {
            $options['scales'] = [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(0,0,0,0.05)']
                ],
                'x' => [
                    'grid' => ['display' => false]
                ]
            ];
            
            if ($args['stacked'] ?? false) {
                $options['scales']['x']['stacked'] = true;
                $options['scales']['y']['stacked'] = true;
            }
        }

        // Get colors based on scheme
        $colors = $this->getColors(count($args['labels']), $chartType, $colorScheme, $customColors);

        return [
            'status' => 'success',
            'chart_data' => [
                'type' => $chartType,
                'labels' => $args['labels'],
                'datasets' => [[
                    'label' => $args['label'],
                    'data' => $args['values'],
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                    'borderWidth' => 1
                ]],
                'options' => $options,
                'height' => $args['height'] ?? 240
            ],
            'title' => $args['title'] ?? null,
            'message' => "Graphique généré avec succès (style: {$colorScheme})."
        ];
    }

    private function getColors(int $count, string $chartType, string $scheme, ?array $customColors): array
    {
        // Use custom colors if provided
        if ($customColors && count($customColors) > 0) {
            $colors = [];
            for ($i = 0; $i < $count; $i++) {
                $colors[] = $customColors[$i % count($customColors)];
            }
            return $colors;
        }

        // Color schemes
        $schemes = [
            'hrflow' => ['#14b8a6', '#7c3aed', '#f59e0b', '#ef4444', '#3b82f6', '#10b981', '#ec4899', '#6366f1'],
            'modern' => ['#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#f43f5e'],
            'warm' => ['#f97316', '#ef4444', '#f59e0b', '#eab308', '#84cc16', '#22c55e'],
            'professional' => ['#64748b', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
        ];

        $palette = $schemes[$scheme] ?? $schemes['hrflow'];

        // For bar and line charts, use single color with opacity variations
        if ($chartType === 'bar' || $chartType === 'horizontalBar' || $chartType === 'line') {
            $baseColor = $palette[0];
            // Return same color for all bars (modern look)
            return array_fill(0, $count, $baseColor);
        }

        // For pie/doughnut/radar, use multiple colors
        $colors = [];
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $palette[$i % count($palette)];
        }
        return $colors;
    }
}
