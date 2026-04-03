package org.example.service;

import com.lowagie.text.Chunk;
import com.lowagie.text.Document;
import com.lowagie.text.DocumentException;
import com.lowagie.text.Element;
import com.lowagie.text.Font;
import com.lowagie.text.PageSize;
import com.lowagie.text.Paragraph;
import com.lowagie.text.Phrase;
import com.lowagie.text.Rectangle;
import com.lowagie.text.pdf.ColumnText;
import com.lowagie.text.pdf.PdfContentByte;
import com.lowagie.text.pdf.PdfPageEventHelper;
import com.lowagie.text.pdf.PdfPCell;
import com.lowagie.text.pdf.PdfPTable;
import com.lowagie.text.pdf.PdfWriter;
import org.apache.poi.ss.usermodel.*;
import org.apache.poi.ss.usermodel.Cell;
import org.apache.poi.ss.util.CellRangeAddress;
import org.apache.poi.xssf.usermodel.*;
import org.example.model.LeaveRequest;

import java.awt.Color;
import java.io.*;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.*;

/**
 * Service d'export des données de congés en Excel (.xlsx) et PDF.
 *
 * <b>Excel</b> : 4 feuilles → Toutes les demandes · Statistiques · Par employé · Analyse mensuelle
 * <b>PDF</b>   : Rapport professionnel avec en-tête, KPIs, tableaux filtrés par statut.
 */
public class LeaveExportService {

    private static final DateTimeFormatter DATE_FMT = DateTimeFormatter.ofPattern("dd/MM/yyyy");
    private static final DateTimeFormatter DT_FMT   = DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm");

    // ═════════════════════════════════════════════════════════════════════════════
    //  EXCEL EXPORT
    // ═════════════════════════════════════════════════════════════════════════════

    public void exportToExcel(List<LeaveRequest>     requests,
                              LeaveStatisticsService stats,
                              File                   file) throws IOException {

        try (XSSFWorkbook wb = new XSSFWorkbook()) {
            buildRequestsSheet(wb, requests);
            buildStatsSheet(wb, stats);
            buildEmployeeSheet(wb, stats);
            buildMonthlySheet(wb, stats);
            try (FileOutputStream fos = new FileOutputStream(file)) { wb.write(fos); }
        }
    }

    // ─── Feuille 1 : Toutes les demandes ─────────────────────────────────────────

    private void buildRequestsSheet(XSSFWorkbook wb, List<LeaveRequest> requests) {
        XSSFSheet sheet = wb.createSheet("Demandes de congés");
        sheet.setDefaultColumnWidth(18);
        sheet.setColumnWidth(2, 22 * 256);  // employé
        sheet.setColumnWidth(9, 50 * 256);  // raison

        // Titre fusionné
        Row titleRow = sheet.createRow(0);
        Cell title = titleRow.createCell(0);
        title.setCellValue("RAPPORT DES DEMANDES DE CONGÉS  —  Généré le " +
                           LocalDateTime.now().format(DT_FMT));
        title.setCellStyle(titleStyle(wb));
        sheet.addMergedRegion(new CellRangeAddress(0, 0, 0, 9));

        // En-têtes
        String[] headers = {"ID", "Statut", "Employé", "Type de congé",
                            "Début", "Fin", "Jours", "Demandé le",
                            "Commentaire RH", "Raison"};
        Row hRow = sheet.createRow(2);
        XSSFCellStyle hStyle = headerStyle(wb);
        for (int i = 0; i < headers.length; i++) {
            Cell c = hRow.createCell(i);
            c.setCellValue(headers[i]);
            c.setCellStyle(hStyle);
        }

        // Données
        XSSFCellStyle approvedStyle = statusStyle(wb, new Color(198, 239, 206), new Color(0, 97, 0));
        XSSFCellStyle rejectedStyle = statusStyle(wb, new Color(255, 199, 206), new Color(156, 0, 6));
        XSSFCellStyle pendingStyle  = statusStyle(wb, new Color(255, 235, 156), new Color(156, 87, 0));
        XSSFCellStyle dataStyle     = dataStyle(wb);
        XSSFCellStyle dateStyle     = dateStyle(wb);

        int rowIdx = 3;
        for (LeaveRequest req : requests) {
            Row row = sheet.createRow(rowIdx++);
            row.setHeightInPoints(18);

            cell(row, 0, req.getId(),        dataStyle);
            String statusLabel = switch (req.getStatus()) {
                case ACCEPTE -> "✅ Approuvé";
                case REFUSE  -> "❌ Refusé";
                default      -> "⏳ En attente";
            };
            XSSFCellStyle stStyle = switch (req.getStatus()) {
                case ACCEPTE -> approvedStyle;
                case REFUSE  -> rejectedStyle;
                default      -> pendingStyle;
            };
            cell(row, 1, statusLabel,        stStyle);
            cell(row, 2, req.getEmployeeName(), dataStyle);
            cell(row, 3, req.getLeaveType(), dataStyle);
            cell(row, 4, fmtDate(req.getStartDate()), dateStyle);
            cell(row, 5, fmtDate(req.getEndDate()),   dateStyle);
            cell(row, 6, req.getDaysCount(), dataStyle);
            cell(row, 7, fmtDate(req.getRequestDate()), dateStyle);
            cell(row, 8, nvl(req.getRhComment()), dataStyle);
            cell(row, 9, nvl(req.getReason()),   dataStyle);
        }

        // Filtre automatique
        sheet.setAutoFilter(new CellRangeAddress(2, rowIdx - 1, 0, 9));
        // Figer la ligne d'en-têtes
        sheet.createFreezePane(0, 3);

        // Totaux
        Row totalRow = sheet.createRow(rowIdx + 1);
        XSSFCellStyle totalStyle = totalStyle(wb);
        Cell tc = totalRow.createCell(0);
        tc.setCellValue("TOTAL : " + requests.size() + " demande(s)");
        tc.setCellStyle(totalStyle);
        sheet.addMergedRegion(new CellRangeAddress(rowIdx + 1, rowIdx + 1, 0, 6));

        long totalDays = requests.stream()
                .filter(r -> r.getStatus() == LeaveRequest.LeaveStatus.ACCEPTE)
                .mapToLong(LeaveRequest::getDaysCount).sum();
        Cell dc = totalRow.createCell(6);
        dc.setCellValue(totalDays + " j approuvés");
        dc.setCellStyle(totalStyle);
    }

