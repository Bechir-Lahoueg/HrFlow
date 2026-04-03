package org.example.Service;

import org.json.JSONObject;

import java.io.IOException;
import java.math.BigDecimal;
import java.math.RoundingMode;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;
import java.util.HashMap;
import java.util.Map;

/**
 * Service de conversion de devises en temps réel.
 * Utilise l'API GRATUITE open.er-api.com (aucune clé requise).
 * 
 * Exemple d'utilisation :
 * <pre>
 * ConversionDevisesService service = new ConversionDevisesService();
 * BigDecimal montantEUR = service.convertir(1500, "TND", "EUR");
 * System.out.println("1500 TND = " + montantEUR + " EUR");
 * </pre>
 */
public class ConversionDevisesService {

    private static final String API_BASE_URL = "https://open.er-api.com/v6/latest/";
    private static final int TIMEOUT_SECONDS = 10;
    
    private final HttpClient httpClient;
    private Map<String, BigDecimal> cachedRates;
    private long cacheTimestamp = 0;
    private static final long CACHE_VALIDITY_MS = 3600_000; // 1 heure

    public ConversionDevisesService() {
        this.httpClient = HttpClient.newBuilder()
                .connectTimeout(Duration.ofSeconds(TIMEOUT_SECONDS))
                .build();
        this.cachedRates = new HashMap<>();
    }

    /**
     * Convertit un montant d'une devise source vers une devise cible.
     * 
     * @param montant       montant à convertir
     * @param deviseSource  code ISO de la devise source (ex: "TND")
     * @param deviseCible   code ISO de la devise cible (ex: "EUR", "USD")
     * @return montant converti avec 3 décimales
     * @throws IOException si l'API est inaccessible
     */
    public BigDecimal convertir(BigDecimal montant, String deviseSource, String deviseCible) 
            throws IOException, InterruptedException {
        
        if (montant == null || montant.compareTo(BigDecimal.ZERO) == 0) {
            return BigDecimal.ZERO;
        }

        // Si conversion vers la même devise, retourne le montant tel quel
        if (deviseSource.equalsIgnoreCase(deviseCible)) {
            return montant.setScale(3, RoundingMode.HALF_UP);
        }

        // Récupère le taux de change
        BigDecimal taux = getTauxDeChange(deviseSource, deviseCible);
        
        // Applique le taux
        return montant.multiply(taux).setScale(3, RoundingMode.HALF_UP);
    }

    /**
     * Convertit un montant TND vers EUR.
     */
    public BigDecimal convertirTndVersEur(BigDecimal montantTND) 
            throws IOException, InterruptedException {
        return convertir(montantTND, "TND", "EUR");
    }

    /**
     * Convertit un montant TND vers USD.
     */
    public BigDecimal convertirTndVersUsd(BigDecimal montantTND) 
            throws IOException, InterruptedException {
        return convertir(montantTND, "TND", "USD");
    }

    /**
     * Récupère le taux de change entre deux devises.
     * Utilise un cache d'1 heure pour limiter les appels API.
     */
    public BigDecimal getTauxDeChange(String deviseSource, String deviseCible) 
            throws IOException, InterruptedException {
        
        // Vérifie si le cache est encore valide
        long now = System.currentTimeMillis();
        if (cachedRates.isEmpty() || (now - cacheTimestamp) > CACHE_VALIDITY_MS) {
            refreshRates(deviseSource);
        }

        BigDecimal taux = cachedRates.get(deviseCible.toUpperCase());
        if (taux == null) {
            throw new IllegalArgumentException(
                "Devise non supportée : " + deviseCible + 
                ". Devises disponibles : " + cachedRates.keySet()
            );
        }

        return taux;
    }

    /**
     * Rafraîchit les taux de change depuis l'API.
     */
    private void refreshRates(String baseDevise) throws IOException, InterruptedException {
        String url = API_BASE_URL + baseDevise.toUpperCase();
        
        HttpRequest request = HttpRequest.newBuilder()
                .uri(URI.create(url))
                .timeout(Duration.ofSeconds(TIMEOUT_SECONDS))
                .GET()
                .build();

        HttpResponse<String> response = httpClient.send(request, 
                HttpResponse.BodyHandlers.ofString());

        if (response.statusCode() != 200) {
            throw new IOException("Erreur API : HTTP " + response.statusCode());
        }

        // Parse la réponse JSON
        JSONObject json = new JSONObject(response.body());
        
        if (!json.getString("result").equals("success")) {
            throw new IOException("API retourne une erreur : " + json.optString("error-type", "inconnue"));
        }

        JSONObject rates = json.getJSONObject("rates");
        cachedRates.clear();
        
        // Stocke tous les taux disponibles
        for (String currency : rates.keySet()) {
            double rate = rates.getDouble(currency);
            cachedRates.put(currency, BigDecimal.valueOf(rate));
        }

        cacheTimestamp = System.currentTimeMillis();
    }

    /**
     * Obtient les taux de conversion TND → EUR et TND → USD.
     * @return Map avec clés "EUR" et "USD"
     */
    public Map<String, BigDecimal> getTauxTndMultiDevises() {
        Map<String, BigDecimal> result = new HashMap<>();
        try {
            result.put("EUR", getTauxDeChange("TND", "EUR"));
            result.put("USD", getTauxDeChange("TND", "USD"));
        } catch (Exception e) {
            // En cas d'erreur, retourne des valeurs par défaut
            result.put("EUR", BigDecimal.valueOf(0.29));
            result.put("USD", BigDecimal.valueOf(0.32));
        }
        return result;
    }

    /**
     * Formate un montant converti avec le symbole de la devise.
     */
    public String formaterMontantConverti(BigDecimal montant, String devise) {
        String symbole = switch (devise.toUpperCase()) {
            case "EUR" -> "€";
            case "USD" -> "$";
            case "TND" -> "DT";
            case "GBP" -> "£";
            default -> devise;
        };
        return String.format("%.3f %s", montant, symbole);
    }

    /**
     * Vérifie la disponibilité de l'API (test de connectivité).
     */
    public boolean isApiDisponible() {
        try {
            refreshRates("TND");
            return true;
        } catch (Exception e) {
            System.err.println("⚠️  API de conversion indisponible : " + e.getMessage());
            return false;
        }
    }
}
