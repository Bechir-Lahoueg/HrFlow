package service;

import java.io.*;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.util.*;
import org.json.JSONObject;

/**
 * Service de stockage cloud sécurisé avec Cloudinary
 * 25 GB gratuit, aucune carte bancaire requise
 * Stockage : images, PDF, documents
 */
public class CloudinaryService {

    // ═══════════════════════════════════════════════════════════════
    // CONFIGURATION (chargée depuis fichier externe)
    // ═══════════════════════════════════════════════════════════════

    private static String CLOUD_NAME;
    private static String API_KEY;
    private static String API_SECRET;
    private static final String UPLOAD_URL_PREFIX = "https://api.cloudinary.com/v1_1/";

    static {
        loadConfiguration();
    }

    /**
     * Charge la configuration depuis cloudinary-config.properties
     */
    private static void loadConfiguration() {
        Properties props = new Properties();

        // 1. Essayer de charger depuis le Classpath (le dossier resources compilé)
        // C'est ici que Java trouvera le fichier dans Workforce-Platform
        try (InputStream is = CloudinaryService.class.getClassLoader()
                .getResourceAsStream("cloudinary-config.properties")) {

            if (is != null) {
                props.load(is);
                CLOUD_NAME = props.getProperty("cloudinary.cloud.name");
                API_KEY = props.getProperty("cloudinary.api.key");
                API_SECRET = props.getProperty("cloudinary.api.secret");

                if (CLOUD_NAME != null) {
                    System.out.println("✅ Configuration Cloudinary chargée via ClassLoader !");
                    return; // On a trouvé, on s'arrête ici
                }
            }
        } catch (IOException e) {
            System.err.println("⚠️ Erreur lors du chargement : " + e.getMessage());
        }
        System.err.println("❌ ERREUR CRITIQUE : Configuration Cloudinary introuvable !");
        }

    // ═══════════════════════════════════════════════════════════════
    // UPLOAD DE FICHIERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Upload une image (photo de profil, logo, etc.)
     * @param filePath Chemin du fichier local
     * @param folder Dossier dans Cloudinary (ex: "employees/profiles")
     * @return URL publique de l'image ou null si erreur
     */
    public String uploadImage(String filePath, String folder) {
        return uploadFile(filePath, folder, "image");
    }

    /**
     * Upload un document PDF
     * @param filePath Chemin du fichier local
     * @param folder Dossier dans Cloudinary (ex: "projects/documents")
     * @return URL publique du PDF ou null si erreur
     */
    public String uploadPDF(String filePath, String folder) {
        return uploadFile(filePath, folder, "raw");
    }

    /**
     * Upload un fichier avec File object
     */
    public String uploadImage(File file, String folder) {
        return uploadFile(file.getAbsolutePath(), folder, "image");
    }

