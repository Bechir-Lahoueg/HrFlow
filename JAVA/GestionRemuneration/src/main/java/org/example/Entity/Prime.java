package org.example.Entity;

import java.math.BigDecimal;
import java.time.LocalDate;

public class Prime {

    private int idPrime;
    private String typePrime;
    private BigDecimal montant;
    private LocalDate dateAttribution;
    private int idEmploye;

    public Prime() {
    }

    public Prime(int idPrime, String typePrime, BigDecimal montant,
                 LocalDate dateAttribution, int idEmploye) {
        this.idPrime = idPrime;
        this.typePrime = typePrime;
        this.montant = montant;
        this.dateAttribution = dateAttribution;
        this.idEmploye = idEmploye;
    }

    public Prime(String typePrime, BigDecimal montant,
                 LocalDate dateAttribution, int idEmploye) {
        this.typePrime = typePrime;
        this.montant = montant;
        this.dateAttribution = dateAttribution;
        this.idEmploye = idEmploye;
    }

    public int getIdPrime() {
        return idPrime;
    }

    public void setIdPrime(int idPrime) {
        this.idPrime = idPrime;
    }

    public String getTypePrime() {
        return typePrime;
    }

    public void setTypePrime(String typePrime) {
        this.typePrime = typePrime;
    }

    public BigDecimal getMontant() {
        return montant;
    }

    public void setMontant(BigDecimal montant) {
        this.montant = montant;
    }

    public LocalDate getDateAttribution() {
        return dateAttribution;
    }

    public void setDateAttribution(LocalDate dateAttribution) {
        this.dateAttribution = dateAttribution;
    }

    public int getIdEmploye() {
        return idEmploye;
    }

    public void setIdEmploye(int idEmploye) {
        this.idEmploye = idEmploye;
    }

    @Override
    public String toString() {
        return "Prime{" +
                "idPrime=" + idPrime +
                ", typePrime='" + typePrime + '\'' +
                ", montant=" + montant +
                ", dateAttribution=" + dateAttribution +
                ", idEmploye=" + idEmploye +
                '}';
    }
}

