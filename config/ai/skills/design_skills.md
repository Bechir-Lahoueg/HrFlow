# Skill: Visual Design & Layout Guidelines

## Chart Design Principles

When creating charts, consider these design options:

### Color Schemes (pass as `color_scheme` parameter)
- `hrflow` - Teal/Purple corporate colors (#14b8a6, #7c3aed, #f59e0b)
- `modern` - Blue/Indigo gradient style (#3b82f6, #6366f1, #8b5cf6)
- `warm` - Orange/Red warm tones (#f97316, #ef4444, #f59e0b)
- `professional` - Gray/Blue business style (#64748b, #3b82f6, #10b981)
- `custom` - Use custom colors array provided by user

### Chart Types & Best Use
- `bar` - Best for comparing quantities across categories
- `horizontalBar` - Best when labels are long (job titles, names)
- `pie` - Best for percentage distribution (status breakdown)
- `doughnut` - Modern alternative to pie, better for multiple small segments
- `line` - Best for trends over time
- `radar` - Best for comparing multiple variables (candidate skills)

### Chart Options (pass as `options` object)
- `responsive: true` - Always include
- `maintainAspectRatio: false` - For custom height control
- `plugins.legend.position` - 'bottom', 'top', 'left', 'right'
- `plugins.title.display: true` - Show chart title
- `scales.y.beginAtZero: true` - For bar charts

## PDF Design Principles

When generating PDFs, consider these layout options:

### Layout Styles (pass as `layout_style` parameter)
- `corporate` - Clean, professional with header/footer, border accents
- `modern` - Cards-based layout, rounded corners, soft shadows
- `minimal` - Simple, text-focused, minimal decoration
- `detailed` - Multi-section with tables, stats grid, comprehensive

### Content Structure Tips
- Use HTML tables for structured data (candidates, jobs)
- Use bullet lists for observations and insights
- Use `<div class="highlight">` for key statistics
- Include candidate/job names in bold
- Add color-coded status badges (green=success, yellow=pending, red=rejected)

### PDF Sections to Include Based on Content Type
For candidate reports:
- Summary card with photo placeholder, name, contact info
- Skills assessment table
- Interview history timeline
- Recruiter notes section

For pipeline reports:
- Stats grid (total, hired, in-progress, rejected)
- Status distribution chart image or table
- Recent applications table
- Action items list

For job offer reports:
- Job details header (title, department, location)
- Application funnel visualization
- Candidate comparison table
- Timeline of recruitment activities
