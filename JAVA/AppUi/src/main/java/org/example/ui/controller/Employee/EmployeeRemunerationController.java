package org.example.ui.controller.Employee;

import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.stage.FileChooser;
import javafx.stage.Stage;
import org.example.Entity.Deduction;
import org.example.Entity.FichePaie;
import org.example.Entity.Prime;
import org.example.Service.ConversionDevisesService;
import org.example.Service.DeductionService;
import org.example.Service.ExportPdfService;
import org.example.Service.FichePaieService;
import org.example.Service.PrimeService;
import org.example.Utils.BD;
import org.example.model.Employee;

import java.io.File;
import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.SQLException;
import java.time.LocalDate;
import java.util.List;
import java.util.stream.Collectors;

/**
 * Contrôleur pour la vue Rémunération des employés
 * Permet aux employés de consulter leurs fiches de paie, primes et déductions
 */
public class EmployeeRemunerationController {

    // ==================== FXML FIELDS - Statistics ====================
    @FXML private Label lblTotalFiches;
    @FXML private Label lblTotalPrimes;
    @FXML private Label lblTotalDeductions;
    @FXML private Label lblPrimeCount;
    @FXML private Label lblDeductionCount;

    // ==================== FXML FIELDS - Tab 1: Fiches de Paie ====================
    @FXML private TabPane tabPane;
    @FXML private ComboBox<Integer> cbFilterYear;
    @FXML private TableView<FichePaie> tableFiches;
    @FXML private TableColumn<FichePaie, String> colFicheId;
    @FXML private TableColumn<FichePaie, String> colFicheMois;
    @FXML private TableColumn<FichePaie, String> colFicheAnnee;
    @FXML private TableColumn<FichePaie, String> colFicheSalaireBrut;
    @FXML private TableColumn<FichePaie, String> colFicheTotalPrimes;
    @FXML private TableColumn<FichePaie, String> colFicheTotalDeductions;
    @FXML private TableColumn<FichePaie, String> colFicheSalaireNet;
    @FXML private Button btnViewFiche;
    @FXML private Button btnDownloadFiche;

    // ==================== FXML FIELDS - Tab 2: Primes ====================
    @FXML private TableView<Prime> tablePrimes;
    @FXML private TableColumn<Prime, String> colPrimeId;
    @FXML private TableColumn<Prime, String> colPrimeType;
    @FXML private TableColumn<Prime, String> colPrimeMontant;
    @FXML private TableColumn<Prime, String> colPrimeDate;

    // ==================== FXML FIELDS - Tab 3: Déductions ====================
    @FXML private TableView<Deduction> tableDeductions;
    @FXML private TableColumn<Deduction, String> colDeductionId;
    @FXML private TableColumn<Deduction, String> colDeductionType;
    @FXML private TableColumn<Deduction, String> colDeductionMontant;
    @FXML private TableColumn<Deduction, String> colDeductionDate;

    // ==================== FXML FIELDS - Conversion Devises ====================
    @FXML private TextField txtMontantConversionEmployee;
    @FXML private Label lblConversionEUREmployee;
    @FXML private Label lblConversionUSDEmployee;
    @FXML private Label lblApiStatusEmployee;
    @FXML private Button btnRefreshRatesEmployee;

    // ==================== SERVICES ====================
    private FichePaieService fichePaieService;
    private PrimeService primeService;
    private DeductionService deductionService;
    private ConversionDevisesService conversionService;
    private Connection connection;

    // ==================== DATA ====================
    private Employee currentEmployee;
    private final ObservableList<FichePaie> fichesList = FXCollections.observableArrayList();
    private final ObservableList<Prime> primesList = FXCollections.observableArrayList();
    private final ObservableList<Deduction> deductionsList = FXCollections.observableArrayList();

    // ==================== INITIALIZATION ====================

