package org.example.Entity;

import java.math.BigDecimal;

public class FichePaie {

    private int idFiche;
    private String mois;
    private int annee;
    private BigDecimal salaireBrut;
    private BigDecimal totalPrimes;
    private BigDecimal totalDeductions;
    private BigDecimal salaireNet;
    private int idEmployees;

    public FichePaie() {
    }

    public FichePaie(int idFiche, String mois, int annee, BigDecimal salaireBrut,
                     BigDecimal totalPrimes, BigDecimal totalDeductions,
                     BigDecimal salaireNet, int idEmployees) {
        this.idFiche = idFiche;
        this.mois = mois;
        this.annee = annee;
        this.salaireBrut = salaireBrut;
        this.totalPrimes = totalPrimes;
        this.totalDeductions = totalDeductions;
        this.salaireNet = salaireNet;
        this.idEmployees = idEmployees;
    }

    public FichePaie(String mois, int annee, BigDecimal salaireBrut,
                     BigDecimal totalPrimes, BigDecimal totalDeductions,
                     BigDecimal salaireNet, int idEmployees) {
        this.mois = mois;
        this.annee = annee;
        this.salaireBrut = salaireBrut;
        this.totalPrimes = totalPrimes;
        this.totalDeductions = totalDeductions;
        this.salaireNet = salaireNet;
        this.idEmployees = idEmployees;
    }

    public int getIdFiche() {
        return idFiche;
    }

    public void setIdFiche(int idFiche) {
        this.idFiche = idFiche;
    }

    public String getMois() {
        return mois;
    }

    public void setMois(String mois) {
        this.mois = mois;
    }

    public int getAnnee() {
        return annee;
    }

    public void setAnnee(int annee) {
        this.annee = annee;
    }

    public BigDecimal getSalaireBrut() {
        return salaireBrut;
    }

    public void setSalaireBrut(BigDecimal salaireBrut) {
        this.salaireBrut = salaireBrut;
    }

    public BigDecimal getTotalPrimes() {
        return totalPrimes;
    }

    public void setTotalPrimes(BigDecimal totalPrimes) {
        this.totalPrimes = totalPrimes;
    }

    public BigDecimal getTotalDeductions() {
        return totalDeductions;
    }

    public void setTotalDeductions(BigDecimal totalDeductions) {
        this.totalDeductions = totalDeductions;
    }

    public BigDecimal getSalaireNet() {
        return salaireNet;
    }

    public void setSalaireNet(BigDecimal salaireNet) {
        this.salaireNet = salaireNet;
    }

    public int getIdEmployees() {
        return idEmployees;
    }

    public void setIdEmployees(int idEmployees) {
        this.idEmployees = idEmployees;
    }

    @Override
    public String toString() {
        return "FichePaie{" +
                "idFiche=" + idFiche +
                ", mois='" + mois + '\'' +
                ", annee=" + annee +
                ", salaireBrut=" + salaireBrut +
                ", totalPrimes=" + totalPrimes +
                ", totalDeductions=" + totalDeductions +
                ", salaireNet=" + salaireNet +
                ", idEmployees=" + idEmployees +
                '}';
    }
}

