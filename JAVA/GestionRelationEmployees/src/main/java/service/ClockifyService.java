package service;
import java.io.InputStream;
import java.util.Properties;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import org.json.JSONObject;


public class ClockifyService {

    private static String API_KEY;
    private static String WORKSPACE_ID;
    private static final String BASE_URL = "https://api.clockify.me/api/v1";

    private String currentTimerId; // Pour savoir quel timer arrêter

    // Bloc statique pour charger les propriétés au démarrage
    static {
        try (InputStream input = ClockifyService.class.getClassLoader().getResourceAsStream("clockify.properties")) {
            Properties prop = new Properties();
            if (input == null) {
                System.err.println("Désolé, impossible de trouver clockify.properties");
            } else {
                prop.load(input);
                API_KEY = prop.getProperty("clockify.api.key");
                WORKSPACE_ID = prop.getProperty("clockify.workspace.id");
            }
        } catch (Exception ex) {
            ex.printStackTrace();
        }
    }

    // 1. Démarrer le Timer
    public void startTimer(String description) throws Exception {
        if (API_KEY == null || WORKSPACE_ID == null) {
            throw new Exception("Configuration Clockify manquante dans clockify.properties");
        }

        String url = BASE_URL + "/workspaces/" + WORKSPACE_ID + "/time-entries";

        JSONObject body = new JSONObject();
        body.put("start", java.time.Instant.now().toString());
        body.put("description", description);

        HttpClient client = HttpClient.newHttpClient();
        HttpRequest request = HttpRequest.newBuilder()
                .uri(URI.create(url))
                .header("X-Api-Key", API_KEY)
                .header("Content-Type", "application/json")
                .POST(HttpRequest.BodyPublishers.ofString(body.toString()))
                .build();

        HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());

        // On récupère l'ID de l'entrée pour pouvoir l'arrêter plus tard
        if (response.statusCode() == 201) { // 201 signifie que Clockify a bien créé l'entrée
            JSONObject jsonResponse = new JSONObject(response.body());
            this.currentTimerId = jsonResponse.getString("id");
            System.out.println("Timer démarré ! ID: " + this.currentTimerId);
        } else {
            // Si le code n'est pas 201, on affiche l'erreur envoyée par Clockify (ex: clé API invalide)
            throw new Exception("Erreur API Clockify : " + response.body());
        }
    }

    // 2. Arrêter le Timer
    public void stopTimer() throws Exception {
        String url = BASE_URL + "/workspaces/" + WORKSPACE_ID + "/time-entries/" + this.currentTimerId;
        // Note: L'arrêt sur Clockify se fait souvent en mettant à jour l'entrée avec une heure de fin "end"

        JSONObject body = new JSONObject();
        body.put("end", java.time.Instant.now().toString());

        HttpClient client = HttpClient.newHttpClient();
        HttpRequest request = HttpRequest.newBuilder()
                .uri(URI.create(url))
                .header("X-Api-Key", API_KEY)
                .header("Content-Type", "application/json")
                .method("PATCH", HttpRequest.BodyPublishers.ofString(body.toString()))
                .build();

        client.send(request, HttpResponse.BodyHandlers.ofString());
        System.out.println("Timer arrêté !");
    }
}