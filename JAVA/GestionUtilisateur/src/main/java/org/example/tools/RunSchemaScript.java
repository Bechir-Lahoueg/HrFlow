package org.example.tools;

import java.nio.file.Files;
import java.nio.file.Path;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;

public class RunSchemaScript {
    public static void main(String[] args) throws Exception {
        String jdbcUrl;
        String sqlFilePath;
        String dbUser;
        String dbPassword;

        if (args.length >= 2) {
            jdbcUrl = args[0];
            sqlFilePath = args[1];
            dbUser = args.length > 2 ? args[2] : "";
            dbPassword = args.length > 3 ? args[3] : "";
        } else {
            jdbcUrl = getenvOrEmpty("SCHEMA_JDBC_URL");
            sqlFilePath = getenvOrEmpty("SCHEMA_SQL_FILE");
            dbUser = getenvOrEmpty("SCHEMA_DB_USER");
            dbPassword = getenvOrEmpty("SCHEMA_DB_PASSWORD");
        }

        if (jdbcUrl.isEmpty() || sqlFilePath.isEmpty()) {
            System.err.println("Usage: RunSchemaScript <jdbcUrl> <sqlFilePath> [dbUser] [dbPassword]");
            System.err.println("Or set env vars: SCHEMA_JDBC_URL, SCHEMA_SQL_FILE, SCHEMA_DB_USER, SCHEMA_DB_PASSWORD");
            System.exit(1);
            return;
        }

        String sql = Files.readString(Path.of(sqlFilePath));
        List<String> statements = splitStatements(sql);

        int ok = 0;
        int failed = 0;

        try (Connection connection = DriverManager.getConnection(jdbcUrl, dbUser, dbPassword);
             Statement statement = connection.createStatement()) {

            for (String raw : statements) {
                String stmt = raw.trim();
                if (stmt.isEmpty()) {
                    continue;
                }

                try {
                    statement.execute(stmt);
                    ok++;
                } catch (SQLException ex) {
                    // Allow script to continue so one non-critical statement does not block all tables
                    failed++;
                    System.err.println("SQL failed: " + ex.getMessage());
                    System.err.println("Statement: " + shorten(stmt));
                }
            }
        }

        System.out.println("SQL execution complete. Success=" + ok + ", Failed=" + failed);

        if (failed > 0) {
            System.exit(2);
        }
    }

    private static List<String> splitStatements(String sql) {
        List<String> out = new ArrayList<>();
        StringBuilder current = new StringBuilder();
        boolean inSingle = false;
        boolean inDouble = false;

        String[] lines = sql.split("\\R");
        for (String line : lines) {
            String trimmed = line.trim();
            if (trimmed.startsWith("--")) {
                continue;
            }

            for (int i = 0; i < line.length(); i++) {
                char c = line.charAt(i);

                if (c == '\'' && !inDouble) {
                    inSingle = !inSingle;
                } else if (c == '"' && !inSingle) {
                    inDouble = !inDouble;
                }

                if (c == ';' && !inSingle && !inDouble) {
                    out.add(current.toString());
                    current.setLength(0);
                } else {
                    current.append(c);
                }
            }
            current.append('\n');
        }

        if (current.length() > 0) {
            out.add(current.toString());
        }

        return out;
    }

    private static String shorten(String s) {
        final int max = 200;
        String oneLine = s.replace('\n', ' ').replace('\r', ' ');
        return oneLine.length() <= max ? oneLine : oneLine.substring(0, max) + "...";
    }

    private static String getenvOrEmpty(String key) {
        String value = System.getenv(key);
        return value == null ? "" : value;
    }
}