    // ─── Feuille 2 : Statistiques ─────────────────────────────────────────────────

    private void buildStatsSheet(XSSFWorkbook wb, LeaveStatisticsService svc) {
        XSSFSheet sheet = wb.createSheet("Statistiques");
        sheet.setDefaultColumnWidth(28);

        Row titleRow = sheet.createRow(0);
        Cell t = titleRow.createCell(0);
        t.setCellValue("STATISTIQUES GLOBALES");
        t.setCellStyle(titleStyle(wb));
        sheet.addMergedRegion(new CellRangeAddress(0, 0, 0, 3));

        LeaveStatisticsService.GlobalStats g = svc.getGlobalStats();
        int r = 2;
        r = sectionTitle(sheet, wb, r, "📊 KPIs Globaux");
        r = kpiRow(sheet, wb, r, "Total des demandes",         g.total());
        r = kpiRow(sheet, wb, r, "Demandes approuvées",        g.approved());
        r = kpiRow(sheet, wb, r, "Demandes refusées",          g.rejected());
        r = kpiRow(sheet, wb, r, "Demandes en attente",        g.pending());
        r = kpiRow(sheet, wb, r, "Total jours approuvés",      g.totalApprovedDays());
        r = kpiRow(sheet, wb, r, "Durée moyenne (j)",          String.format("%.1f", g.avgApprovedDays()));
        r = kpiRow(sheet, wb, r, "Taux d'approbation",         String.format("%.1f%%", g.approvalRatePct()));
        r = kpiRow(sheet, wb, r, "Employés distincts",         g.uniqueEmployees());
        r++;

        r = sectionTitle(sheet, wb, r, "📋 Demandes par type de congé");
        r = tableHeaders(sheet, wb, r, "Type", "Nb demandes", "Jours approuvés", "Durée moy. (j)");
        Map<String, Long> counts   = svc.countByType();
        Map<String, Long> days     = svc.daysByType();
        Map<String, Double> avgDur = svc.avgDurationByType();
        XSSFCellStyle ds = dataStyle(wb);
        for (String type : counts.keySet()) {
            Row row = sheet.createRow(r++);
            cell(row, 0, type,                                   ds);
            cell(row, 1, counts.getOrDefault(type, 0L),         ds);
            cell(row, 2, days.getOrDefault(type, 0L),           ds);
            cell(row, 3, String.format("%.1f", avgDur.getOrDefault(type, 0.0)), ds);
        }
        r++;

        r = sectionTitle(sheet, wb, r, "📅 Taux d'approbation par mois");
        r = tableHeaders(sheet, wb, r, "Mois", "Taux (%)");
        Map<String, Double> rates = svc.approvalRatePerMonth();
        for (Map.Entry<String, Double> e : rates.entrySet()) {
            Row row = sheet.createRow(r++);
            cell(row, 0, e.getKey(),                              ds);
            cell(row, 1, String.format("%.1f%%", e.getValue()), ds);
        }
    }

