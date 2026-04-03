package org.example.services;

import java.io.InputStream;
import java.io.FileOutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;

public class QRCodeService {

    private final String API_KEY = "c7ed4a2267msh0bde65dc2423c74p119780jsn2bc1d0c0c058";

    private final String API_HOST = "qrcode-generator20.p.rapidapi.com";

    public String genererQRCode(String data, String fileName) throws Exception {
        String encodedData = URLEncoder.encode(data, StandardCharsets.UTF_8.toString());

        String urlStr = "https://qrcode-generator20.p.rapidapi.com/get-qrcode?data=" + encodedData;

        URL url = new URL(urlStr);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();

        conn.setRequestMethod("GET");
        conn.setRequestProperty("x-rapidapi-key", API_KEY);
        conn.setRequestProperty("x-rapidapi-host", API_HOST);

        int responseCode = conn.getResponseCode();

        if (responseCode == HttpURLConnection.HTTP_OK) {
            try (InputStream in = conn.getInputStream();
                 FileOutputStream out = new FileOutputStream(fileName)) {
                byte[] buffer = new byte[4096];
                int bytesRead;
                while ((bytesRead = in.read(buffer)) != -1) {
                    out.write(buffer, 0, bytesRead);
                }
            }
            return fileName;
        } else {
            System.err.println("Erreur QR Code : Code " + responseCode);
            return genererPlanB(data, fileName);
        }
    }

    // Le Plan B :
    private String genererPlanB(String data, String fileName) throws Exception {
        String encodedData = URLEncoder.encode(data, StandardCharsets.UTF_8.toString());
        URL url = new URL("https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" + encodedData);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();

        try (InputStream in = conn.getInputStream();
             FileOutputStream out = new FileOutputStream(fileName)) {
            byte[] buffer = new byte[4096];
            int bytesRead;
            while ((bytesRead = in.read(buffer)) != -1) {
                out.write(buffer, 0, bytesRead);
            }
        }
        return fileName;
    }
}