package org.example.service;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.DayOfWeek;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.*;
import java.util.concurrent.ConcurrentHashMap;
import java.util.regex.Matcher;
import java.util.regex.Pattern;

/**
 * Service d'accès aux jours fériés via l'API Nager.Date (https://date.nager.at).
 *
 * Fonctionnalités :
 *  - Récupère les jours fériés d'un pays/année via l'API REST
 *  - Met en cache les résultats pour éviter des appels répétés
 *  - Calcule le nombre de jours ouvrables (hors week-ends et jours fériés)
 *  - Détecte les jours fériés inclus dans une plage de dates
 *
 * Pays par défaut : Tunisie (TN).
 * L'API retourne {} ou [] si le pays/année n'existe pas → silencieusement ignoré.
 */
public class PublicHolidayService {

    private static final String API_BASE = "https://date.nager.at/api/v3/PublicHolidays";
    public static final String DEFAULT_COUNTRY = "TN";

    // Cache en mémoire : clé = "YYYY_CC" → liste de jours fériés
    private final Map<String, List<HolidayEntry>> cache = new ConcurrentHashMap<>();
    private final HttpClient httpClient = HttpClient.newBuilder()
            .connectTimeout(java.time.Duration.ofSeconds(5))
            .build();

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Retourne les jours fériés pour l'année et le pays donnés.
     * Résultats mis en cache.
     */
    public List<HolidayEntry> getHolidays(int year, String countryCode) {
        String key = year + "_" + countryCode.toUpperCase();
        return cache.computeIfAbsent(key, k -> fetchFromApi(year, countryCode));
    }

    /** Raccourci Tunisie. */
    public List<HolidayEntry> getHolidaysTunisie(int year) {
        return getHolidays(year, DEFAULT_COUNTRY);
    }

    /**
     * Compte les jours ouvrables entre start et end (inclus),
     * en excluant les week-ends ET les jours fériés du pays.
     */
    public int countWorkingDays(LocalDate start, LocalDate end, String countryCode) {
        Set<LocalDate> holidays = collectHolidayDates(start, end, countryCode);
        int count = 0;
        for (LocalDate d = start; !d.isAfter(end); d = d.plusDays(1)) {
            if (!isWeekend(d) && !holidays.contains(d)) count++;
        }
        return count;
    }

    /** Raccourci Tunisie. */
    public int countWorkingDaysTunisie(LocalDate start, LocalDate end) {
        return countWorkingDays(start, end, DEFAULT_COUNTRY);
    }

    /**
     * Retourne les jours fériés inclus dans la plage de dates.
     */
    public List<HolidayEntry> findHolidaysInRange(LocalDate start, LocalDate end, String countryCode) {
        List<HolidayEntry> result = new ArrayList<>();
        for (int y = start.getYear(); y <= end.getYear(); y++) {
            for (HolidayEntry h : getHolidays(y, countryCode)) {
                if (!h.date.isBefore(start) && !h.date.isAfter(end)) {
                    result.add(h);
                }
            }
        }
        return result;
    }

    /** Raccourci Tunisie. */
    public List<HolidayEntry> findHolidaysInRangeTunisie(LocalDate start, LocalDate end) {
        return findHolidaysInRange(start, end, DEFAULT_COUNTRY);
    }

    /**
     * Retourne vrai si au moins un jour férié (non week-end) est dans la plage.
     */
    public boolean containsHoliday(LocalDate start, LocalDate end, String countryCode) {
        return !findHolidaysInRange(start, end, countryCode).isEmpty();
    }

    /** Raccourci Tunisie. */
    public boolean containsHolidayTunisie(LocalDate start, LocalDate end) {
        return containsHoliday(start, end, DEFAULT_COUNTRY);
    }

    /** Vide le cache (utile après un changement de paramètres). */
    public void clearCache() {
        cache.clear();
        System.out.println("✓ Cache jours fériés vidé");
    }

    /**
     * Vérifie si l'API est accessible (test de connectivité).
     * Retourne true si l'API répond, false sinon.
     */
    public boolean isApiAvailable() {
        try {
            int year = LocalDate.now().getYear();
            HttpRequest req = HttpRequest.newBuilder()
                    .uri(URI.create(API_BASE + "/" + year + "/" + DEFAULT_COUNTRY))
                    .header("Accept", "application/json")
                    .timeout(java.time.Duration.ofSeconds(3))
                    .GET()
                    .build();
            HttpResponse<String> response = httpClient.send(req, HttpResponse.BodyHandlers.ofString());
            return response.statusCode() == 200;
        } catch (Exception e) {
            return false;
        }
    }

