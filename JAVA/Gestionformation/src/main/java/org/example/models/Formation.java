package org.example.models;

public class Formation {

    private int idFormation;
    private String titre;
    private String description;
    private String type;
    private int duree;
    private String organisme;
    private String objectifs;
    private Integer idRh; // ID du RH qui a créé cette formation
    private double moyenneRating;
    public Formation() {
    }

    // Constructeur sans ID (pour INSERT)
    public Formation(String titre, String description, String type, int duree, String organisme, String objectifs, Integer idRh) {
        this.titre = titre;
        this.description = description;
        this.type = type;
        this.duree = duree;
        this.organisme = organisme;
        this.objectifs = objectifs;
        this.idRh = idRh;
    }

    //Constructeur avec ID
    public Formation(int idFormation, String titre, String description,
                     String type, int duree, String organisme, String objectifs, Integer idRh) {
        this.idFormation = idFormation;
        this.titre = titre;
        this.description = description;
        this.type = type;
        this.duree = duree;
        this.organisme = organisme;
        this.objectifs = objectifs;
        this.idRh = idRh;
    }

    //getters et setters

    public int getIdFormation() {
        return idFormation;
    }

    public void setIdFormation(int idFormation) {
        this.idFormation = idFormation;
    }

    public String getTitre() {
        return titre;
    }

    public void setTitre(String titre) {
        this.titre = titre;
    }

    public String getDescription() {
        return description;
    }

    public void setDescription(String description) {
        this.description = description;
    }

    public String getType() {
        return type;
    }

    public void setType(String type) {
        this.type = type;
    }

    public int getDuree() {
        return duree;
    }

    public void setDuree(int duree) {
        this.duree = duree;
    }

    public String getOrganisme() {
        return organisme;
    }

    public void setOrganisme(String organisme) {
        this.organisme = organisme;
    }
    public String getObjectifs() {
        return objectifs;
    }

    public void setObjectifs(String objectifs) {
        this.objectifs = objectifs;
    }

    public Integer getIdRh() {
        return idRh;
    }

    public void setIdRh(Integer idRh) {
        this.idRh = idRh;
    }

    public double getMoyenneRating() { return moyenneRating; }
    public void setMoyenneRating(double moyenneRating) { this.moyenneRating = moyenneRating; }

    @Override
    public String toString() {
        return "Formation{" +
                "idFormation=" + idFormation +
                ", titre='" + titre + '\'' +
                ", description='" + description + '\'' +
                ", type='" + type + '\'' +
                ", duree=" + duree +
                ", organisme='" + organisme + '\'' +
                ", objectifs='" + objectifs + '\'' +
                ", idRh=" + idRh +
                '}';
    }
}