    @FXML
    public void initialize() {
        try {
            // Initialize connection and services
            connection = BD.getInstance().getConnection();
            fichePaieService = new FichePaieService(connection);
            primeService = new PrimeService(connection);
            deductionService = new DeductionService(connection);
            conversionService = new ConversionDevisesService();

            setupFichesTable();
            setupPrimesTable();
            setupDeductionsTable();
            setupYearFilter();
            setupConversionWidget();
            
            // Initialize labels with default values
            if (lblTotalFiches != null) lblTotalFiches.setText("0");
            if (lblTotalPrimes != null) lblTotalPrimes.setText("0.00 DT");
            if (lblTotalDeductions != null) lblTotalDeductions.setText("0.00 DT");
            if (lblPrimeCount != null) lblPrimeCount.setText("0 prime(s)");
            if (lblDeductionCount != null) lblDeductionCount.setText("0 déduction(s)");

        } catch (Exception e) {
            System.err.println("Erreur d'initialisation EmployeeRemunerationController: " + e.getMessage());
            e.printStackTrace();
        }
    }

    public void setCurrentEmployee(Employee employee) {
        this.currentEmployee = employee;
        loadAllData();
    }

    // ==================== TABLE SETUP ====================

    private void setupFichesTable() {
        colFicheId.setCellValueFactory(data -> 
            new SimpleStringProperty(String.valueOf(data.getValue().getIdFiche())));
        colFicheMois.setCellValueFactory(data -> 
            new SimpleStringProperty(data.getValue().getMois()));
        colFicheAnnee.setCellValueFactory(data -> 
            new SimpleStringProperty(String.valueOf(data.getValue().getAnnee())));
        colFicheSalaireBrut.setCellValueFactory(data -> 
            new SimpleStringProperty(formatMontant(data.getValue().getSalaireBrut())));
        colFicheTotalPrimes.setCellValueFactory(data -> 
            new SimpleStringProperty(formatMontant(data.getValue().getTotalPrimes())));
        colFicheTotalDeductions.setCellValueFactory(data -> 
            new SimpleStringProperty(formatMontant(data.getValue().getTotalDeductions())));
        colFicheSalaireNet.setCellValueFactory(data -> 
            new SimpleStringProperty(formatMontant(data.getValue().getSalaireNet())));

        tableFiches.setItems(fichesList);
    }

    private void setupPrimesTable() {
        colPrimeId.setCellValueFactory(data -> 
            new SimpleStringProperty(String.valueOf(data.getValue().getIdPrime())));
        colPrimeType.setCellValueFactory(data -> 
            new SimpleStringProperty(data.getValue().getTypePrime()));
        colPrimeMontant.setCellValueFactory(data -> 
            new SimpleStringProperty(formatMontant(data.getValue().getMontant())));
        colPrimeDate.setCellValueFactory(data -> 
            new SimpleStringProperty(formatDate(data.getValue().getDateAttribution())));

        tablePrimes.setItems(primesList);
    }

    private void setupDeductionsTable() {
        colDeductionId.setCellValueFactory(data -> 
            new SimpleStringProperty(String.valueOf(data.getValue().getIdDeduction())));
        colDeductionType.setCellValueFactory(data -> 
            new SimpleStringProperty(data.getValue().getTypeDeduction()));
        colDeductionMontant.setCellValueFactory(data -> 
            new SimpleStringProperty(formatMontant(data.getValue().getMontant())));
        colDeductionDate.setCellValueFactory(data -> 
            new SimpleStringProperty(formatDate(data.getValue().getDateDeduction())));

        tableDeductions.setItems(deductionsList);
    }