    // ─── Feuille 3 : Par employé ─────────────────────────────────────────────────

    private void buildEmployeeSheet(XSSFWorkbook wb, LeaveStatisticsService svc) {
        XSSFSheet sheet = wb.createSheet("Par employé");
        sheet.setDefaultColumnWidth(22);

        Row titleRow = sheet.createRow(0);
        Cell t = titleRow.createCell(0);
        t.setCellValue("ANALYSE PAR EMPLOYÉ");
        t.setCellStyle(titleStyle(wb));
        sheet.addMergedRegion(new CellRangeAddress(0, 0, 0, 5));

        int r = 2;
        r = tableHeaders(sheet, wb, r,
                "Employé", "Total demandes", "Demandes approuvées",
                "Demandes refusées", "Jours approuvés", "Taux appro.");

        XSSFCellStyle ds   = dataStyle(wb);
        XSSFCellStyle top1 = statusStyle(wb, new Color(255, 215, 0), new Color(100, 80, 0));
        XSSFCellStyle top2 = statusStyle(wb, new Color(220, 220, 220), new Color(60, 60, 60));
        XSSFCellStyle top3 = statusStyle(wb, new Color(210, 140, 70), new Color(80, 40, 0));

        List<LeaveStatisticsService.EmployeeStat> empStats = svc.allEmployeeStats();
        int rank = 0;
        for (LeaveStatisticsService.EmployeeStat e : empStats) {
            Row row = sheet.createRow(r++);
            rank++;
            XSSFCellStyle ns = rank == 1 ? top1 : rank == 2 ? top2 : rank == 3 ? top3 : ds;
            cell(row, 0, e.name(),          ns);
            cell(row, 1, e.totalRequests(), ds);
            cell(row, 2, e.approvedCount(), ds);
            cell(row, 3, e.rejectedCount(), ds);
            cell(row, 4, e.approvedDays(),  ds);
            long decided = e.approvedCount() + e.rejectedCount();
            String rate = decided == 0 ? "—" :
                          String.format("%.0f%%", (double) e.approvedCount() / decided * 100);
            cell(row, 5, rate, ds);
        }
        sheet.setAutoFilter(new CellRangeAddress(2, r - 1, 0, 5));
        sheet.createFreezePane(0, 3);
    }

    // ─── Feuille 4 : Analyse mensuelle ───────────────────────────────────────────

    private void buildMonthlySheet(XSSFWorkbook wb, LeaveStatisticsService svc) {
        XSSFSheet sheet = wb.createSheet("Tendances mensuelles");
        sheet.setDefaultColumnWidth(22);

        Row titleRow = sheet.createRow(0);
        Cell t = titleRow.createCell(0);
        t.setCellValue("TENDANCES MENSUELLES (12 DERNIERS MOIS)");
        t.setCellStyle(titleStyle(wb));
        sheet.addMergedRegion(new CellRangeAddress(0, 0, 0, 4));

        int r = 2;
        r = tableHeaders(sheet, wb, r,
                "Mois", "Demandes soumises", "Jours approuvés",
                "Employés absents", "Taux d'approbation");

        Map<String, Long>   submissions = svc.submissionsPerMonth();
        Map<String, Long>   approved    = svc.approvedDaysPerMonth();
        Map<String, Long>   absences    = svc.absenceLoadByMonth();
        Map<String, Double> rates       = svc.approvalRatePerMonth();

        Set<String> months = new LinkedHashSet<>();
        months.addAll(submissions.keySet());
        months.addAll(approved.keySet());

        XSSFCellStyle ds = dataStyle(wb);
        for (String m : months) {
            Row row = sheet.createRow(r++);
            cell(row, 0, m,                                                 ds);
            cell(row, 1, submissions.getOrDefault(m, 0L),                  ds);
            cell(row, 2, approved.getOrDefault(m, 0L),                     ds);
            cell(row, 3, absences.getOrDefault(m, 0L),                     ds);
            cell(row, 4, String.format("%.1f%%", rates.getOrDefault(m, 0.0)), ds);
        }
    }

