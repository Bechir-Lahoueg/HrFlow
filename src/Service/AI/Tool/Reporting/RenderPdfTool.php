<?php

namespace App\Service\AI\Tool\Reporting;

use App\Service\AI\ReportGeneratorService;
use App\Service\AI\Tool\ToolInterface;
use Symfony\Bundle\SecurityBundle\Security;

class RenderPdfTool implements ToolInterface
{
    public function __construct(
        private ReportGeneratorService $reportGenerator,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'render_pdf';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'render_pdf',
            'description' => 'Generates a professional PDF document with flexible design options. Supports multiple layout styles, sections, and visual customization based on user requirements and content type.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'title' => [
                        'type' => 'string',
                        'description' => 'The main title of the document displayed in header'
                    ],
                    'subtitle' => [
                        'type' => 'string',
                        'description' => 'Optional subtitle or description'
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Main content as HTML. Use <table>, <ul>, <div class="stat-box">, <div class="highlight"> for styling'
                    ],
                    'layout_style' => [
                        'type' => 'string',
                        'enum' => ['corporate', 'modern', 'minimal', 'detailed'],
                        'description' => 'Visual style: corporate=clean professional, modern=cards with shadows, minimal=simple text, detailed=comprehensive multi-section'
                    ],
                    'sections' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string', 'description' => 'Section header'],
                                'content' => ['type' => 'string', 'description' => 'Section HTML content'],
                                'type' => ['type' => 'string', 'enum' => ['text', 'table', 'stats', 'highlight'], 'description' => 'Visual treatment for section']
                            ]
                        ],
                        'description' => 'Optional: Break content into structured sections instead of single content block'
                    ],
                    'stats' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'label' => ['type' => 'string'],
                                'value' => ['type' => 'string'],
                                'color' => ['type' => 'string', 'enum' => ['teal', 'purple', 'orange', 'red', 'blue'], 'description' => 'Accent color for stat box']
                            ]
                        ],
                        'description' => 'Key statistics to display in a grid at top of document'
                    ],
                    'filename' => [
                        'type' => 'string',
                        'description' => 'Optional filename without extension'
                    ],
                    'include_date' => [
                        'type' => 'boolean',
                        'description' => 'Include generation date in header',
                        'default' => true
                    ],
                    'footer_text' => [
                        'type' => 'string',
                        'description' => 'Custom footer text (default: HrFlow Recruitment System)'
                    ]
                ],
                'required' => ['title', 'content'],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user) return ['error' => 'Non authentifié'];

        $layoutStyle = $args['layout_style'] ?? 'corporate';
        
        // Build structured content from sections if provided
        $structuredContent = $this->buildStructuredContent($args);

        $data = [
            'title' => $args['title'],
            'subtitle' => $args['subtitle'] ?? '',
            'user_name' => method_exists($user, 'getFullName') ? $user->getFullName() : $user->getUserIdentifier(),
            'custom_content' => $structuredContent ?: $args['content'],
            'layout_style' => $layoutStyle,
            'stats' => $args['stats'] ?? [],
            'include_date' => $args['include_date'] ?? true,
            'footer_text' => $args['footer_text'] ?? 'HrFlow Recruitment System',
            // Legacy stats for backward compatibility with template
            'total_applications' => 0,
            'hired_count' => 0,
            'interview_count' => 0,
            'status_stats' => [],
        ];

        // Select template based on layout style
        $template = $this->selectTemplate($layoutStyle);
        $filename = $args['filename'] ?? $this->generateFilename($args['title']);

        $url = $this->reportGenerator->generatePdf($template, $data, $filename);

        return [
            'status' => 'success',
            'download_url' => $url,
            'layout_style' => $layoutStyle,
            'message' => "Document PDF '{$args['title']}' généré avec le style {$layoutStyle}."
        ];
    }

    /** @param array<mixed> $args */
    private function buildStructuredContent(array $args): ?string
    {
        if (empty($args['sections']) || !is_array($args['sections'])) {
            return null;
        }

        $html = '';
        foreach ($args['sections'] as $section) {
            $type = $section['type'] ?? 'text';
            $title = $section['title'] ?? '';
            $content = $section['content'] ?? '';

            switch ($type) {
                case 'stats':
                    $html .= "<div class=\"section section-stats\">\n";
                    $html .= "  <div class=\"section-title\">{$title}</div>\n";
                    $html .= "  <div class=\"stats-grid\">{$content}</div>\n";
                    $html .= "</div>\n";
                    break;
                case 'table':
                    $html .= "<div class=\"section\">\n";
                    $html .= "  <div class=\"section-title\">{$title}</div>\n";
                    $html .= "  {$content}\n";
                    $html .= "</div>\n";
                    break;
                case 'highlight':
                    $html .= "<div class=\"section\">\n";
                    $html .= "  <div class=\"section-title\">{$title}</div>\n";
                    $html .= "  <div class=\"highlight-box\">{$content}</div>\n";
                    $html .= "</div>\n";
                    break;
                default:
                    $html .= "<div class=\"section\">\n";
                    if ($title) {
                        $html .= "  <div class=\"section-title\">{$title}</div>\n";
                    }
                    $html .= "  <div class=\"section-content\">{$content}</div>\n";
                    $html .= "</div>\n";
            }
        }

        return $html;
    }

    private function selectTemplate(string $layoutStyle): string
    {
        $templates = [
            'corporate' => 'ai/reports/pipeline_report.html.twig',
            'modern' => 'ai/reports/modern_report.html.twig',
            'minimal' => 'ai/reports/minimal_report.html.twig',
            'detailed' => 'ai/reports/detailed_report.html.twig',
        ];

        return $templates[$layoutStyle] ?? $templates['corporate'];
    }

    private function generateFilename(string $title): string
    {
        // Transliterate French characters and create safe filename
        $iconvResult = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($title));
        $slug = is_string($iconvResult) ? $iconvResult : $title;
        $slug = (string) preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        return $slug ?: 'custom_report';
    }
}