    /**
     * Méthode générique d'upload
     */
    private String uploadFile(String filePath, String folder, String resourceType) {
        if (CLOUD_NAME == null || API_KEY == null || API_SECRET == null) {
            System.err.println("❌ Configuration Cloudinary non chargée");
            return null;
        }

        try {
            File file = new File(filePath);
            if (!file.exists()) {
                System.err.println("❌ Fichier introuvable : " + filePath);
                return null;
            }

            // Générer signature pour sécurité
            long timestamp = System.currentTimeMillis() / 1000;
            String toSign = "folder=" + folder + "&timestamp=" + timestamp + API_SECRET;
            String signature = generateSignature(toSign);

            // Préparer la requête multipart
            String boundary = "----Boundary" + System.currentTimeMillis();
            String uploadUrl = UPLOAD_URL_PREFIX + CLOUD_NAME + "/" + resourceType + "/upload";

            URL url = new URL(uploadUrl);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setDoOutput(true);
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Content-Type", "multipart/form-data; boundary=" + boundary);

            try (OutputStream os = conn.getOutputStream();
                 PrintWriter writer = new PrintWriter(new OutputStreamWriter(os, StandardCharsets.UTF_8), true)) {

                // Champs du formulaire
                addFormField(writer, boundary, "api_key", API_KEY);
                addFormField(writer, boundary, "timestamp", String.valueOf(timestamp));
                addFormField(writer, boundary, "signature", signature);
                addFormField(writer, boundary, "folder", folder);

                // Fichier
                addFileField(writer, os, boundary, "file", file);

                // Fin du formulaire
                writer.append("--").append(boundary).append("--").append("\r\n");
                writer.flush();
            }

            // Lire la réponse
            int responseCode = conn.getResponseCode();
            if (responseCode == 200) {
                try (BufferedReader br = new BufferedReader(
                        new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8))) {
                    StringBuilder response = new StringBuilder();
                    String line;
                    while ((line = br.readLine()) != null) {
                        response.append(line);
                    }

                    JSONObject json = new JSONObject(response.toString());
                    String publicUrl = json.getString("secure_url");
                    System.out.println("✅ Fichier uploadé : " + publicUrl);
                    return publicUrl;
                }
            } else {
                try (BufferedReader br = new BufferedReader(
                        new InputStreamReader(conn.getErrorStream(), StandardCharsets.UTF_8))) {
                    StringBuilder error = new StringBuilder();
                    String line;
                    while ((line = br.readLine()) != null) {
                        error.append(line);
                    }
                    System.err.println("❌ Erreur upload (" + responseCode + "): " + error);
                }
                return null;
            }

        } catch (Exception e) {
            System.err.println("❌ Erreur upload fichier : " + e.getMessage());
            e.printStackTrace();
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SUPPRESSION DE FICHIERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Supprime un fichier de Cloudinary
     * @param publicId ID public du fichier (extrait de l'URL)
     * @return true si succès
     */
    public boolean deleteFile(String publicId, String resourceType) {
        if (CLOUD_NAME == null || API_KEY == null || API_SECRET == null) {
            System.err.println("❌ Configuration Cloudinary non chargée");
            return false;
        }

        try {
            long timestamp = System.currentTimeMillis() / 1000;
            String toSign = "public_id=" + publicId + "&timestamp=" + timestamp + API_SECRET;
            String signature = generateSignature(toSign);

            String deleteUrl = UPLOAD_URL_PREFIX + CLOUD_NAME + "/" + resourceType + "/destroy";

            URL url = new URL(deleteUrl);
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("POST");
            conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded");
            conn.setDoOutput(true);

            String params = "api_key=" + API_KEY +
                    "&timestamp=" + timestamp +
                    "&signature=" + signature +
                    "&public_id=" + URLEncoder.encode(publicId, StandardCharsets.UTF_8);

            try (OutputStream os = conn.getOutputStream()) {
                os.write(params.getBytes(StandardCharsets.UTF_8));
            }

            int responseCode = conn.getResponseCode();
            if (responseCode == 200) {
                System.out.println("✅ Fichier supprimé : " + publicId);
                return true;
            } else {
                System.err.println("❌ Erreur suppression (" + responseCode + ")");
                return false;
            }

        } catch (Exception e) {
            System.err.println("❌ Erreur suppression fichier : " + e.getMessage());
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // TRANSFORMATION D'IMAGES
    // ═══════════════════════════════════════════════════════════════

    /**
     * Génère une URL d'image redimensionnée
     * @param originalUrl URL originale de Cloudinary
     * @param width Largeur souhaitée
     * @param height Hauteur souhaitée
     * @return URL de l'image transformée
     */
    public String getResizedImageUrl(String originalUrl, int width, int height) {
        // Format: https://res.cloudinary.com/CLOUD/image/upload/v123/folder/file.jpg
        // Devient: https://res.cloudinary.com/CLOUD/image/upload/w_200,h_200,c_fill/v123/folder/file.jpg

        if (originalUrl == null || !originalUrl.contains("cloudinary.com")) {
            return originalUrl;
        }

        String transformation = "w_" + width + ",h_" + height + ",c_fill";
        return originalUrl.replace("/upload/", "/upload/" + transformation + "/");
    }

    /**
     * Génère une URL d'image avec transformation ronde (avatar)
     */
    public String getRoundedImageUrl(String originalUrl, int size) {
        if (originalUrl == null || !originalUrl.contains("cloudinary.com")) {
            return originalUrl;
        }

        String transformation = "w_" + size + ",h_" + size + ",c_fill,r_max";
        return originalUrl.replace("/upload/", "/upload/" + transformation + "/");
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════

    private void addFormField(PrintWriter writer, String boundary, String name, String value) {
        writer.append("--").append(boundary).append("\r\n");
        writer.append("Content-Disposition: form-data; name=\"").append(name).append("\"\r\n");
        writer.append("\r\n");
        writer.append(value).append("\r\n");
    }

    private void addFileField(PrintWriter writer, OutputStream os, String boundary,
                              String fieldName, File file) throws IOException {
        String fileName = file.getName();

        writer.append("--").append(boundary).append("\r\n");
        writer.append("Content-Disposition: form-data; name=\"").append(fieldName)
                .append("\"; filename=\"").append(fileName).append("\"\r\n");
        writer.append("Content-Type: ").append(getMimeType(fileName)).append("\r\n");
        writer.append("\r\n");
        writer.flush();

        try (FileInputStream fis = new FileInputStream(file)) {
            byte[] buffer = new byte[4096];
            int bytesRead;
            while ((bytesRead = fis.read(buffer)) != -1) {
                os.write(buffer, 0, bytesRead);
            }
        }

        writer.append("\r\n");
        writer.flush();
    }

    private String getMimeType(String fileName) {
        String extension = fileName.substring(fileName.lastIndexOf('.') + 1).toLowerCase();
        return switch (extension) {
            case "jpg", "jpeg" -> "image/jpeg";
            case "png" -> "image/png";
            case "gif" -> "image/gif";
            case "pdf" -> "application/pdf";
            case "doc" -> "application/msword";
            case "docx" -> "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
            default -> "application/octet-stream";
        };
    }

    private String generateSignature(String toSign) {
        try {
            java.security.MessageDigest md = java.security.MessageDigest.getInstance("SHA-1");
            byte[] hash = md.digest(toSign.getBytes(StandardCharsets.UTF_8));

            StringBuilder hexString = new StringBuilder();
            for (byte b : hash) {
                String hex = Integer.toHexString(0xff & b);
                if (hex.length() == 1) hexString.append('0');
                hexString.append(hex);
            }
            return hexString.toString();
        } catch (Exception e) {
            throw new RuntimeException("Erreur génération signature", e);
        }
    }

    /**
     * Extrait le public_id d'une URL Cloudinary
     * Ex: https://res.cloudinary.com/demo/image/upload/v123/folder/file.jpg → folder/file
     */
    public String extractPublicId(String cloudinaryUrl) {
        if (cloudinaryUrl == null || !cloudinaryUrl.contains("cloudinary.com")) {
            return null;
        }

        try {
            // Format: .../upload/v123/folder/file.jpg
            String afterUpload = cloudinaryUrl.substring(cloudinaryUrl.indexOf("/upload/") + 8);
            // Retirer la version (v123/)
            if (afterUpload.startsWith("v")) {
                afterUpload = afterUpload.substring(afterUpload.indexOf('/') + 1);
            }
            // Retirer l'extension
            return afterUpload.substring(0, afterUpload.lastIndexOf('.'));
        } catch (Exception e) {
            System.err.println("❌ Erreur extraction public_id : " + e.getMessage());
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // CLASSES HELPER
    // ═══════════════════════════════════════════════════════════════

    public static class UploadResult {
        public String url;
        public String publicId;
        public long size;
        public String format;

        public UploadResult(String url, String publicId, long size, String format) {
            this.url = url;
            this.publicId = publicId;
            this.size = size;
            this.format = format;
        }
    }
}