package org.example.models;

import java.time.LocalDate;

public class SessionFormation {

    private int idSession;
    private int idFormation;
    private LocalDate dateDebut;
    private LocalDate dateFin;
    private String lieu;
    private String mode; // Présentiel / Distanciel
    private int capaciteMax;
    private int placesDisponibles;
    private String statut; // Planifiée / EnCours / Terminée / Annulée

    public SessionFormation() {}

    // Constructeur sans ID
    public SessionFormation(int idFormation, LocalDate dateDebut, LocalDate dateFin,
                            String lieu, String mode, int capaciteMax, String statut) {
        this.idFormation = idFormation;
        this.dateDebut = dateDebut;
        this.dateFin = dateFin;
        this.lieu = lieu;
        this.mode = mode;
        this.capaciteMax = capaciteMax;
        this.placesDisponibles = capaciteMax;
        this.statut = statut;
    }

    // Constructeur avec ID
    public SessionFormation(int idSession, int idFormation, LocalDate dateDebut, LocalDate dateFin,
                            String lieu, String mode, int capaciteMax, String statut) {
        this.idSession = idSession;
        this.idFormation = idFormation;
        this.dateDebut = dateDebut;
        this.dateFin = dateFin;
        this.lieu = lieu;
        this.mode = mode;
        this.capaciteMax = capaciteMax;
        this.placesDisponibles = capaciteMax;
        this.statut = statut;
    }

    // Getters & Setters
    public int getIdSession() { return idSession; }
    public void setIdSession(int idSession) { this.idSession = idSession; }
    public int getIdFormation() { return idFormation; }
    public void setIdFormation(int idFormation) { this.idFormation = idFormation; }
    public LocalDate getDateDebut() { return dateDebut; }
    public void setDateDebut(LocalDate dateDebut) { this.dateDebut = dateDebut; }
    public LocalDate getDateFin() { return dateFin; }
    public void setDateFin(LocalDate dateFin) { this.dateFin = dateFin; }
    public String getLieu() { return lieu; }
    public void setLieu(String lieu) { this.lieu = lieu; }
    public String getMode() { return mode; }
    public void setMode(String mode) { this.mode = mode; }
    public int getCapaciteMax() { return capaciteMax; }
    public void setCapaciteMax(int capaciteMax) { this.capaciteMax = capaciteMax; }
    public int getPlacesDisponibles() { return placesDisponibles; }
    public void setPlacesDisponibles(int placesDisponibles) { this.placesDisponibles = placesDisponibles; }
    public String getStatut() { return statut; }
    public void setStatut(String statut) { this.statut = statut; }

    @Override
    public String toString() {
        return "SessionFormation{" +
                "idSession=" + idSession +
                ", idFormation=" + idFormation +
                ", dateDebut=" + dateDebut +
                ", dateFin=" + dateFin +
                ", lieu='" + lieu + '\'' +
                ", mode='" + mode + '\'' +
                ", capaciteMax=" + capaciteMax +
                ", placesDisponibles=" + placesDisponibles +
                ", statut='" + statut + '\'' +
                '}';
    }
}
