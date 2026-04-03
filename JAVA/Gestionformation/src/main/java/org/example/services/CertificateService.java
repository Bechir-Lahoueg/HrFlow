package org.example.services;

import com.itextpdf.io.image.ImageDataFactory;
import com.itextpdf.kernel.colors.ColorConstants;
import com.itextpdf.kernel.geom.PageSize;
import com.itextpdf.kernel.pdf.PdfDocument;
import com.itextpdf.kernel.pdf.PdfWriter;
import com.itextpdf.layout.Document;
import com.itextpdf.layout.element.Image;
import com.itextpdf.layout.element.Paragraph;
import com.itextpdf.layout.properties.TextAlignment;
import java.io.File;
import java.net.URL;
import java.sql.Date;

public class CertificateService {

    public void generateCertificate(String employeeName, String formationTitle, Date sqlDateDebut, Date sqlDateFin, String organisme) {
        String dest = System.getProperty("user.home") + File.separator + "Desktop" + File.separator + "Certificat_" + employeeName.replace(" ", "_") + ".pdf";

        try (PdfWriter writer = new PdfWriter(dest);
             PdfDocument pdf = new PdfDocument(writer);
             Document document = new Document(pdf, PageSize.A4.rotate())) {

            URL imageUrl = getClass().getResource("/templates/certificate_template.png");
            if (imageUrl == null) {
                System.err.println("❌ Image de template introuvable à /templates/certificate_template.png");
                return;
            }
            Image template = new Image(ImageDataFactory.create(imageUrl));
            template.setFixedPosition(0, 0);
            template.scaleToFit(PageSize.A4.rotate().getWidth(), PageSize.A4.rotate().getHeight());
            document.add(template);

            document.add(new Paragraph(employeeName)
                    .setFontSize(36)
                    .setBold()
                    .setFontColor(ColorConstants.WHITE)
                    .setFixedPosition(68, 250, 723)
                    .setTextAlignment(TextAlignment.CENTER));

            document.add(new Paragraph(formationTitle)
                    .setFontSize(26)
                    .setItalic()
                    .setFontColor(ColorConstants.WHITE)
                    .setFixedPosition(0, 155, PageSize.A4.rotate().getWidth())
                    .setTextAlignment(TextAlignment.CENTER));

            String dateDebut = (sqlDateDebut != null) ? sqlDateDebut.toLocalDate().toString() : "N/A";
            document.add(new Paragraph(dateDebut)
                    .setFontSize(14)
                    .setFontColor(ColorConstants.WHITE)
                    .setFixedPosition(180, 100, 200));

            String dateFin = (sqlDateFin != null) ? sqlDateFin.toLocalDate().toString() : "N/A";
            document.add(new Paragraph(dateFin)
                    .setFontSize(14)
                    .setFontColor(ColorConstants.WHITE)
                    .setFixedPosition(680, 100, 200));

            String orgText = (organisme != null) ? organisme : "N/A";
            document.add(new Paragraph(orgText)
                    .setFontSize(14)
                    .setFontColor(ColorConstants.WHITE)
                    .setFixedPosition(220, 50, 400));

            document.add(new Paragraph("Fait le : " + java.time.LocalDate.now().toString())
                    .setFontSize(12)
                    .setFontColor(ColorConstants.WHITE)
                    .setFixedPosition(600, 40, 200)
                    .setTextAlignment(TextAlignment.RIGHT));

            System.out.println("✅ Certificat généré : " + dest);

        } catch (Exception e) {
            System.err.println("❌ Erreur lors de la génération du PDF");
            e.printStackTrace();
        }
    }
}