    // ═════════════════════════════════════════════════════════════════════════════
    //  PDF EXPORT
    // ═════════════════════════════════════════════════════════════════════════════

    public void exportToPdf(List<LeaveRequest>     requests,
                            LeaveStatisticsService stats,
                            File                   file) throws DocumentException, IOException {

        Document doc = new Document(PageSize.A4.rotate(), 30, 30, 40, 30);
        PdfWriter writer = PdfWriter.getInstance(doc, new FileOutputStream(file));
        writer.setPageEvent(new PdfPageEvent());
        doc.open();

        LeaveStatisticsService.GlobalStats g = stats.getGlobalStats();

        // ── Couverture ──────────────────────────────────────────────────────────
        addCoverPage(doc, g);

        // ── KPIs ────────────────────────────────────────────────────────────────
        doc.newPage();
        addSectionTitle(doc, "1.  Tableau de bord global");
        addKpiTable(doc, g);
        doc.add(Chunk.NEWLINE);
        addSectionTitle(doc, "2.  Répartition par type de congé");
        addTypeTable(doc, stats);
        doc.add(Chunk.NEWLINE);
        addSectionTitle(doc, "3.  Analyse mensuelle (12 derniers mois)");
        addMonthlyTable(doc, stats);

        // ── Liste des demandes ──────────────────────────────────────────────────
        doc.newPage();
        addSectionTitle(doc, "4.  Liste complète des demandes (" + requests.size() + ")");
        addRequestsTable(doc, requests);

        // ── Top employés ────────────────────────────────────────────────────────
        doc.newPage();
        addSectionTitle(doc, "5.  Top employés par jours de congé approuvés");
        addEmployeeTable(doc, stats);

        doc.close();
    }

    // ─── PDF helpers ─────────────────────────────────────────────────────────────

    private static final Font TITLE_FONT   = new Font(Font.HELVETICA, 22, Font.BOLD,   new Color(44, 62, 80));
    private static final Font SECTION_FONT = new Font(Font.HELVETICA, 13, Font.BOLD,   new Color(52, 73, 94));
    private static final Font HEADER_FONT  = new Font(Font.HELVETICA,  9, Font.BOLD,   Color.WHITE);
    private static final Font DATA_FONT    = new Font(Font.HELVETICA,  8, Font.NORMAL, new Color(44, 62, 80));
    private static final Font SMALL_FONT   = new Font(Font.HELVETICA,  7, Font.NORMAL, new Color(127, 140, 141));
    private static final Font KPI_LABEL    = new Font(Font.HELVETICA, 10, Font.BOLD,   new Color(52, 73, 94));
    private static final Font KPI_VALUE    = new Font(Font.HELVETICA, 18, Font.BOLD,   new Color(41, 128, 185));

    private static final Color HEADER_BG   = new Color(44,  62,  80);
    private static final Color STRIPE_BG   = new Color(236, 240, 241);
    private static final Color APPROVED_BG = new Color(198, 239, 206);
    private static final Color REJECTED_BG = new Color(255, 199, 206);
    private static final Color PENDING_BG  = new Color(255, 235, 156);

    private void addCoverPage(Document doc, LeaveStatisticsService.GlobalStats g) throws DocumentException {
        doc.add(new Paragraph("\n\n\n"));
        Paragraph title = new Paragraph("RAPPORT DE GESTION DES CONGÉS\n", TITLE_FONT);
        title.setAlignment(Element.ALIGN_CENTER);
        doc.add(title);

        Paragraph sub = new Paragraph(
            "Workforce Platform  ·  Généré le " + LocalDateTime.now().format(DT_FMT),
            new Font(Font.HELVETICA, 11, Font.NORMAL, new Color(127, 140, 141)));
        sub.setAlignment(Element.ALIGN_CENTER);
        doc.add(sub);
        doc.add(new Paragraph("\n\n"));

        // Mini-cards KPI en couverture
        PdfPTable kpis = new PdfPTable(4);
        kpis.setWidthPercentage(90);
        kpis.setHorizontalAlignment(Element.ALIGN_CENTER);
        float[] widths = {1f, 1f, 1f, 1f};
        kpis.setWidths(widths);

        addMiniKpi(kpis, "📋 Total",     String.valueOf(g.total()),            new Color(52, 152, 219));
        addMiniKpi(kpis, "✅ Approuvées", String.valueOf(g.approved()),          new Color(46, 204, 113));
        addMiniKpi(kpis, "❌ Refusées",  String.valueOf(g.rejected()),          new Color(231, 76, 60));
        addMiniKpi(kpis, "⏳ En attente", String.valueOf(g.pending()),           new Color(241, 196, 15));
        doc.add(kpis);
        doc.add(new Paragraph("\n"));

        PdfPTable kpis2 = new PdfPTable(3);
        kpis2.setWidthPercentage(70);
        kpis2.setHorizontalAlignment(Element.ALIGN_CENTER);
        addMiniKpi(kpis2, "📅 Jours approuvés", String.valueOf(g.totalApprovedDays()), new Color(155, 89, 182));
        addMiniKpi(kpis2, "⏱ Durée moy.",       String.format("%.1f j",g.avgApprovedDays()), new Color(52, 73, 94));
        addMiniKpi(kpis2, "📈 Taux appro.",      String.format("%.0f%%", g.approvalRatePct()), new Color(26, 188, 156));
        doc.add(kpis2);
    }

