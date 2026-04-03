import service.ReportGenerator.ApiKeyManager;
import java.io.InputStream;
import java.util.Properties;

public class TestKey {
    public static void main(String[] args) {
        System.out.println("Testing API key loading...");
        
        // Test direct file loading
        try {
            InputStream input = TestKey.class.getClassLoader().getResourceAsStream("config.properties");
            if (input != null) {
                System.out.println("✅ config.properties found in classpath");
                Properties props = new Properties();
                props.load(input);
                input.close();
                
                String key = props.getProperty("gemini.api.key");
                System.out.println("Key from file: " + (key != null ? key.substring(0, 8) + "..." + key.substring(key.length() - 4) : "NULL"));
                System.out.println("Key length: " + (key != null ? key.length() : "NULL"));
            } else {
                System.out.println("❌ config.properties NOT found in classpath");
            }
        } catch (Exception e) {
            System.err.println("Error loading config: " + e.getMessage());
        }
        
        // Test ApiKeyManager
        String apiKey = ApiKeyManager.getApiKey();
        System.out.println("ApiKeyManager result: " + (apiKey != null ? apiKey.substring(0, 8) + "..." + apiKey.substring(apiKey.length() - 4) : "NULL"));
    }
}
