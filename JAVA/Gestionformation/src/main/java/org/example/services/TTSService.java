package org.example.services;

import java.io.InputStream;
import java.io.FileOutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;

public class TTSService {

    public String genererAudio(String texte, String cheminFichier) throws Exception {
        String textePropre = texte.replaceAll("\\*\\*", "").replace("\n", " ").trim();

        List<String> morceaux = decouperTexte(textePropre, 180);

        try (FileOutputStream out = new FileOutputStream(cheminFichier)) {
            for (String morceau : morceaux) {
                String texteEncode = URLEncoder.encode(morceau, StandardCharsets.UTF_8.toString());
                String urlStr = "https://translate.google.com/translate_tts?ie=UTF-8&tl=fr&client=tw-ob&q=" + texteEncode;

                URL url = new URL(urlStr);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestProperty("User-Agent", "Mozilla/5.0");

                if (conn.getResponseCode() == HttpURLConnection.HTTP_OK) {
                    try (InputStream in = conn.getInputStream()) {
                        byte[] buffer = new byte[4096];
                        int bytesRead;
                        while ((bytesRead = in.read(buffer)) != -1) {
                            out.write(buffer, 0, bytesRead);
                        }
                    }
                }
                conn.disconnect();
                Thread.sleep(100);
            }
        }
        return cheminFichier;
    }

    private List<String> decouperTexte(String texte, int limite) {
        List<String> chunks = new ArrayList<>();
        StringBuilder sb = new StringBuilder();

        for (String mot : texte.split(" ")) {
            if (sb.length() + mot.length() + 1 > limite) {
                chunks.add(sb.toString());
                sb.setLength(0);
            }
            if (sb.length() > 0) sb.append(" ");
            sb.append(mot);
        }
        if (sb.length() > 0) chunks.add(sb.toString());
        return chunks;
    }
}