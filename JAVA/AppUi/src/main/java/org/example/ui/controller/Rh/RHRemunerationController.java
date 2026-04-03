package org.example.ui.controller.Rh;

import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.scene.control.*;
import javafx.scene.layout.GridPane;
import javafx.stage.FileChooser;
import javafx.stage.Stage;
import org.example.Entity.Deduction;
import org.example.Entity.FichePaie;
import org.example.Entity.Prime;
import org.example.Service.CalculFiscalService;
import org.example.Service.ConversionDevisesService;
import org.example.Service.DeductionService;
import org.example.Service.ExportPdfService;
import org.example.Service.FichePaieService;
import org.example.Service.PrimeService;
import org.example.Utils.BD;
import org.example.controller.EmployeeController;
import org.example.model.Employee;
import org.example.model.User;

import java.io.File;
import java.math.BigDecimal;
import java.math.RoundingMode;
import java.sql.Connection;
import java.sql.SQLException;
import java.time.LocalDate;
import java.util.List;
import java.util.Optional;

/**
 * Contrôleur pour la gestion de la rémunération côté RH
 * Permet de gérer les fiches de paie, primes et déductions de tous les employés
 */
public class RHRemunerationController {

    // ==================== FXML FIELDS - Statistics ====================
    @FXML private Label lblTotalFiches;
    @FXML private Label lblTotalPrimes;
    @FXML private Label lblTotalDeductions;
    @FXML private Label lblMasseSalariale;

    // ==================== FXML FIELDS - Tab 1: Fiches de Paie ====================
    @FXML private TabPane tabPane;
    @FXML private TextField txtSearchFiche;
    @FXML private TableView<FichePaie> tableFiches;
    @FXML private TableColumn<FichePaie, String> colFicheId;
    @FXML private TableColumn<FichePaie, String> colFicheEmploye;
    @FXML private TableColumn<FichePaie, String> colFicheMois;
    @FXML private TableColumn<FichePaie, String> colFicheAnnee;
    @FXML private TableColumn<FichePaie, String> colFicheSalaireBrut;
    @FXML private TableColumn<FichePaie, String> colFicheTotalPrimes;
    @FXML private TableColumn<FichePaie, String> colFicheTotalDeductions;
    @FXML private TableColumn<FichePaie, String> colFicheSalaireNet;
    @FXML private Button btnAddFiche;
    @FXML private Button btnEditFiche;
    @FXML private Button btnDeleteFiche;
    @FXML private Button btnGenerateFiche;
    @FXML private Button btnRefreshFiches;
    @FXML private Button btnExportPdfFiche;

    // ==================== FXML FIELDS - Tab 2: Primes ====================
    @FXML private TextField txtSearchPrime;
    @FXML private TableView<Prime> tablePrimes;
    @FXML private TableColumn<Prime, String> colPrimeId;
    @FXML private TableColumn<Prime, String> colPrimeEmploye;
    @FXML private TableColumn<Prime, String> colPrimeType;
    @FXML private TableColumn<Prime, String> colPrimeMontant;
    @FXML private TableColumn<Prime, String> colPrimeDate;
    @FXML private Button btnAddPrime;
    @FXML private Button btnEditPrime;
    @FXML private Button btnDeletePrime;
    @FXML private Button btnRefreshPrimes;

    // ==================== FXML FIELDS - Tab 3: Déductions ====================
    @FXML private TextField txtSearchDeduction;
    @FXML private TableView<Deduction> tableDeductions;
    @FXML private TableColumn<Deduction, String> colDeductionId;
    @FXML private TableColumn<Deduction, String> colDeductionEmploye;
    @FXML private TableColumn<Deduction, String> colDeductionType;
    @FXML private TableColumn<Deduction, String> colDeductionMontant;
    @FXML private TableColumn<Deduction, String> colDeductionDate;
    @FXML private Button btnAddDeduction;
    @FXML private Button btnEditDeduction;
    @FXML private Button btnDeleteDeduction;
    @FXML private Button btnRefreshDeductions;

    // ==================== FXML FIELDS - Tab 4: Conversion Devises ====================
    @FXML private TextField txtMontantConversion;
    @FXML private Label lblConversionEUR;
    @FXML private Label lblConversionUSD;
    @FXML private Label lblTauxEUR;
    @FXML private Label lblTauxUSD;
    @FXML private Label lblLastUpdate;
    @FXML private Label lblApiStatus;
    @FXML private Button btnRefreshRates;

    // ==================== SERVICES ====================
    private FichePaieService fichePaieService;
    private PrimeService primeService;
    private DeductionService deductionService;
    private ConversionDevisesService conversionService;
    private EmployeeController employeeController;
    private Connection connection;

