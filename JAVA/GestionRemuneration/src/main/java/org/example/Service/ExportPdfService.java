package org.example.Service;

import org.apache.pdfbox.pdmodel.PDDocument;
import org.apache.pdfbox.pdmodel.PDPage;
import org.apache.pdfbox.pdmodel.PDPageContentStream;
import org.apache.pdfbox.pdmodel.common.PDRectangle;
import org.apache.pdfbox.pdmodel.font.PDType1Font;
import org.example.Entity.FichePaie;

import java.io.IOException;
import java.nio.file.Path;

/**
 * Service d'export de fiche de paie au format PDF.
 * Utilise Apache PDFBox 2.x — aucune clé API requise.
 */
public class ExportPdfService {

    // Couleur principale : bleu RH-Flow  (R=0.18, G=0.31, B=0.52)
    private static final float[] COLOR_PRIMARY = {0.18f, 0.31f, 0.52f};
    private static final float[] COLOR_WHITE   = {1f, 1f, 1f};
    private static final float[] COLOR_BLACK   = {0f, 0f, 0f};
    private static final float[] COLOR_GRAY    = {0.65f, 0.65f, 0.65f};
    private static final float[] COLOR_LIGHT   = {0.93f, 0.95f, 0.98f};

    /**
     * Génère un PDF de fiche de paie et l'enregistre au chemin indiqué.
     *
     * @param fiche       FichePaie à exporter
     * @param employeNom  Nom complet de l'employé
     * @param poste       Intitulé du poste (peut être null)
     * @param outputPath  Chemin de sortie du fichier PDF
     * @throws IOException en cas d'erreur d'écriture
     */
    public void exportFichePaiePDF(FichePaie fiche,
                                   String employeNom,
                                   String poste,
                                   Path outputPath) throws IOException {

        try (PDDocument doc = new PDDocument()) {
            PDPage page = new PDPage(PDRectangle.A4);
            doc.addPage(page);

            float pageWidth  = page.getMediaBox().getWidth();   // 595
            float pageHeight = page.getMediaBox().getHeight();  // 842
            float margin = 50f;
            float contentWidth = pageWidth - 2 * margin;

            try (PDPageContentStream cs = new PDPageContentStream(doc, page)) {

                // ─── BANDE D'EN-TÊTE ──────────────────────────────────────
                setFill(cs, COLOR_PRIMARY);
                cs.addRect(0, pageHeight - 55, pageWidth, 55);
                cs.fill();

                setFill(cs, COLOR_WHITE);
                cs.setFont(PDType1Font.HELVETICA_BOLD, 20);
                drawText(cs, "HR-FLOW", margin, pageHeight - 35);

                cs.setFont(PDType1Font.HELVETICA, 11);
                drawText(cs, "BULLETIN DE PAIE", margin + 120, pageHeight - 35);

                // Période en haut à droite
                String periode = (fiche.getMois() != null ? fiche.getMois() : "—")
                                 + "  " + fiche.getAnnee();
                cs.setFont(PDType1Font.HELVETICA_BOLD, 11);
                float periodeX = pageWidth - margin - 110;
                drawText(cs, periode, periodeX, pageHeight - 35);

                // ─── BLOC INFORMATIONS EMPLOYÉ ────────────────────────────
                float y = pageHeight - 80;

                setFill(cs, COLOR_LIGHT);
                cs.addRect(margin, y - 60, contentWidth, 65);
                cs.fill();

                setFill(cs, COLOR_BLACK);
                cs.setFont(PDType1Font.HELVETICA_BOLD, 10);
                drawText(cs, "INFORMATIONS EMPLOYÉ", margin + 8, y - 12);

                cs.setFont(PDType1Font.HELVETICA, 10);
                drawText(cs, "Nom & Prénom : " + nvl(employeNom), margin + 8, y - 28);
                drawText(cs, "Poste           : " + nvl(poste),    margin + 8, y - 43);
                drawText(cs, "Période         : " + periode,        margin + 8, y - 58);

                // ─── TITRE SECTION RÉMUNÉRATION ───────────────────────────
                y = pageHeight - 165;

                setFill(cs, COLOR_PRIMARY);
                cs.setFont(PDType1Font.HELVETICA_BOLD, 10);
                cs.addRect(margin, y - 4, contentWidth, 18);
                cs.fill();

                setFill(cs, COLOR_WHITE);
                drawText(cs, "DÉTAIL DE LA RÉMUNÉRATION", margin + 8, y + 8);

                // ─── LIGNES DE DÉTAIL ─────────────────────────────────────
                y -= 20;
                String[] labels  = {
                    "Salaire Brut",
                    "(+) Total Primes",
                    "(-) Total Déductions  [CNSS + AMG + IRPP]"
                };
                String[] valeurs = {
                    fmt(fiche.getSalaireBrut()),
                    fmt(fiche.getTotalPrimes()),
                    fmt(fiche.getTotalDeductions())
                };

                for (int i = 0; i < labels.length; i++) {
                    // Fond alterné
                    if (i % 2 == 0) {
                        setFill(cs, new float[]{0.97f, 0.97f, 0.97f});
                        cs.addRect(margin, y - 4, contentWidth, 18);
                        cs.fill();
                    }

                    setFill(cs, COLOR_BLACK);
                    cs.setFont(PDType1Font.HELVETICA, 10);
                    drawText(cs, labels[i],  margin + 8, y + 8);

                    cs.setFont(PDType1Font.HELVETICA_BOLD, 10);
                    float valX = pageWidth - margin - 90;
                    drawText(cs, valeurs[i], valX, y + 8);

                    // Trait séparateur fin
                    setStroke(cs, COLOR_GRAY);
                    cs.setLineWidth(0.3f);
                    cs.moveTo(margin, y - 4);
                    cs.lineTo(pageWidth - margin, y - 4);
                    cs.stroke();

                    y -= 20;
                }

                // ─── LIGNE SALAIRE NET (mise en valeur) ───────────────────
                y -= 6;

                setFill(cs, COLOR_PRIMARY);
                cs.addRect(margin, y - 6, contentWidth, 26);
                cs.fill();

                setFill(cs, COLOR_WHITE);
                cs.setFont(PDType1Font.HELVETICA_BOLD, 13);
                drawText(cs, "SALAIRE NET À PAYER", margin + 8, y + 9);

                cs.setFont(PDType1Font.HELVETICA_BOLD, 14);
                float netX = pageWidth - margin - 110;
                drawText(cs, fmt(fiche.getSalaireNet()), netX, y + 9);

                // ─── NOTE FISCALE ─────────────────────────────────────────
                y -= 50;
                setFill(cs, COLOR_GRAY);
                cs.setFont(PDType1Font.HELVETICA, 8);
                drawText(cs,
                    "Les déductions incluent : CNSS (9.18%) + AMG (4%) + IRPP (barème Tunisie 2025)",
                    margin, y);

                // ─── PIED DE PAGE ─────────────────────────────────────────
                setStroke(cs, COLOR_GRAY);
                cs.setLineWidth(0.4f);
                cs.moveTo(margin, 45);
                cs.lineTo(pageWidth - margin, 45);
                cs.stroke();

                setFill(cs, COLOR_GRAY);
                cs.setFont(PDType1Font.HELVETICA, 7);
                drawText(cs,
                    "Document généré automatiquement par HR-Flow · ESPRIT PIDEV 3A4 · 2025–2026",
                    margin, 32);
                drawText(cs,
                    "Ce document a valeur d'information. Il ne constitue pas un acte juridique.",
                    margin, 22);
            }

            doc.save(outputPath.toFile());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private void drawText(PDPageContentStream cs, String text, float x, float y)
            throws IOException {
        cs.beginText();
        cs.newLineAtOffset(x, y);
        cs.showText(text != null ? text : "");
        cs.endText();
    }

    private void setFill(PDPageContentStream cs, float[] rgb) throws IOException {
        cs.setNonStrokingColor(rgb[0], rgb[1], rgb[2]);
    }

    private void setStroke(PDPageContentStream cs, float[] rgb) throws IOException {
        cs.setStrokingColor(rgb[0], rgb[1], rgb[2]);
    }

    private String fmt(java.math.BigDecimal v) {
        if (v == null) return "0.000 DT";
        return String.format("%.3f DT", v);
    }

    private String nvl(String s) {
        return (s != null && !s.isBlank()) ? s : "—";
    }
}