    private void addMiniKpi(PdfPTable table, String label, String value, Color color) {
        PdfPCell cell = new PdfPCell();
        cell.setBorderColor(color);
        cell.setBorderWidth(2f);
        cell.setPadding(12f);
        cell.setHorizontalAlignment(Element.ALIGN_CENTER);
        cell.setBackgroundColor(new Color(color.getRed(), color.getGreen(), color.getBlue(), 20));

        Font lf = new Font(Font.HELVETICA, 8,  Font.NORMAL, new Color(100, 100, 100));
        Font vf = new Font(Font.HELVETICA, 16, Font.BOLD,   color);
        Paragraph p = new Paragraph();
        p.add(new Chunk(label + "\n", lf));
        p.add(new Chunk(value, vf));
        p.setAlignment(Element.ALIGN_CENTER);
        cell.addElement(p);
        table.addCell(cell);
    }

    private void addSectionTitle(Document doc, String text) throws DocumentException {
        Paragraph p = new Paragraph(text, SECTION_FONT);
        p.setSpacingBefore(6);
        p.setSpacingAfter(6);
        PdfPTable line = new PdfPTable(1);
        line.setWidthPercentage(100);
        PdfPCell c = new PdfPCell(p);
        c.setBorder(Rectangle.BOTTOM);
        c.setBorderColor(new Color(44, 62, 80));
        c.setBorderWidth(1.5f);
        c.setPaddingBottom(4);
        line.addCell(c);
        doc.add(line);
        doc.add(new Paragraph("\n", SMALL_FONT));
    }

    private void addKpiTable(Document doc, LeaveStatisticsService.GlobalStats g) throws DocumentException {
        PdfPTable table = new PdfPTable(2);
        table.setWidthPercentage(60);
        table.setWidths(new float[]{2f, 1.5f});

        String[][] kpis = {
            {"Total des demandes",        String.valueOf(g.total())},
            {"Demandes approuvées",       String.valueOf(g.approved())},
            {"Demandes refusées",         String.valueOf(g.rejected())},
            {"Demandes en attente",       String.valueOf(g.pending())},
            {"Total jours approuvés",     String.valueOf(g.totalApprovedDays())},
            {"Durée moyenne (jours)",     String.format("%.1f", g.avgApprovedDays())},
            {"Taux d'approbation",        String.format("%.1f%%", g.approvalRatePct())},
            {"Employés distincts",        String.valueOf(g.uniqueEmployees())},
        };
        pdfHeaderRow(table, "Indicateur", "Valeur");
        boolean stripe = false;
        for (String[] row : kpis) {
            Color bg = stripe ? STRIPE_BG : Color.WHITE;
            pdfDataCell(table, row[0], bg, Element.ALIGN_LEFT);
            pdfDataCell(table, row[1], bg, Element.ALIGN_CENTER);
            stripe = !stripe;
        }
        doc.add(table);
    }