    // =========================================================================
    // MODEL
    // =========================================================================

    /** Représente un jour férié avec sa date et son nom localisé. */
    public static class HolidayEntry {
        public final LocalDate date;
        public final String localName;
        public final String name;

        public HolidayEntry(LocalDate date, String localName, String name) {
            this.date = date;
            this.localName = localName != null ? localName : name;
            this.name = name;
        }

        @Override
        public String toString() {
            return date.format(DateTimeFormatter.ofPattern("dd/MM/yyyy")) + " — " + localName;
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Appelle l'API Nager.Date et parse la réponse JSON.
     * En cas d'erreur réseau, retourne une liste vide (mode hors-ligne tolerant).
     *
     * Exemple de réponse JSON :
     * [{"date":"2026-01-01","localName":"Jour de l'An","name":"New Year's Day",...}, ...]
     */
    private List<HolidayEntry> fetchFromApi(int year, String countryCode) {
        List<HolidayEntry> holidays = new ArrayList<>();
        String url = API_BASE + "/" + year + "/" + countryCode.toUpperCase();

        System.out.printf("  → Téléchargement jours fériés %s %d depuis %s%n", countryCode.toUpperCase(), year, url);

        try {
            HttpRequest request = HttpRequest.newBuilder()
                    .uri(URI.create(url))
                    .header("Accept", "application/json")
                    .timeout(java.time.Duration.ofSeconds(8))
                    .GET()
                    .build();

            HttpResponse<String> response = httpClient.send(request, HttpResponse.BodyHandlers.ofString());

            if (response.statusCode() == 200) {
                holidays = parseJson(response.body());
                System.out.printf("  ✓ %d jours fériés chargés pour %s %d%n", holidays.size(), countryCode.toUpperCase(), year);
            } else {
                System.err.printf("  ⚠️  API répond %d pour %s %d — mode hors-ligne activé%n",
                        response.statusCode(), countryCode.toUpperCase(), year);
            }
        } catch (java.net.http.HttpTimeoutException e) {
            System.err.printf("  ⚠️  Timeout API jours fériés %s %d — mode hors-ligne activé%n",
                    countryCode.toUpperCase(), year);
        } catch (Exception e) {
            System.err.printf("  ⚠️  Erreur API jours fériés : %s — mode hors-ligne activé%n", e.getMessage());
        }

        return holidays;
    }

    /**
     * Parse un tableau JSON de jours fériés.
     * Extrait les champs "date", "localName" et "name" avec des regex.
     * Aucune dépendance externe nécessaire.
     */
    private List<HolidayEntry> parseJson(String json) {
        List<HolidayEntry> result = new ArrayList<>();

        // Découper par objet JSON : {...}
        Pattern objectPattern = Pattern.compile("\\{[^}]+\\}");
        Matcher objectMatcher = objectPattern.matcher(json);

        Pattern datePattern      = Pattern.compile("\"date\"\\s*:\\s*\"(\\d{4}-\\d{2}-\\d{2})\"");
        Pattern localNamePattern = Pattern.compile("\"localName\"\\s*:\\s*\"([^\"]+)\"");
        Pattern namePattern      = Pattern.compile("\"name\"\\s*:\\s*\"([^\"]+)\"");

        while (objectMatcher.find()) {
            String obj = objectMatcher.group();

            Matcher dm = datePattern.matcher(obj);
            Matcher lm = localNamePattern.matcher(obj);
            Matcher nm = namePattern.matcher(obj);

            if (dm.find()) {
                LocalDate date = LocalDate.parse(dm.group(1));
                String localName = lm.find() ? lm.group(1) : null;
                String name = nm.find() ? nm.group(1) : (localName != null ? localName : "Jour férié");
                result.add(new HolidayEntry(date, localName, name));
            }
        }

        return result;
    }

    private Set<LocalDate> collectHolidayDates(LocalDate start, LocalDate end, String countryCode) {
        Set<LocalDate> dates = new HashSet<>();
        for (int y = start.getYear(); y <= end.getYear(); y++) {
            for (HolidayEntry h : getHolidays(y, countryCode)) {
                dates.add(h.date);
            }
        }
        return dates;
    }

    private boolean isWeekend(LocalDate date) {
        DayOfWeek dow = date.getDayOfWeek();
        return dow == DayOfWeek.SATURDAY || dow == DayOfWeek.SUNDAY;
    }
}
