package org.example.ui.util;

import javafx.collections.ObservableList;
import javafx.stage.FileChooser;
import javafx.stage.Stage;
import java.io.File;
import java.io.FileWriter;
import java.io.IOException;
import java.lang.reflect.Field;
import java.util.List;
import java.util.stream.Collectors;

public class CSVExporter {

    public static <T> void exportToCSV(ObservableList<T> data, List<String> columns, List<String> fieldNames,
            String defaultFileName) {
        if (data == null || data.isEmpty())
            return;

        FileChooser fileChooser = new FileChooser();
        fileChooser.setTitle("Save CSV File");
        fileChooser.setInitialFileName(defaultFileName);
        fileChooser.getExtensionFilters().add(new FileChooser.ExtensionFilter("CSV Files", "*.csv"));

        File file = fileChooser.showSaveDialog(null);
        if (file != null) {
            try (FileWriter writer = new FileWriter(file)) {
                // Header
                writer.write(String.join(",", columns) + "\n");

                // Data
                for (T item : data) {
                    String row = fieldNames.stream().map(fieldName -> {
                        try {
                            Field field = findField(item.getClass(), fieldName);
                            field.setAccessible(true);
                            Object value = field.get(item);
                            return value != null ? "\"" + value.toString().replace("\"", "\"\"") + "\"" : "";
                        } catch (Exception e) {
                            return "";
                        }
                    }).collect(Collectors.joining(","));
                    writer.write(row + "\n");
                }
                System.out.println("✅ Exported " + data.size() + " rows to " + file.getAbsolutePath());
            } catch (IOException e) {
                System.err.println("❌ Export failed: " + e.getMessage());
            }
        }
    }

    private static Field findField(Class<?> clazz, String fieldName) throws NoSuchFieldException {
        try {
            return clazz.getDeclaredField(fieldName);
        } catch (NoSuchFieldException e) {
            if (clazz.getSuperclass() != null) {
                return findField(clazz.getSuperclass(), fieldName);
            }
            throw e;
        }
    }
}