    private void addTypeTable(Document doc, LeaveStatisticsService svc) throws DocumentException {
        Map<String, Long>   counts  = svc.countByType();
        Map<String, Long>   days    = svc.daysByType();
        Map<String, Double> avgDur  = svc.avgDurationByType();

        PdfPTable table = new PdfPTable(4);
        table.setWidthPercentage(100);
        table.setWidths(new float[]{3f, 1.5f, 2f, 2f});
        pdfHeaderRow(table, "Type de congé", "Nb demandes", "Jours approuvés", "Durée moy. (j)");
        boolean stripe = false;
        for (String type : counts.keySet()) {
            Color bg = stripe ? STRIPE_BG : Color.WHITE;
            pdfDataCell(table, type,                                                       bg, Element.ALIGN_LEFT);
            pdfDataCell(table, String.valueOf(counts.getOrDefault(type, 0L)),              bg, Element.ALIGN_CENTER);
            pdfDataCell(table, String.valueOf(days.getOrDefault(type, 0L)),               bg, Element.ALIGN_CENTER);
            pdfDataCell(table, String.format("%.1f", avgDur.getOrDefault(type, 0.0)),    bg, Element.ALIGN_CENTER);
            stripe = !stripe;
        }
        doc.add(table);
    }

    private void addMonthlyTable(Document doc, LeaveStatisticsService svc) throws DocumentException {
        Map<String, Long>   subs  = svc.submissionsPerMonth();
        Map<String, Long>   appr  = svc.approvedDaysPerMonth();
        Map<String, Double> rates = svc.approvalRatePerMonth();

        PdfPTable table = new PdfPTable(4);
        table.setWidthPercentage(100);
        table.setWidths(new float[]{2f, 2f, 2f, 2f});
        pdfHeaderRow(table, "Mois", "Demandes soumises", "Jours approuvés", "Taux d'approbation");
        Set<String> months = new LinkedHashSet<>(subs.keySet());
        months.addAll(appr.keySet());
        boolean stripe = false;
        for (String m : months) {
            Color bg = stripe ? STRIPE_BG : Color.WHITE;
            pdfDataCell(table, m,                                                     bg, Element.ALIGN_CENTER);
            pdfDataCell(table, String.valueOf(subs.getOrDefault(m, 0L)),              bg, Element.ALIGN_CENTER);
            pdfDataCell(table, String.valueOf(appr.getOrDefault(m, 0L)),              bg, Element.ALIGN_CENTER);
            pdfDataCell(table, String.format("%.1f%%", rates.getOrDefault(m, 0.0)), bg, Element.ALIGN_CENTER);
            stripe = !stripe;
        }
        doc.add(table);
    }

    private void addRequestsTable(Document doc, List<LeaveRequest> requests) throws DocumentException {
        PdfPTable table = new PdfPTable(7);
        table.setWidthPercentage(100);
        table.setWidths(new float[]{0.5f, 2.5f, 1.8f, 1.2f, 1.2f, 0.7f, 1.5f});
        pdfHeaderRow(table, "ID", "Employé", "Type", "Début", "Fin", "Jours", "Statut");
        boolean stripe = false;
        for (LeaveRequest req : requests) {
            Color bg = switch (req.getStatus()) {
                case ACCEPTE -> APPROVED_BG;
                case REFUSE  -> REJECTED_BG;
                default      -> stripe ? STRIPE_BG : Color.WHITE;
            };
            pdfDataCell(table, String.valueOf(req.getId()),      bg, Element.ALIGN_CENTER);
            pdfDataCell(table, req.getEmployeeName(),            bg, Element.ALIGN_LEFT);
            pdfDataCell(table, req.getLeaveType(),               bg, Element.ALIGN_LEFT);
            pdfDataCell(table, fmtDate(req.getStartDate()),      bg, Element.ALIGN_CENTER);
            pdfDataCell(table, fmtDate(req.getEndDate()),        bg, Element.ALIGN_CENTER);
            pdfDataCell(table, String.valueOf(req.getDaysCount()), bg, Element.ALIGN_CENTER);
            pdfDataCell(table, req.getStatus().getDisplayName(), bg, Element.ALIGN_CENTER);
            stripe = !stripe;
        }
        doc.add(table);
    }

