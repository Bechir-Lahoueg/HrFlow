package org.example.services;

import com.google.gson.Gson;
import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import okhttp3.*;
import java.io.IOException;
import java.util.concurrent.TimeUnit;

public class OpenAIService {

    private static final String GROQ_API_KEY = "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";

    private static final String URL = "https://api.groq.com/openai/v1/chat/completions";

    private static final OkHttpClient client = new OkHttpClient.Builder()
            .connectTimeout(20, TimeUnit.SECONDS)
            .readTimeout(20, TimeUnit.SECONDS)
            .build();
    private static final Gson gson = new Gson();

    public static String generateObjectives(String title) {
        // Préparation du JSON au format Chat Completion
        JsonObject message = new JsonObject();
        message.addProperty("role", "user");
        message.addProperty("content", "Génère 3 objectifs très courts pour la formation : " + title);

        JsonArray messages = new JsonArray();
        messages.add(message);

        JsonObject body = new JsonObject();
        body.addProperty("model", "llama-3.1-8b-instant");
        body.add("messages", messages);

        Request request = new Request.Builder()
                .url(URL)
                .post(RequestBody.create(body.toString(), MediaType.parse("application/json")))
                .addHeader("Authorization", "Bearer " + GROQ_API_KEY)
                .build();

        try (Response response = client.newCall(request).execute()) {
            String responseBody = response.body().string();

            if (!response.isSuccessful()) {
                System.out.println("Erreur Groq : " + response.code() + " - " + responseBody);
                return "Erreur technique : " + response.code();
            }

            // Parsing du format OpenAI/Groq
            JsonObject json = gson.fromJson(responseBody, JsonObject.class);
            JsonArray choices = json.getAsJsonArray("choices");
            if (choices != null && choices.size() > 0) {
                return choices.get(0).getAsJsonObject()
                        .get("message").getAsJsonObject()
                        .get("content").getAsString().trim();
            }
            return "Aucun contenu généré.";

        } catch (IOException e) {
            e.printStackTrace();
            return "Erreur de connexion.";
        }
    }
}