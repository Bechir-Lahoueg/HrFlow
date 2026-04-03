package service.ReportGenerator;

import java.util.Properties;
import java.io.InputStream;

/**
 * Shared utility class for API key management
 * Ensures all agents use the same API key retrieval logic
 */
public class ApiKeyManager {

    private static final String CONFIG_FILE = "config.properties";

    public enum LlmProvider {
        GEMINI,
        GROQ
    }

    public static LlmProvider getLlmProvider() {
        String provider = null;

        provider = System.getProperty("llm.provider");
        if (provider == null || provider.isEmpty()) {
            try {
                InputStream input = ApiKeyManager.class.getClassLoader().getResourceAsStream(CONFIG_FILE);
                if (input != null) {
                    Properties props = new Properties();
                    props.load(input);
                    input.close();
                    provider = props.getProperty("llm.provider");
                }
            } catch (Exception e) {
                // ignore
            }
        }

        if (provider == null || provider.trim().isEmpty()) {
            return LlmProvider.GEMINI;
        }

        String normalized = provider.trim().toLowerCase();
        if (normalized.equals("groq")) {
            return LlmProvider.GROQ;
        }

        return LlmProvider.GEMINI;
    }

    /**
     * Retrieves API key from environment variable, system property, or config file
     * 
     * @return API key or null if not found
     */
    public static String getApiKey() {
        System.out.println("DEBUG: [ApiKeyManager] Starting API key retrieval...");

        String apiKey = null;
        String source = null;

        // 1. Check environment variable
        apiKey = System.getenv("GEMINI_API_KEY");
        if (apiKey != null && !apiKey.isEmpty()) {
            source = "ENVIRONMENT (GEMINI_API_KEY)";
        } else {
            // 2. Check system property
            apiKey = System.getProperty("gemini.api.key");
            if (apiKey != null && !apiKey.isEmpty()) {
                source = "SYSTEM PROPERTY (gemini.api.key)";
            } else {
                // 3. Check config.properties file
                try {
                    InputStream input = ApiKeyManager.class.getClassLoader().getResourceAsStream(CONFIG_FILE);
                    if (input != null) {
                        Properties props = new Properties();
                        props.load(input);
                        input.close();
                        apiKey = props.getProperty("gemini.api.key");
                        if (apiKey != null && !apiKey.isEmpty()) {
                            source = "FILE (" + CONFIG_FILE + ")";
                        }
                    }
                } catch (Exception e) {
                    System.err.println("DEBUG: [ApiKeyManager] Error reading " + CONFIG_FILE + ": " + e.getMessage());
                }
            }
        }

        if (apiKey != null && !apiKey.isEmpty()) {
            apiKey = apiKey.trim(); // CRITICAL: remove hidden characters
            String start = apiKey.substring(0, Math.min(8, apiKey.length()));
            String end = apiKey.length() > 4 ? apiKey.substring(apiKey.length() - 4) : "****";
            System.out.println("DEBUG: [ApiKeyManager] Loaded key from " + source);
            System.out.println(
                    "DEBUG: [ApiKeyManager] Key ID: " + start + "..." + end + " (Length: " + apiKey.length() + ")");
            return apiKey;
        }

        System.err.println("CRITICAL: [ApiKeyManager] No Gemini API key found in config.properties or system properties!");
        return null;
    }

    public static String getGroqApiKey() {
        System.out.println("DEBUG: [ApiKeyManager] Starting Groq API key retrieval...");

        String apiKey = null;
        String source = null;

        apiKey = System.getenv("GROQ_API_KEY");
        if (apiKey != null && !apiKey.isEmpty()) {
            source = "ENVIRONMENT (GROQ_API_KEY)";
        } else {
            apiKey = System.getProperty("groq.api.key");
            if (apiKey != null && !apiKey.isEmpty()) {
                source = "SYSTEM PROPERTY (groq.api.key)";
            } else {
                try {
                    InputStream input = ApiKeyManager.class.getClassLoader().getResourceAsStream(CONFIG_FILE);
                    if (input != null) {
                        Properties props = new Properties();
                        props.load(input);
                        input.close();
                        apiKey = props.getProperty("groq.api.key");
                        if (apiKey != null && !apiKey.isEmpty()) {
                            source = "FILE (" + CONFIG_FILE + ")";
                        }
                    }
                } catch (Exception e) {
                    System.err.println("DEBUG: [ApiKeyManager] Error reading " + CONFIG_FILE + ": " + e.getMessage());
                }
            }
        }

        if (apiKey != null && !apiKey.isEmpty()) {
            apiKey = apiKey.trim();
            String start = apiKey.substring(0, Math.min(8, apiKey.length()));
            String end = apiKey.length() > 4 ? apiKey.substring(apiKey.length() - 4) : "****";
            System.out.println("DEBUG: [ApiKeyManager] Loaded Groq key from " + source);
            System.out.println(
                    "DEBUG: [ApiKeyManager] Key ID: " + start + "..." + end + " (Length: " + apiKey.length() + ")");
            return apiKey;
        }

        System.err.println("CRITICAL: [ApiKeyManager] No Groq API key found in config.properties or system properties!");
        return null;
    }
}
