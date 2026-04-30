# Skill: Advanced Reporting & Analytics (Step-by-Step)

Follow these steps to fulfill a reporting or visualization request:

## Step 1: Data Retrieval
Identify what data you need to answer the user's request.
- Use `get_job_offers` to list available jobs if the user hasn't specified one.
- Use `get_applications` or `get_candidates` to get raw records.
- Use `get_interviews` for interview-related stats.

## Step 2: Data Aggregation
The tools return raw lists. You MUST aggregate this data yourself:
- **For Charts**: Count the number of items per category (e.g., "5 Pending, 3 Interview").
- **For Tables**: Extract only the relevant columns for the final report.

## Step 3: Rendering
Once you have the numbers or the formatted text:
- **Visual Charts**: Call `render_chart` with your aggregated `labels` and `values`. Choose appropriate design options based on user intent.
- **Custom PDFs**: Call `render_pdf`. Construct a professional HTML/Text body. Use `layout_style` parameter to match the user's preference.

## Step 4: Final Response
Confirm what you have generated and provide context.
- Example: "J'ai analysé vos 15 candidatures récentes. Voici la répartition par statut dans le graphique ci-dessous."

## Chart Design Guidelines

### Chart Type Selection
- **pie/doughnut**: Best for percentage distribution (status breakdown, categorical splits).
- **bar**: Best for comparing quantities across categories. Use `horizontalBar` for long labels like job titles.
- **line**: Best for trends over time (volume by month, week).
- **radar**: Best for comparing multiple variables (candidate skills assessment).

### Color Schemes (color_scheme parameter)
- **hrflow**: Teal/purple corporate colors - default for HRFlow branding.
- **modern**: Blue/indigo gradient - for contemporary dashboards.
- **warm**: Orange/red tones - for urgency, alerts, attention-grabbing stats.
- **professional**: Gray/blue business style - for executive reports.
- **custom**: Use with `colors` array for specific brand requirements.

### Legend & Layout
- Use `legend_position: 'none'` for simple charts where values are self-explanatory.
- Use `legend_position: 'right'` for pie charts to save vertical space.
- Set `title` parameter to describe what the chart shows.

## PDF Design Guidelines

### Layout Styles (layout_style parameter)
- **corporate**: Clean, professional with header/footer - default for business documents.
- **modern**: Cards-based with shadows, rounded corners - for contemporary presentations.
- **minimal**: Simple, text-focused - for quick summaries and notes.
- **detailed**: Multi-section with stats grid, comprehensive - for executive reports.

### Content Structure with Sections
Instead of single `content` block, use `sections` array for better structure:
```json
{
  "sections": [
    {"title": "Résumé", "content": "<p>Les points clés...</p>", "type": "text"},
    {"title": "Statistiques", "content": "...", "type": "stats"},
    {"title": "Détails", "content": "<table>...</table>", "type": "table"}
  ]
}
```

### Stats Parameter
Use `stats` array to display key metrics at the top of the document:
```json
{
  "stats": [
    {"label": "Candidatures", "value": "42", "color": "teal"},
    {"label": "Entretiens", "value": "15", "color": "purple"}
  ]
}
```

### HTML Styling Tips
- Use `<table>` with proper headers for structured data.
- Use `<div class="highlight">` for key observations.
- Use `<ul>/<li>` for bullet points and action items.
- Bold (`<b>`) candidate names, job titles, and key numbers.
- Include color-coded status: green=success, yellow=pending, red=rejected.