    private void setupYearFilter() {
        if (cbFilterYear == null) return;
        
        // Populate years (current year and previous 5 years)
        ObservableList<Integer> years = FXCollections.observableArrayList();
        int currentYear = LocalDate.now().getYear();
        for (int i = 0; i < 6; i++) {
            years.add(currentYear - i);
        }
        cbFilterYear.setItems(years);

        // Add listener for year filter
        cbFilterYear.valueProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal != null) {
                filterFichesByYear(newVal);
            } else {
                loadFiches();
            }
        });
    }

    // ==================== DATA LOADING ====================

    private void loadAllData() {
        if (currentEmployee == null) return;

        loadFiches();
        loadPrimes();
        loadDeductions();
        updateStatistics();
    }

    private void loadFiches() {
        if (currentEmployee == null) return;
        
        try {
            List<FichePaie> allFiches = fichePaieService.getAllFiches();
            List<FichePaie> employeeFiches = allFiches.stream()
                .filter(f -> f.getIdEmployees() == currentEmployee.getId())
                .collect(Collectors.toList());
            
            fichesList.clear();
            fichesList.addAll(employeeFiches);

        } catch (SQLException e) {
            showError("Erreur de chargement", "Impossible de charger les fiches de paie: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void filterFichesByYear(int year) {
        if (currentEmployee == null) return;
        
        try {
            List<FichePaie> allFiches = fichePaieService.getAllFiches();
            List<FichePaie> filtered = allFiches.stream()
                .filter(f -> f.getIdEmployees() == currentEmployee.getId() && f.getAnnee() == year)
                .collect(Collectors.toList());
            
            fichesList.clear();
            fichesList.addAll(filtered);

        } catch (SQLException e) {
            showError("Erreur de filtrage", e.getMessage());
        }
    }

    private void loadPrimes() {
        if (currentEmployee == null) return;
        
        try {
            List<Prime> allPrimes = primeService.getAllPrimes();
            List<Prime> employeePrimes = allPrimes.stream()
                .filter(p -> p.getIdEmploye() == currentEmployee.getId())
                .collect(Collectors.toList());
            
            primesList.clear();
            primesList.addAll(employeePrimes);
            
            if (lblPrimeCount != null) {
                lblPrimeCount.setText(employeePrimes.size() + " prime(s)");
            }

        } catch (SQLException e) {
            showError("Erreur de chargement", "Impossible de charger les primes: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void loadDeductions() {
        if (currentEmployee == null) return;
        
        try {
            List<Deduction> allDeductions = deductionService.getAllDeductions();
            List<Deduction> employeeDeductions = allDeductions.stream()
                .filter(d -> d.getIdEmploye() == currentEmployee.getId())
                .collect(Collectors.toList());
            
            deductionsList.clear();
            deductionsList.addAll(employeeDeductions);
            
            if (lblDeductionCount != null) {
                lblDeductionCount.setText(employeeDeductions.size() + " déduction(s)");
            }

        } catch (SQLException e) {
            showError("Erreur de chargement", "Impossible de charger les déductions: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void updateStatistics() {
        // Update statistics
        if (lblTotalFiches != null) {
            lblTotalFiches.setText(String.valueOf(fichesList.size()));
        }

        BigDecimal totalPrimes = primesList.stream()
            .map(Prime::getMontant)
            .reduce(BigDecimal.ZERO, BigDecimal::add);
        if (lblTotalPrimes != null) {
            lblTotalPrimes.setText(formatMontant(totalPrimes));
        }

        BigDecimal totalDeductions = deductionsList.stream()
            .map(Deduction::getMontant)
            .reduce(BigDecimal.ZERO, BigDecimal::add);
        if (lblTotalDeductions != null) {
            lblTotalDeductions.setText(formatMontant(totalDeductions));
        }
    }

    // ==================== EVENT HANDLERS ====================

    @FXML
    private void handleViewFiche() {
        FichePaie selected = tableFiches.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showWarning("Aucune sélection", "Veuillez sélectionner une fiche de paie.");
            return;
        }

        // Show details dialog
        showFicheDetails(selected);
    }

    @FXML
    private void handleDownloadFiche() {
        FichePaie selected = tableFiches.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showWarning("Aucune sélection", "Veuillez sélectionner une fiche de paie.");
            return;
        }

        FileChooser fileChooser = new FileChooser();
        fileChooser.setTitle("Enregistrer la fiche de paie en PDF");
        fileChooser.getExtensionFilters().add(
                new FileChooser.ExtensionFilter("Fichier PDF (*.pdf)", "*.pdf"));
        fileChooser.setInitialFileName(
                "FichePaie_" + selected.getMois() + "_" + selected.getAnnee() + ".pdf");

        Stage stage = (Stage) tableFiches.getScene().getWindow();
        File file = fileChooser.showSaveDialog(stage);
        if (file == null) return; // annulé par l'utilisateur

        try {
            String nomComplet = currentEmployee != null
                    ? currentEmployee.getFirstName() + " " + currentEmployee.getLastName()
                    : "Employé";
            String poste = currentEmployee != null ? currentEmployee.getJobTitle() : null;

            new ExportPdfService().exportFichePaiePDF(selected, nomComplet, poste, file.toPath());
            showInfo("Téléchargement réussi",
                    "Fiche de paie exportée avec succès :\n" + file.getAbsolutePath());
        } catch (Exception e) {
            showError("Erreur d'export PDF",
                    "Impossible de générer le fichier PDF :\n" + e.getMessage());
            e.printStackTrace();
        }
    }

    // ==================== HELPER METHODS ====================

    private void showFicheDetails(FichePaie fiche) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Détails de la Fiche de Paie");
        alert.setHeaderText(fiche.getMois() + " " + fiche.getAnnee());
        
        String content = String.format(
            "Salaire Brut: %s\n" +
            "Total Primes: %s\n" +
            "Total Déductions: %s\n" +
            "─────────────────────\n" +
            "Salaire Net: %s",
            formatMontant(fiche.getSalaireBrut()),
            formatMontant(fiche.getTotalPrimes()),
            formatMontant(fiche.getTotalDeductions()),
            formatMontant(fiche.getSalaireNet())
        );
        
        alert.setContentText(content);
        alert.showAndWait();
    }

    private String formatMontant(BigDecimal montant) {
        if (montant == null) return "0.00 DT";
        return String.format("%.2f DT", montant);
    }

    private String formatDate(LocalDate date) {
        if (date == null) return "";
        return date.toString();
    }

    private void showError(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private void showWarning(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.WARNING);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private void showInfo(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    // ==================== CONVERSION DE DEVISES ====================

    private void setupConversionWidget() {
        if (txtMontantConversionEmployee == null) return;

        // Recalcul automatique lors de la saisie
        txtMontantConversionEmployee.textProperty().addListener((obs, oldVal, newVal) -> {
            calculateConversionEmployee();
        });

        // Charge les taux initiaux
        loadExchangeRatesEmployee();
    }

    @FXML
    private void handleRefreshRatesEmployee() {
        loadExchangeRatesEmployee();
        showInfo("Taux actualisés", "Les taux de change ont été mis à jour.");
    }

    private void loadExchangeRatesEmployee() {
        try {
            BigDecimal tauxEUR = conversionService.getTauxDeChange("TND", "EUR");
            BigDecimal tauxUSD = conversionService.getTauxDeChange("TND", "USD");

            if (lblApiStatusEmployee != null) {
                lblApiStatusEmployee.setText(String.format("Taux: 1 TND = %.4f EUR · %.4f USD", tauxEUR, tauxUSD));
            }

            // Recalcule la conversion si un montant est saisi
            calculateConversionEmployee();

        } catch (Exception e) {
            if (lblApiStatusEmployee != null) {
                lblApiStatusEmployee.setText("⚠️ API indisponible — Taux: 1 TND ≈ 0.29 EUR · 0.32 USD");
            }
        }
    }

    private void calculateConversionEmployee() {
        if (txtMontantConversionEmployee == null || lblConversionEUREmployee == null || lblConversionUSDEmployee == null)
            return;

        String text = txtMontantConversionEmployee.getText().replace(",", ".").trim();
        if (text.isEmpty()) {
            lblConversionEUREmployee.setText("—");
            lblConversionUSDEmployee.setText("—");
            return;
        }

        try {
            BigDecimal montantTND = new BigDecimal(text);
            BigDecimal montantEUR = conversionService.convertirTndVersEur(montantTND);
            BigDecimal montantUSD = conversionService.convertirTndVersUsd(montantTND);

            lblConversionEUREmployee.setText(String.format("%.3f €", montantEUR));
            lblConversionUSDEmployee.setText(String.format("%.3f $", montantUSD));

        } catch (NumberFormatException e) {
            lblConversionEUREmployee.setText("—");
            lblConversionUSDEmployee.setText("—");
        } catch (Exception e) {
            lblConversionEUREmployee.setText("Erreur");
            lblConversionUSDEmployee.setText("Erreur");
        }
    }
}
