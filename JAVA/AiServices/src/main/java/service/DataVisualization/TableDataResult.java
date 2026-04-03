package service.DataVisualization;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.ObservableMap;

/**
 * Table Data Result - Contains table query results
 */
public class TableDataResult {
    private ObservableList<ObservableMap<String, Object>> data;
    private java.util.List<String> columns;
    private String errorMessage;
    private String generatedSql;

    public TableDataResult() {
        this.data = FXCollections.observableArrayList();
        this.columns = new java.util.ArrayList<>();
        this.errorMessage = null;
        this.generatedSql = null;
    }

    public ObservableList<ObservableMap<String, Object>> getData() {
        return data;
    }

    public java.util.List<String> getColumns() {
        return columns;
    }

    public void setData(ObservableList<ObservableMap<String, Object>> data) {
        this.data = data;
    }

    public void setColumns(java.util.List<String> columns) {
        this.columns = columns;
    }

    public String getErrorMessage() {
        return errorMessage;
    }

    public void setErrorMessage(String errorMessage) {
        this.errorMessage = errorMessage;
    }

    public boolean hasError() {
        return errorMessage != null && !errorMessage.isEmpty();
    }

    public String getGeneratedSql() {
        return generatedSql;
    }

    public void setGeneratedSql(String generatedSql) {
        this.generatedSql = generatedSql;
    }
}