    // ==================== DATA ====================
    private User currentUser;
    private final ObservableList<FichePaie> fichesList = FXCollections.observableArrayList();
    private final ObservableList<Prime> primesList = FXCollections.observableArrayList();
    private final ObservableList<Deduction> deductionsList = FXCollections.observableArrayList();
    private List<Employee> allEmployees;

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
            employeeController = new EmployeeController();

            setupFichesTable();
            setupPrimesTable();
            setupDeductionsTable();
            setupSearchFilters();
            setupConversionWidget();

        } catch (Exception e) {
            showError("Erreur d'initialisation", e.getMessage());
            e.printStackTrace();
        }
    }

    public void setCurrentUser(User user) {
        this.currentUser = user;
        loadAllEmployees();
        loadAllData();
    }

    // ==================== TABLE SETUP ====================

    private void setupFichesTable() {
        colFicheId.setCellValueFactory(data -> 
            new SimpleStringProperty(String.valueOf(data.getValue().getIdFiche())));
        colFicheEmploye.setCellValueFactory(data -> 
            new SimpleStringProperty(getEmployeeName(data.getValue().getIdEmployees())));
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
        colPrimeEmploye.setCellValueFactory(data -> 
            new SimpleStringProperty(getEmployeeName(data.getValue().getIdEmploye())));
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
        colDeductionEmploye.setCellValueFactory(data -> 
            new SimpleStringProperty(getEmployeeName(data.getValue().getIdEmploye())));
        colDeductionType.setCellValueFactory(data -> 
            new SimpleStringProperty(data.getValue().getTypeDeduction()));
        colDeductionMontant.setCellValueFactory(data -> 
            new SimpleStringProperty(formatMontant(data.getValue().getMontant())));
        colDeductionDate.setCellValueFactory(data -> 
            new SimpleStringProperty(formatDate(data.getValue().getDateDeduction())));

        tableDeductions.setItems(deductionsList);
    }

    private void setupSearchFilters() {
        // Search filter for Fiches
        txtSearchFiche.textProperty().addListener((obs, oldVal, newVal) -> {
            // TODO: Implement search filter
        });

        // Search filter for Primes
        txtSearchPrime.textProperty().addListener((obs, oldVal, newVal) -> {
            // TODO: Implement search filter
        });

        // Search filter for Deductions
        txtSearchDeduction.textProperty().addListener((obs, oldVal, newVal) -> {
            // TODO: Implement search filter
        });
    }

    // ==================== DATA LOADING ====================

    private void loadAllEmployees() {
        try {
            allEmployees = employeeController.handleListMyEmployees(currentUser);
        } catch (Exception e) {
            showError("Erreur", "Impossible de charger les employés: " + e.getMessage());
        }
    }

    private void loadAllData() {
        loadFiches();
        loadPrimes();
        loadDeductions();
        updateStatistics();
    }

    private void loadFiches() {
        try {
            List<FichePaie> fiches = fichePaieService.getAllFiches();
            fichesList.clear();
            fichesList.addAll(fiches);
        } catch (SQLException e) {
            showError("Erreur de chargement", "Impossible de charger les fiches de paie: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void loadPrimes() {
        try {
            List<Prime> primes = primeService.getAllPrimes();
            primesList.clear();
            primesList.addAll(primes);
        } catch (SQLException e) {
            showError("Erreur de chargement", "Impossible de charger les primes: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void loadDeductions() {
        try {
            List<Deduction> deductions = deductionService.getAllDeductions();
            deductionsList.clear();
            deductionsList.addAll(deductions);
        } catch (SQLException e) {
            showError("Erreur de chargement", "Impossible de charger les déductions: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void updateStatistics() {
        // Nombre de fiches
        lblTotalFiches.setText(String.valueOf(fichesList.size()));
        
        // Total des primes en DT
        BigDecimal totalPrimes = primesList.stream()
                .map(Prime::getMontant)
                .reduce(BigDecimal.ZERO, BigDecimal::add);
        lblTotalPrimes.setText(formatMontant(totalPrimes));
        
        // Total des déductions en DT
        BigDecimal totalDeductions = deductionsList.stream()
                .map(Deduction::getMontant)
                .reduce(BigDecimal.ZERO, BigDecimal::add);
        lblTotalDeductions.setText(formatMontant(totalDeductions));
        
        // Masse salariale (sum des salaires nets)
        BigDecimal masseSalariale = fichesList.stream()
                .map(FichePaie::getSalaireNet)
                .reduce(BigDecimal.ZERO, BigDecimal::add);
        if (lblMasseSalariale != null) {
            lblMasseSalariale.setText(formatMontant(masseSalariale));
        }
    }

    // ==================== EVENT HANDLERS - Fiches de Paie ====================

    @FXML
    private void handleAddFiche() {
        Dialog<FichePaie> dialog = createFicheDialog(null);
        Optional<FichePaie> result = dialog.showAndWait();

        result.ifPresent(fiche -> {
            try {
                fichePaieService.addFiche(fiche);
                loadFiches();
                updateStatistics();
                showInfo("Succès", "Fiche de paie ajoutée avec succès!");
            } catch (SQLException e) {
                showError("Erreur", "Impossible d'ajouter la fiche de paie: " + e.getMessage());
            }
        });
    }

    @FXML
    private void handleEditFiche() {
        FichePaie selected = tableFiches.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showWarning("Aucune sélection", "Veuillez sélectionner une fiche de paie.");
            return;
        }

        Dialog<FichePaie> dialog = createFicheDialog(selected);
        Optional<FichePaie> result = dialog.showAndWait();

        result.ifPresent(fiche -> {
            try {
                fichePaieService.updateFiche(fiche);
                loadFiches();
                showInfo("Succès", "Fiche de paie modifiée avec succès!");
            } catch (SQLException e) {
                showError("Erreur", "Impossible de modifier la fiche de paie: " + e.getMessage());
            }
        });
    }

    @FXML
    private void handleDeleteFiche() {
        FichePaie selected = tableFiches.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showWarning("Aucune sélection", "Veuillez sélectionner une fiche de paie.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Supprimer la fiche de paie ?");
        confirm.setContentText("Êtes-vous sûr de vouloir supprimer cette fiche de paie ?");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                try {
                    fichePaieService.deleteFiche(selected.getIdFiche());
                    loadFiches();
                    updateStatistics();
                    showInfo("Succès", "Fiche de paie supprimée avec succès!");
                } catch (SQLException e) {
                    showError("Erreur", "Impossible de supprimer la fiche de paie: " + e.getMessage());
                }
            }
        });
    }

    @FXML
    private void handleExportPdfFiche() {
        FichePaie selected = tableFiches.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showWarning("Aucune sélection", "Veuillez sélectionner une fiche de paie à exporter.");
            return;
        }

        FileChooser fc = new FileChooser();
        fc.setTitle("Exporter la fiche de paie en PDF");
        fc.getExtensionFilters().add(new FileChooser.ExtensionFilter("PDF", "*.pdf"));
        
        Employee emp = getEmployeeById(selected.getIdEmployees());
        String employeeName = emp != null ? emp.getLastName() : "Employe";
        fc.setInitialFileName("FichePaie_" + employeeName + "_" + selected.getMois() + "_" + selected.getAnnee() + ".pdf");
        
        Stage stage = (Stage) tableFiches.getScene().getWindow();
        File file = fc.showSaveDialog(stage);
        
        if (file != null) {
            try {
                String nomComplet = emp != null ? emp.getFirstName() + " " + emp.getLastName() : "Employé";
                String poste = emp != null ? emp.getJobTitle() : null;
                
                new ExportPdfService().exportFichePaiePDF(selected, nomComplet, poste, file.toPath());
                showInfo("Export PDF réussi", "Fiche de paie exportée vers :\n" + file.getAbsolutePath());
            } catch (Exception e) {
                showError("Erreur d'export", "Impossible de générer le PDF :\n" + e.getMessage());
                e.printStackTrace();
            }
        }
    }

    @FXML
    private void handleGenerateFiche() {
        CalculFiscalService fiscal = new CalculFiscalService();

        // ── Dialog de simulation / génération ──────────────────────────────
        Dialog<ButtonType> dialog = new Dialog<>();
        dialog.setTitle("Générer une Fiche de Paie");
        dialog.setHeaderText("Calcul automatique CNSS / AMG / IRPP — Barème Tunisie 2025");

        ButtonType btnGenerer  = new ButtonType("Générer & Enregistrer", ButtonBar.ButtonData.OK_DONE);
        ButtonType btnExportPdf = new ButtonType("Simuler + Exporter PDF", ButtonBar.ButtonData.OTHER);
        dialog.getDialogPane().getButtonTypes().addAll(btnGenerer, btnExportPdf, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(8);
        grid.setPadding(new Insets(20, 120, 10, 10));

        ComboBox<Employee> cbEmploye = new ComboBox<>(FXCollections.observableArrayList(allEmployees));
        cbEmploye.setConverter(new javafx.util.StringConverter<Employee>() {
            @Override public String toString(Employee e) { return e == null ? "" : e.getFirstName() + " " + e.getLastName(); }
            @Override public Employee fromString(String s) { return null; }
        });
        cbEmploye.setMaxWidth(Double.MAX_VALUE);

        ComboBox<String> cbMois = new ComboBox<>(FXCollections.observableArrayList(
            "Janvier","Février","Mars","Avril","Mai","Juin",
            "Juillet","Août","Septembre","Octobre","Novembre","Décembre"
        ));
        cbMois.setValue(LocalDate.now().getMonth().getDisplayName(
            java.time.format.TextStyle.FULL, java.util.Locale.FRENCH));

        TextField txtAnnee       = new TextField(String.valueOf(LocalDate.now().getYear()));
        TextField txtSalaireBrut = new TextField();
        TextField txtPrimes      = new TextField("0");
        txtSalaireBrut.setPromptText("Ex: 1800.000");

        // Résultats calculés (lecture seule)
        Label lblCNSS   = new Label("—");
        Label lblAMG    = new Label("—");
        Label lblIRPP   = new Label("—");
        Label lblNet    = new Label("—");
        lblNet.setStyle("-fx-font-weight: bold; -fx-text-fill: #2e5c8a;");

        // Recalcul en temps réel à chaque frappe
        Runnable recalculer = () -> {
            try {
                String brutTxt = txtSalaireBrut.getText().replace(",", ".").trim();
                String primTxt = txtPrimes.getText().replace(",", ".").trim();
                if (brutTxt.isEmpty()) return;
                BigDecimal brut   = new BigDecimal(brutTxt);
                BigDecimal primes = primTxt.isEmpty() ? BigDecimal.ZERO : new BigDecimal(primTxt);
                BigDecimal[] d = fiscal.calculerDeductionsFiscales(brut);
                lblCNSS.setText(String.format("%.3f DT", d[0]));
                lblAMG.setText(String.format("%.3f DT",  d[1]));
                lblIRPP.setText(String.format("%.3f DT", d[2]));
                BigDecimal net = brut.add(primes).subtract(d[3]).setScale(3, RoundingMode.HALF_UP);
                lblNet.setText(String.format("%.3f DT", net));
            } catch (NumberFormatException ignored) {
                lblCNSS.setText("—"); lblAMG.setText("—"); lblIRPP.setText("—"); lblNet.setText("—");
            }
        };
        txtSalaireBrut.textProperty().addListener((o, ov, nv) -> recalculer.run());
        txtPrimes.textProperty().addListener((o, ov, nv) -> recalculer.run());

        int row = 0;
        grid.add(new Label("Employé :"),        0, row); grid.add(cbEmploye,       1, row++); 
        grid.add(new Label("Mois :"),           0, row); grid.add(cbMois,          1, row++);
        grid.add(new Label("Année :"),          0, row); grid.add(txtAnnee,        1, row++);
        grid.add(new Label("Salaire Brut (DT):"), 0, row); grid.add(txtSalaireBrut, 1, row++);
        grid.add(new Label("Primes (DT) :"),    0, row); grid.add(txtPrimes,       1, row++);
        grid.add(new Separator(),               0, row, 2, 1); row++;
        grid.add(new Label("→ CNSS (9.18%) :"), 0, row); grid.add(lblCNSS,         1, row++);
        grid.add(new Label("→ AMG (4%) :"),     0, row); grid.add(lblAMG,          1, row++);
        grid.add(new Label("→ IRPP :"),         0, row); grid.add(lblIRPP,         1, row++);
        grid.add(new Separator(),               0, row, 2, 1); row++;
        grid.add(new Label("Salaire Net :"),    0, row); grid.add(lblNet,          1, row);

        dialog.getDialogPane().setContent(grid);
        Optional<ButtonType> result = dialog.showAndWait();
        if (!result.isPresent() || result.get() == ButtonType.CANCEL) return;

        boolean enregistrer = result.get() == btnGenerer;
        boolean exportPdf   = result.get() == btnExportPdf;

        try {
            Employee emp = cbEmploye.getValue();
            if (emp == null)              throw new IllegalArgumentException("Veuillez sélectionner un employé.");
            if (cbMois.getValue() == null) throw new IllegalArgumentException("Veuillez sélectionner un mois.");
            String brutTxt = txtSalaireBrut.getText().replace(",", ".").trim();
            if (brutTxt.isEmpty())        throw new IllegalArgumentException("Saisissez le salaire brut.");

            BigDecimal brut   = new BigDecimal(brutTxt);
            BigDecimal primes = new BigDecimal(txtPrimes.getText().replace(",", ".").trim());
            BigDecimal[] d    = fiscal.calculerDeductionsFiscales(brut);
            BigDecimal totalDeductions = d[3]; // cnss + amg + irpp
            BigDecimal net = brut.add(primes).subtract(totalDeductions).setScale(3, RoundingMode.HALF_UP);
            int annee = Integer.parseInt(txtAnnee.getText().trim());

            FichePaie fiche = new FichePaie(cbMois.getValue(), annee,
                    brut, primes, totalDeductions, net, emp.getId());

            if (enregistrer) {
                // Persiste la fiche de paie
                fichePaieService.addFiche(fiche);

                // Crée automatiquement les déductions CNSS, AMG, IRPP
                DeductionService ds = new DeductionService(connection);
                ds.addDeduction(fiscal.genererDeductionCNSS(brut, emp.getId()));
                ds.addDeduction(fiscal.genererDeductionAMG(brut, emp.getId()));
                BigDecimal baseIrpp = brut.subtract(d[0]).subtract(d[1]);
                ds.addDeduction(fiscal.genererDeductionIRPP(baseIrpp, emp.getId()));

                loadAllData();
                showInfo("Succès",
                    "Fiche générée pour " + emp.getFirstName() + " " + emp.getLastName()
                    + "\nCNSS: " + String.format("%.3f DT", d[0])
                    + "  AMG: " + String.format("%.3f DT", d[1])
                    + "  IRPP: " + String.format("%.3f DT", d[2])
                    + "\nSalaire Net: " + String.format("%.3f DT", net));

            } else if (exportPdf) {
                // Simulation seulement + export PDF
                FileChooser fc = new FileChooser();
                fc.setTitle("Enregistrer la simulation PDF");
                fc.getExtensionFilters().add(new FileChooser.ExtensionFilter("PDF", "*.pdf"));
                fc.setInitialFileName("Simulation_" + emp.getLastName()
                        + "_" + cbMois.getValue() + "_" + annee + ".pdf");
                Stage stage = (Stage) tableFiches.getScene().getWindow();
                File file = fc.showSaveDialog(stage);
                if (file != null) {
                    String nomComplet = emp.getFirstName() + " " + emp.getLastName();
                    new ExportPdfService().exportFichePaiePDF(
                            fiche, nomComplet, emp.getJobTitle(), file.toPath());
                    showInfo("Export PDF", "Simulation exportée vers :\n" + file.getAbsolutePath());
                }
            }
        } catch (Exception ex) {
            showError("Erreur", ex.getMessage());
        }
    }

    @FXML
    private void handleRefreshFiches() {
        loadFiches();
        updateStatistics();
    }

    // ==================== EVENT HANDLERS - Primes ====================

    @FXML
    private void handleAddPrime() {
        Dialog<Prime> dialog = createPrimeDialog(null);
        Optional<Prime> result = dialog.showAndWait();

        result.ifPresent(prime -> {
            try {
                primeService.addPrime(prime);
                loadPrimes();
                updateStatistics();
                showInfo("Succès", "Prime ajoutée avec succès!");
            } catch (SQLException e) {
                showError("Erreur", "Impossible d'ajouter la prime: " + e.getMessage());
            }
        });
    }

    @FXML
    private void handleEditPrime() {
        Prime selected = tablePrimes.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showWarning("Aucune sélection", "Veuillez sélectionner une prime.");
            return;
        }

        Dialog<Prime> dialog = createPrimeDialog(selected);
        Optional<Prime> result = dialog.showAndWait();

        result.ifPresent(prime -> {
            try {
                primeService.updatePrime(prime);
                loadPrimes();
                showInfo("Succès", "Prime modifiée avec succès!");
            } catch (SQLException e) {
                showError("Erreur", "Impossible de modifier la prime: " + e.getMessage());
            }
        });
    }

    @FXML
    private void handleDeletePrime() {
        Prime selected = tablePrimes.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showWarning("Aucune sélection", "Veuillez sélectionner une prime.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Supprimer la prime ?");
        confirm.setContentText("Êtes-vous sûr de vouloir supprimer cette prime ?");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                try {
                    primeService.deletePrime(selected.getIdPrime());
                    loadPrimes();
                    updateStatistics();
                    showInfo("Succès", "Prime supprimée avec succès!");
                } catch (SQLException e) {
                    showError("Erreur", "Impossible de supprimer la prime: " + e.getMessage());
                }
            }
        });
    }

    @FXML
    private void handleRefreshPrimes() {
        loadPrimes();
        updateStatistics();
    }

    // ==================== EVENT HANDLERS - Déductions ====================

    @FXML
    private void handleAddDeduction() {
        Dialog<Deduction> dialog = createDeductionDialog(null);
        Optional<Deduction> result = dialog.showAndWait();

        result.ifPresent(deduction -> {
            try {
                deductionService.addDeduction(deduction);
                loadDeductions();
                updateStatistics();
                showInfo("Succès", "Déduction ajoutée avec succès!");
            } catch (SQLException e) {
                showError("Erreur", "Impossible d'ajouter la déduction: " + e.getMessage());
            }
        });
    }

    @FXML
    private void handleEditDeduction() {
        Deduction selected = tableDeductions.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showWarning("Aucune sélection", "Veuillez sélectionner une déduction.");
            return;
        }

        Dialog<Deduction> dialog = createDeductionDialog(selected);
        Optional<Deduction> result = dialog.showAndWait();

        result.ifPresent(deduction -> {
            try {
                deductionService.updateDeduction(deduction);
                loadDeductions();
                showInfo("Succès", "Déduction modifiée avec succès!");
            } catch (SQLException e) {
                showError("Erreur", "Impossible de modifier la déduction: " + e.getMessage());
            }
        });
    }

    @FXML
    private void handleDeleteDeduction() {
        Deduction selected = tableDeductions.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showWarning("Aucune sélection", "Veuillez sélectionner une déduction.");
            return;
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Supprimer la déduction ?");
        confirm.setContentText("Êtes-vous sûr de vouloir supprimer cette déduction ?");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                try {
                    deductionService.deleteDeduction(selected.getIdDeduction());
                    loadDeductions();
                    updateStatistics();
                    showInfo("Succès", "Déduction supprimée avec succès!");
                } catch (SQLException e) {
                    showError("Erreur", "Impossible de supprimer la déduction: " + e.getMessage());
                }
            }
        });
    }

    @FXML
    private void handleRefreshDeductions() {
        loadDeductions();
        updateStatistics();
    }

    // ==================== DIALOG CREATORS ====================

    private Dialog<FichePaie> createFicheDialog(FichePaie existingFiche) {
        Dialog<FichePaie> dialog = new Dialog<>();
        dialog.setTitle(existingFiche == null ? "Nouvelle Fiche de Paie" : "Modifier Fiche de Paie");

        ButtonType saveButtonType = new ButtonType("Enregistrer", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(saveButtonType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20, 150, 10, 10));

        ComboBox<Employee> cbEmploye = new ComboBox<>();
        cbEmploye.setItems(FXCollections.observableArrayList(allEmployees));
        cbEmploye.setConverter(new javafx.util.StringConverter<Employee>() {
            @Override
            public String toString(Employee employee) {
                return employee == null ? "" : employee.getFirstName() + " " + employee.getLastName();
            }

            @Override
            public Employee fromString(String string) {
                return null;
            }
        });

        TextField txtMois = new TextField();
        TextField txtAnnee = new TextField();
        TextField txtSalaireBrut = new TextField();
        TextField txtTotalPrimes = new TextField();
        TextField txtTotalDeductions = new TextField();
        TextField txtSalaireNet = new TextField();

        if (existingFiche != null) {
            Employee emp = getEmployeeById(existingFiche.getIdEmployees());
            cbEmploye.setValue(emp);
            txtMois.setText(existingFiche.getMois());
            txtAnnee.setText(String.valueOf(existingFiche.getAnnee()));
            txtSalaireBrut.setText(existingFiche.getSalaireBrut().toString());
            txtTotalPrimes.setText(existingFiche.getTotalPrimes().toString());
            txtTotalDeductions.setText(existingFiche.getTotalDeductions().toString());
            txtSalaireNet.setText(existingFiche.getSalaireNet().toString());
        }

        grid.add(new Label("Employé:"), 0, 0);
        grid.add(cbEmploye, 1, 0);
        grid.add(new Label("Mois:"), 0, 1);
        grid.add(txtMois, 1, 1);
        grid.add(new Label("Année:"), 0, 2);
        grid.add(txtAnnee, 1, 2);
        grid.add(new Label("Salaire Brut:"), 0, 3);
        grid.add(txtSalaireBrut, 1, 3);
        grid.add(new Label("Total Primes:"), 0, 4);
        grid.add(txtTotalPrimes, 1, 4);
        grid.add(new Label("Total Déductions:"), 0, 5);
        grid.add(txtTotalDeductions, 1, 5);
        grid.add(new Label("Salaire Net:"), 0, 6);
        grid.add(txtSalaireNet, 1, 6);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == saveButtonType) {
                try {
                    Employee selectedEmp = cbEmploye.getValue();
                    if (selectedEmp == null) throw new IllegalArgumentException("Veuillez sélectionner un employé");

                    BigDecimal salaireBrut = new BigDecimal(txtSalaireBrut.getText());
                    BigDecimal totalPrimes = new BigDecimal(txtTotalPrimes.getText());
                    BigDecimal totalDeductions = new BigDecimal(txtTotalDeductions.getText());
                    BigDecimal salaireNet = new BigDecimal(txtSalaireNet.getText());

                    if (existingFiche == null) {
                        return new FichePaie(
                            txtMois.getText(),
                            Integer.parseInt(txtAnnee.getText()),
                            salaireBrut,
                            totalPrimes,
                            totalDeductions,
                            salaireNet,
                            selectedEmp.getId()
                        );
                    } else {
                        existingFiche.setMois(txtMois.getText());
                        existingFiche.setAnnee(Integer.parseInt(txtAnnee.getText()));
                        existingFiche.setSalaireBrut(salaireBrut);
                        existingFiche.setTotalPrimes(totalPrimes);
                        existingFiche.setTotalDeductions(totalDeductions);
                        existingFiche.setSalaireNet(salaireNet);
                        existingFiche.setIdEmployees(selectedEmp.getId());
                        return existingFiche;
                    }
                } catch (Exception e) {
                    showError("Erreur de validation", e.getMessage());
                    return null;
                }
            }
            return null;
        });

        return dialog;
    }

    private Dialog<Prime> createPrimeDialog(Prime existingPrime) {
        Dialog<Prime> dialog = new Dialog<>();
        dialog.setTitle(existingPrime == null ? "Nouvelle Prime" : "Modifier Prime");

        ButtonType saveButtonType = new ButtonType("Enregistrer", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(saveButtonType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20, 150, 10, 10));

        ComboBox<Employee> cbEmploye = new ComboBox<>();
        cbEmploye.setItems(FXCollections.observableArrayList(allEmployees));
        cbEmploye.setConverter(new javafx.util.StringConverter<Employee>() {
            @Override
            public String toString(Employee employee) {
                return employee == null ? "" : employee.getFirstName() + " " + employee.getLastName();
            }

            @Override
            public Employee fromString(String string) {
                return null;
            }
        });

        TextField txtType = new TextField();
        TextField txtMontant = new TextField();
        DatePicker dpDate = new DatePicker();

        if (existingPrime != null) {
            Employee emp = getEmployeeById(existingPrime.getIdEmploye());
            cbEmploye.setValue(emp);
            txtType.setText(existingPrime.getTypePrime());
            txtMontant.setText(existingPrime.getMontant().toString());
            dpDate.setValue(existingPrime.getDateAttribution());
        }

        grid.add(new Label("Employé:"), 0, 0);
        grid.add(cbEmploye, 1, 0);
        grid.add(new Label("Type de Prime:"), 0, 1);
        grid.add(txtType, 1, 1);
        grid.add(new Label("Montant:"), 0, 2);
        grid.add(txtMontant, 1, 2);
        grid.add(new Label("Date Attribution:"), 0, 3);
        grid.add(dpDate, 1, 3);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == saveButtonType) {
                try {
                    Employee selectedEmp = cbEmploye.getValue();
                    if (selectedEmp == null) throw new IllegalArgumentException("Veuillez sélectionner un employé");

                    if (existingPrime == null) {
                        return new Prime(
                            txtType.getText(),
                            new BigDecimal(txtMontant.getText()),
                            dpDate.getValue(),
                            selectedEmp.getId()
                        );
                    } else {
                        existingPrime.setTypePrime(txtType.getText());
                        existingPrime.setMontant(new BigDecimal(txtMontant.getText()));
                        existingPrime.setDateAttribution(dpDate.getValue());
                        existingPrime.setIdEmploye(selectedEmp.getId());
                        return existingPrime;
                    }
                } catch (Exception e) {
                    showError("Erreur de validation", e.getMessage());
                    return null;
                }
            }
            return null;
        });

        return dialog;
    }

    private Dialog<Deduction> createDeductionDialog(Deduction existingDeduction) {
        Dialog<Deduction> dialog = new Dialog<>();
        dialog.setTitle(existingDeduction == null ? "Nouvelle Déduction" : "Modifier Déduction");

        ButtonType saveButtonType = new ButtonType("Enregistrer", ButtonBar.ButtonData.OK_DONE);
        dialog.getDialogPane().getButtonTypes().addAll(saveButtonType, ButtonType.CANCEL);

        GridPane grid = new GridPane();
        grid.setHgap(10);
        grid.setVgap(10);
        grid.setPadding(new Insets(20, 150, 10, 10));

        ComboBox<Employee> cbEmploye = new ComboBox<>();
        cbEmploye.setItems(FXCollections.observableArrayList(allEmployees));
        cbEmploye.setConverter(new javafx.util.StringConverter<Employee>() {
            @Override
            public String toString(Employee employee) {
                return employee == null ? "" : employee.getFirstName() + " " + employee.getLastName();
            }

            @Override
            public Employee fromString(String string) {
                return null;
            }
        });

        TextField txtType = new TextField();
        TextField txtMontant = new TextField();
        DatePicker dpDate = new DatePicker();

        if (existingDeduction != null) {
            Employee emp = getEmployeeById(existingDeduction.getIdEmploye());
            cbEmploye.setValue(emp);
            txtType.setText(existingDeduction.getTypeDeduction());
            txtMontant.setText(existingDeduction.getMontant().toString());
            dpDate.setValue(existingDeduction.getDateDeduction());
        }

        grid.add(new Label("Employé:"), 0, 0);
        grid.add(cbEmploye, 1, 0);
        grid.add(new Label("Type de Déduction:"), 0, 1);
        grid.add(txtType, 1, 1);
        grid.add(new Label("Montant:"), 0, 2);
        grid.add(txtMontant, 1, 2);
        grid.add(new Label("Date Déduction:"), 0, 3);
        grid.add(dpDate, 1, 3);

        dialog.getDialogPane().setContent(grid);

        dialog.setResultConverter(dialogButton -> {
            if (dialogButton == saveButtonType) {
                try {
                    Employee selectedEmp = cbEmploye.getValue();
                    if (selectedEmp == null) throw new IllegalArgumentException("Veuillez sélectionner un employé");

                    if (existingDeduction == null) {
                        return new Deduction(
                            txtType.getText(),
                            new BigDecimal(txtMontant.getText()),
                            dpDate.getValue(),
                            selectedEmp.getId()
                        );
                    } else {
                        existingDeduction.setTypeDeduction(txtType.getText());
                        existingDeduction.setMontant(new BigDecimal(txtMontant.getText()));
                        existingDeduction.setDateDeduction(dpDate.getValue());
                        existingDeduction.setIdEmploye(selectedEmp.getId());
                        return existingDeduction;
                    }
                } catch (Exception e) {
                    showError("Erreur de validation", e.getMessage());
                    return null;
                }
            }
            return null;
        });

        return dialog;
    }

    // ==================== HELPER METHODS ====================

    private String getEmployeeName(int employeeId) {
        if (allEmployees == null) return "Employé #" + employeeId;
        
        return allEmployees.stream()
            .filter(e -> e.getId() == employeeId)
            .findFirst()
            .map(e -> e.getFirstName() + " " + e.getLastName())
            .orElse("Employé #" + employeeId);
    }

    private Employee getEmployeeById(int employeeId) {
        if (allEmployees == null) return null;
       
        return allEmployees.stream()
            .filter(e -> e.getId() == employeeId)
            .findFirst()
            .orElse(null);
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
        if (txtMontantConversion == null) return;

        // Recalcul automatique lors de la saisie
        txtMontantConversion.textProperty().addListener((obs, oldVal, newVal) -> {
            calculateConversion();
        });

        // Charge les taux initiaux
        loadExchangeRates();
    }

    @FXML
    private void handleRefreshRates() {
        loadExchangeRates();
        showInfo("Taux actualisés", "Les taux de change ont été mis à jour depuis l'API.");
    }

    private void loadExchangeRates() {
        try {
            // Force le rafraîchissement des taux
            BigDecimal tauxEUR = conversionService.getTauxDeChange("TND", "EUR");
            BigDecimal tauxUSD = conversionService.getTauxDeChange("TND", "USD");

            if (lblTauxEUR != null) lblTauxEUR.setText(String.format("%.4f €", tauxEUR));
            if (lblTauxUSD != null) lblTauxUSD.setText(String.format("%.4f $", tauxUSD));

            if (lblLastUpdate != null) {
                lblLastUpdate.setText("Dernière mise à jour: " + 
                    java.time.LocalTime.now().format(java.time.format.DateTimeFormatter.ofPattern("HH:mm:ss")));
            }

            if (lblApiStatus != null) {
                lblApiStatus.setText("✅ API connectée — Taux mis à jour en temps réel");
                lblApiStatus.setStyle("-fx-font-size: 11px; -fx-text-fill: #27ae60; -fx-background-color: #d5f4e6; -fx-padding: 6 10; -fx-background-radius: 6;");
            }

            // Recalcule la conversion si un montant est saisi
            calculateConversion();

        } catch (Exception e) {
            if (lblApiStatus != null) {
                lblApiStatus.setText("⚠️ API indisponible — Taux par défaut utilisés");
                lblApiStatus.setStyle("-fx-font-size: 11px; -fx-text-fill: #c0392b; -fx-background-color: #fadbd8; -fx-padding: 6 10; -fx-background-radius: 6;");
            }
            // Utilise des taux par défaut
            if (lblTauxEUR != null) lblTauxEUR.setText("0.2900 €");
            if (lblTauxUSD != null) lblTauxUSD.setText("0.3200 $");
        }
    }

    private void calculateConversion() {
        if (txtMontantConversion == null || lblConversionEUR == null || lblConversionUSD == null) return;

        String text = txtMontantConversion.getText().replace(",", ".").trim();
        if (text.isEmpty()) {
            lblConversionEUR.setText("—");
            lblConversionUSD.setText("—");
            return;
        }

        try {
            BigDecimal montantTND = new BigDecimal(text);
            BigDecimal montantEUR = conversionService.convertirTndVersEur(montantTND);
            BigDecimal montantUSD = conversionService.convertirTndVersUsd(montantTND);

            lblConversionEUR.setText(String.format("%.3f €", montantEUR));
            lblConversionUSD.setText(String.format("%.3f $", montantUSD));

        } catch (NumberFormatException e) {
            lblConversionEUR.setText("—");
            lblConversionUSD.setText("—");
        } catch (Exception e) {
            lblConversionEUR.setText("Erreur API");
            lblConversionUSD.setText("Erreur API");
        }
    }
}