    private void addEmployeeTable(Document doc, LeaveStatisticsService svc) throws DocumentException {
        PdfPTable table = new PdfPTable(5);
        table.setWidthPercentage(100);
        table.setWidths(new float[]{3f, 1.5f, 1.5f, 1.5f, 2f});
        pdfHeaderRow(table, "Employé", "Demandes", "✅ Approuvées", "❌ Refusées", "Jours approuvés");
        List<LeaveStatisticsService.EmployeeStat> empStats = svc.topEmployeesByDays(20);
        boolean stripe = false;
        int rank = 0;
        for (LeaveStatisticsService.EmployeeStat e : empStats) {
            rank++;
            Color bg = switch (rank) {
                case 1 -> new Color(255, 215, 0, 80);
                case 2 -> new Color(192, 192, 192, 80);
                case 3 -> new Color(205, 127, 50, 80);
                default -> stripe ? STRIPE_BG : Color.WHITE;
            };
            pdfDataCell(table, (rank <= 3 ? ("  🥇🥈🥉".substring(rank * 2 - 2, rank * 2) + "  ") : "      ") + e.name(), bg, Element.ALIGN_LEFT);
            pdfDataCell(table, String.valueOf(e.totalRequests()), bg, Element.ALIGN_CENTER);
            pdfDataCell(table, String.valueOf(e.approvedCount()), bg, Element.ALIGN_CENTER);
            pdfDataCell(table, String.valueOf(e.rejectedCount()), bg, Element.ALIGN_CENTER);
            pdfDataCell(table, String.valueOf(e.approvedDays()),  bg, Element.ALIGN_CENTER);
            stripe = !stripe;
        }
        doc.add(table);
    }

    private void pdfHeaderRow(PdfPTable table, String... headers) {
        for (String h : headers) {
            PdfPCell cell = new PdfPCell(new Phrase(h, HEADER_FONT));
            cell.setBackgroundColor(HEADER_BG);
            cell.setHorizontalAlignment(Element.ALIGN_CENTER);
            cell.setPadding(6f);
            cell.setBorderColor(Color.WHITE);
            table.addCell(cell);
        }
    }

    private void pdfDataCell(PdfPTable table, String text, Color bg, int align) {
        PdfPCell cell = new PdfPCell(new Phrase(text != null ? text : "", DATA_FONT));
        cell.setBackgroundColor(bg);
        cell.setHorizontalAlignment(align);
        cell.setPadding(4f);
        cell.setBorderColor(new Color(220, 220, 220));
        table.addCell(cell);
    }

    // ─── En-tête / pied de page PDF ──────────────────────────────────────────────

    private static class PdfPageEvent extends PdfPageEventHelper {
        @Override
        public void onEndPage(PdfWriter writer, Document doc) {
            PdfContentByte cb = writer.getDirectContent();
            cb.saveState();
            cb.setColorFill(new Color(44, 62, 80));
            cb.rectangle(30, doc.bottom() - 10, doc.right() - doc.left() + 30, 1);
            cb.fill();
            Font f = new Font(Font.HELVETICA, 7, Font.NORMAL, new Color(127, 140, 141));
            ColumnText.showTextAligned(cb, Element.ALIGN_CENTER,
                new Phrase("Workforce Platform  · Rapport Congés · Page " + writer.getPageNumber(), f),
                (doc.right() + doc.left()) / 2, doc.bottom() - 18, 0);
            cb.restoreState();
        }
    }

    // ═════════════════════════════════════════════════════════════════════════════
    //  EXCEL STYLES
    // ═════════════════════════════════════════════════════════════════════════════

    private XSSFCellStyle titleStyle(XSSFWorkbook wb) {
        XSSFCellStyle s = wb.createCellStyle();
        s.setFillForegroundColor(new XSSFColor(new byte[]{(byte)44,(byte)62,(byte)80}, null));
        s.setFillPattern(FillPatternType.SOLID_FOREGROUND);
        s.setAlignment(HorizontalAlignment.CENTER);
        s.setVerticalAlignment(VerticalAlignment.CENTER);
        XSSFFont f = wb.createFont(); f.setBold(true); f.setFontHeightInPoints((short)13);
        f.setColor(new XSSFColor(new byte[]{(byte)255,(byte)255,(byte)255}, null));
        s.setFont(f);
        s.getFont().setFontHeightInPoints((short)13);
        CellUtil_setRow(s, 30);
        return s;
    }

    private XSSFCellStyle headerStyle(XSSFWorkbook wb) {
        XSSFCellStyle s = wb.createCellStyle();
        s.setFillForegroundColor(new XSSFColor(new byte[]{(byte)52,(byte)73,(byte)94}, null));
        s.setFillPattern(FillPatternType.SOLID_FOREGROUND);
        s.setAlignment(HorizontalAlignment.CENTER);
        s.setBorderBottom(BorderStyle.THIN);
        XSSFFont f = wb.createFont(); f.setBold(true); f.setFontHeightInPoints((short)10);
        f.setColor(new XSSFColor(new byte[]{(byte)255,(byte)255,(byte)255}, null));
        s.setFont(f);
        return s;
    }

