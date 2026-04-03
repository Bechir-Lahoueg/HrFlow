package controllers;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import models.Notification;
import service.NotificationService;

import java.net.URL;
import java.util.List;
import java.util.ResourceBundle;

public class NotificationController implements Initializable {

    // ─── TableView ────────────────────────────────────────────────────
    @FXML private TableView<Notification>              tableNotifications;
    @FXML private TableColumn<Notification, String>    colStatus;
    @FXML private TableColumn<Notification, String>    colType;
    @FXML private TableColumn<Notification, String>    colTitle;
    @FXML private TableColumn<Notification, String>    colMessage;
    @FXML private TableColumn<Notification, String>    colDate;

    // ─── Stats ───────────────────────────────────────────────────────
    @FXML private Label lblTotal;
    @FXML private Label lblUnread;

    // ─── Boutons ─────────────────────────────────────────────────────
    @FXML private Button btnMarkRead;
    @FXML private Button btnMarkAllRead;
    @FXML private Button btnDelete;

    // ─── Filtre ──────────────────────────────────────────────────────
    @FXML private ComboBox<String> filterRead;

    private final NotificationService service = new NotificationService();
    private ObservableList<Notification> notifList = FXCollections.observableArrayList();

    // user connecté (à remplacer quand intégration équipe)
    private final int CURRENT_USER_ID = 1;

    // ─── Initialisation ──────────────────────────────────────────────

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        setupColumns();
        setupFilter();
        loadData();
    }

    private void setupColumns() {
        colStatus.setCellValueFactory(new PropertyValueFactory<>("readStatus"));
        colType.setCellValueFactory(new PropertyValueFactory<>("type"));
        colTitle.setCellValueFactory(new PropertyValueFactory<>("title"));
        colMessage.setCellValueFactory(new PropertyValueFactory<>("message"));
        colDate.setCellValueFactory(new PropertyValueFactory<>("createdAt"));

        // Colorier les non lues
        colStatus.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(String val, boolean empty) {
                super.updateItem(val, empty);
                if (empty || val == null) { setText(null); setStyle(""); return; }
                setText(val);
                if (val.contains("Nouveau")) {
                    setStyle("-fx-text-fill: #3498db; -fx-font-weight: bold;");
                } else {
                    setStyle("-fx-text-fill: #95a5a6;");
                }
            }
        });

        // Double-clic → marquer comme lu
        tableNotifications.setRowFactory(tv -> {
            TableRow<Notification> row = new TableRow<>();
            row.setOnMouseClicked(e -> {
                if (e.getClickCount() == 2 && !row.isEmpty()) {
                    Notification n = row.getItem();
                    if (!n.isRead()) {
                        service.markAsRead(n.getId());
                        loadData();
                    }
                }
            });
            return row;
        });
    }

    private void setupFilter() {
        filterRead.setItems(FXCollections.observableArrayList(
                "Toutes", "Non lues", "Lues"
        ));
        filterRead.setValue("Toutes");
        filterRead.setOnAction(e -> applyFilter());
    }

    // ─── Chargement ──────────────────────────────────────────────────

    private void loadData() {
        List<Notification> list = service.getByUserId(CURRENT_USER_ID);
        notifList.setAll(list);
        tableNotifications.setItems(notifList);
        updateStats(list);
    }

    private void applyFilter() {
        String filter = filterRead.getValue();
        List<Notification> all = service.getByUserId(CURRENT_USER_ID);

        List<Notification> filtered = switch (filter) {
            case "Non lues" -> all.stream().filter(n -> !n.isRead()).toList();
            case "Lues"     -> all.stream().filter(Notification::isRead).toList();
            default         -> all;
        };

        notifList.setAll(filtered);
        tableNotifications.setItems(notifList);
        updateStats(filtered);
    }

    private void updateStats(List<Notification> list) {
        long unread = list.stream().filter(n -> !n.isRead()).count();
        lblTotal.setText("Total : " + list.size());
        lblUnread.setText("🔵 Non lues : " + unread);
    }

    // ─── Actions ─────────────────────────────────────────────────────

    @FXML
    private void handleMarkRead() {
        Notification selected = tableNotifications.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Sélection",
                    "Veuillez sélectionner une notification.");
            return;
        }
        service.markAsRead(selected.getId());
        loadData();
    }

    @FXML
    private void handleMarkAllRead() {
        service.markAllAsRead(CURRENT_USER_ID);
        showAlert(Alert.AlertType.INFORMATION, "Succès",
                "Toutes les notifications marquées comme lues.");
        loadData();
    }

    @FXML
    private void handleDelete() {
        Notification selected = tableNotifications.getSelectionModel().getSelectedItem();
        if (selected == null) {
            showAlert(Alert.AlertType.WARNING, "Sélection",
                    "Veuillez sélectionner une notification.");
            return;
        }
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION,
                "Supprimer cette notification ?", ButtonType.YES, ButtonType.NO);
        confirm.showAndWait().ifPresent(bt -> {
            if (bt == ButtonType.YES) { service.delete(selected.getId()); loadData(); }
        });
    }

    private void showAlert(Alert.AlertType type, String title, String msg) {
        new Alert(type, msg, ButtonType.OK) {{ setTitle(title); }}.showAndWait();
    }
}