package org.example.Entity;

import java.math.BigDecimal;
import java.time.LocalDate;

public class Deduction {

    private int idDeduction;
    private String typeDeduction;
    private BigDecimal montant;
    private LocalDate dateDeduction;
    private int idEmploye;

    public Deduction() {
    }


    public Deduction(int idDeduction, String typeDeduction, BigDecimal montant,
                     LocalDate dateDeduction, int idEmploye) {
        this.idDeduction = idDeduction;
        this.typeDeduction = typeDeduction;
        this.montant = montant;
        this.dateDeduction = dateDeduction;
        this.idEmploye = idEmploye;
    }

    public Deduction(String typeDeduction, BigDecimal montant,
                     LocalDate dateDeduction, int idEmploye) {
        this.typeDeduction = typeDeduction;
        this.montant = montant;
        this.dateDeduction = dateDeduction;
        this.idEmploye = idEmploye;
    }

    public int getIdDeduction() {
        return idDeduction;
    }

    public void setIdDeduction(int idDeduction) {
        this.idDeduction = idDeduction;
    }

    public String getTypeDeduction() {
        return typeDeduction;
    }

    public void setTypeDeduction(String typeDeduction) {
        this.typeDeduction = typeDeduction;
    }

    public BigDecimal getMontant() {
        return montant;
    }

    public void setMontant(BigDecimal montant) {
        this.montant = montant;
    }

    public LocalDate getDateDeduction() {
        return dateDeduction;
    }

    public void setDateDeduction(LocalDate dateDeduction) {
        this.dateDeduction = dateDeduction;
    }

    public int getIdEmploye() {
        return idEmploye;
    }

    public void setIdEmploye(int idEmploye) {
        this.idEmploye = idEmploye;
    }

    @Override
    public String toString() {
        return "Deduction{" +
                "idDeduction=" + idDeduction +
                ", typeDeduction='" + typeDeduction + '\'' +
                ", montant=" + montant +
                ", dateDeduction=" + dateDeduction +
                ", idEmploye=" + idEmploye +
                '}';
    }
}