    private XSSFCellStyle dataStyle(XSSFWorkbook wb) {
        XSSFCellStyle s = wb.createCellStyle();
        s.setBorderBottom(BorderStyle.HAIR);
        s.setBorderRight(BorderStyle.HAIR);
        s.setBottomBorderColor(IndexedColors.GREY_25_PERCENT.getIndex());
        return s;
    }

    private XSSFCellStyle dateStyle(XSSFWorkbook wb) {
        XSSFCellStyle s = dataStyle(wb);
        s.setAlignment(HorizontalAlignment.CENTER);
        return s;
    }

    private XSSFCellStyle statusStyle(XSSFWorkbook wb, Color bg, Color fg) {
        XSSFCellStyle s = wb.createCellStyle();
        s.setFillForegroundColor(new XSSFColor(
            new byte[]{(byte)bg.getRed(),(byte)bg.getGreen(),(byte)bg.getBlue()}, null));
        s.setFillPattern(FillPatternType.SOLID_FOREGROUND);
        s.setAlignment(HorizontalAlignment.CENTER);
        XSSFFont f = wb.createFont(); f.setBold(true); f.setFontHeightInPoints((short)9);
        f.setColor(new XSSFColor(
            new byte[]{(byte)fg.getRed(),(byte)fg.getGreen(),(byte)fg.getBlue()}, null));
        s.setFont(f);
        return s;
    }

    private XSSFCellStyle totalStyle(XSSFWorkbook wb) {
        XSSFCellStyle s = wb.createCellStyle();
        s.setFillForegroundColor(new XSSFColor(new byte[]{(byte)236,(byte)240,(byte)241}, null));
        s.setFillPattern(FillPatternType.SOLID_FOREGROUND);
        XSSFFont f = wb.createFont(); f.setBold(true);
        s.setFont(f);
        return s;
    }

    private void CellUtil_setRow(XSSFCellStyle s, int height) { /* row height set separately */ }

    // ─── Sections Excel ──────────────────────────────────────────────────────────

    private int sectionTitle(XSSFSheet sheet, XSSFWorkbook wb, int rowIdx, String text) {
        Row row = sheet.createRow(rowIdx);
        row.setHeightInPoints(20);
        Cell c = row.createCell(0);
        c.setCellValue(text);
        XSSFCellStyle s = wb.createCellStyle();
        s.setFillForegroundColor(new XSSFColor(new byte[]{(byte)52,(byte)73,(byte)94}, null));
        s.setFillPattern(FillPatternType.SOLID_FOREGROUND);
        XSSFFont f = wb.createFont(); f.setBold(true); f.setFontHeightInPoints((short)11);
        f.setColor(new XSSFColor(new byte[]{(byte)255,(byte)255,(byte)255}, null));
        s.setFont(f);
        c.setCellStyle(s);
        sheet.addMergedRegion(new CellRangeAddress(rowIdx, rowIdx, 0, 5));
        return rowIdx + 2;
    }

    private int kpiRow(XSSFSheet sheet, XSSFWorkbook wb, int rowIdx, String label, Object value) {
        Row row = sheet.createRow(rowIdx);
        XSSFCellStyle ls = wb.createCellStyle();
        XSSFFont lf = wb.createFont(); lf.setBold(true); ls.setFont(lf);
        Cell lc = row.createCell(0); lc.setCellValue(label); lc.setCellStyle(ls);
        Cell vc = row.createCell(1); vc.setCellValue(value.toString());
        return rowIdx + 1;
    }

    private int tableHeaders(XSSFSheet sheet, XSSFWorkbook wb, int rowIdx, String... headers) {
        Row row = sheet.createRow(rowIdx);
        XSSFCellStyle s = headerStyle(wb);
        for (int i = 0; i < headers.length; i++) {
            Cell c = row.createCell(i);
            c.setCellValue(headers[i]);
            c.setCellStyle(s);
        }
        return rowIdx + 1;
    }

    // ─── Utilitaires généraux ────────────────────────────────────────────────────

    private void cell(Row row, int col, Object val, XSSFCellStyle style) {
        Cell c = row.createCell(col);
        if (val instanceof Number n) c.setCellValue(n.doubleValue());
        else c.setCellValue(val != null ? val.toString() : "");
        c.setCellStyle(style);
    }

    private String fmtDate(LocalDate d) { return d != null ? d.format(DATE_FMT) : "—"; }
    private String nvl(String s)         { return s != null ? s : ""; }
}
