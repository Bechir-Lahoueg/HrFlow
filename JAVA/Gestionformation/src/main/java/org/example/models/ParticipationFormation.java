package org.example.models;

import java.time.LocalDate;

public class ParticipationFormation {

    private int idParticipation;
    private int idSession;
    private int idEmployee;
    private LocalDate dateInscription;
    private String statutParticipation;
    private String resultat;
    private String nomEmployee;


    // Constructeur vide
    public ParticipationFormation () {}


    // Constructeur sans id
    public ParticipationFormation(int idSession, int idEmployee,
                                  LocalDate dateInscription,
                                  String statutParticipation,
                                  String resultat) {
        this.idSession = idSession;
        this.idEmployee = idEmployee;
        this.dateInscription = dateInscription;
        this.statutParticipation = statutParticipation;
        this.resultat = resultat;
    }

    // Constructeur avec id
    public ParticipationFormation(int idParticipation, int idSession, int idEmployee,
                                  LocalDate dateInscription,
                                  String statutParticipation,
                                  String resultat) {
        this.idParticipation = idParticipation;
        this.idSession = idSession;
        this.idEmployee = idEmployee;
        this.dateInscription = dateInscription;
        this.statutParticipation = statutParticipation;
        this.resultat = resultat;
    }
    //constructeur avec employee
    public ParticipationFormation(int idParticipation, int idSession, int idEmployee,
                                  LocalDate dateInscription, String statutParticipation,
                                  String resultat, String nomEmployee) {
        this.idParticipation = idParticipation;
        this.idSession = idSession;
        this.idEmployee = idEmployee;
        this.dateInscription = dateInscription;
        this.statutParticipation = statutParticipation;
        this.resultat = resultat;
        this.nomEmployee = nomEmployee;
    }

    // Getters & Setters
    public int getIdParticipation() { return idParticipation; }
    public int getIdSession() { return idSession; }
    public int getIdEmployee() { return idEmployee; }
    public LocalDate getDateInscription() { return dateInscription; }
    public String getStatutParticipation() { return statutParticipation; }
    public String getResultat() { return resultat; }

    public void setStatutParticipation(String statutParticipation) {
        this.statutParticipation = statutParticipation;
    }

    public void setResultat(String resultat) {
        this.resultat = resultat;
    }
    public String getNomEmployee() { return nomEmployee; }
    public void setNomEmployee(String nomEmployee) { this.nomEmployee = nomEmployee; }

    @Override
    public String toString() {
        return "ParticipationFormation{" +
                "idParticipation=" + idParticipation +
                ", idSession=" + idSession +
                ", idEmployee=" + idEmployee +
                ", dateInscription=" + dateInscription +
                ", statutParticipation='" + statutParticipation + '\'' +
                ", resultat='" + resultat + '\'' +
                '}';
    }
}
