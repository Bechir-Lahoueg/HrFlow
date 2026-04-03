package org.example.Service;

import org.example.Entity.Deduction;
import org.example.Entity.FichePaie;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.time.LocalDate;

/**
 * Service de calcul fiscal tunisien.
 * Applique le barème CNSS, AMG et IRPP en vigueur (2025).
 */
public class CalculFiscalService {

    // ==============================
    // TAUX LÉGAUX TUNISIE 2025
    // ==============================

    /** Cotisation CNSS employé : 9.18 % */
    private static final BigDecimal TAUX_CNSS = new BigDecimal("0.0918");

    /** Assurance Maladie de Groupe : 4 % */
    private static final BigDecimal TAUX_AMG = new BigDecimal("0.04");

    // ==============================
    // CALCULS INDIVIDUELS
    // ==============================

    /**
     * Cotisation CNSS mensuelle de l'employé.
     * Formule : salaireBrut × 9.18 %
     */
    public BigDecimal calculerCNSS(BigDecimal salaireBrut) {
        if (salaireBrut == null || salaireBrut.compareTo(BigDecimal.ZERO) <= 0)
            return BigDecimal.ZERO;
        return salaireBrut.multiply(TAUX_CNSS).setScale(3, RoundingMode.HALF_UP);
    }

    /**
     * Cotisation AMG mensuelle de l'employé.
     * Formule : salaireBrut × 4 %
     */
    public BigDecimal calculerAMG(BigDecimal salaireBrut) {
        if (salaireBrut == null || salaireBrut.compareTo(BigDecimal.ZERO) <= 0)
            return BigDecimal.ZERO;
        return salaireBrut.multiply(TAUX_AMG).setScale(3, RoundingMode.HALF_UP);
    }

    /**
     * Retenue IRPP mensuelle selon le barème annualisé Tunisie 2025.
     * Le montant mensuel imposable est annualisé, l'IRPP annuel calculé,
     * puis divisé par 12 pour obtenir la retenue mensuelle.
     *
     * Tranches annuelles :
     *   0      – 5 000  DT  →  0 %
     *   5 001  – 20 000 DT  →  26 %
     *   20 001 – 30 000 DT  →  28 %
     *   30 001 – 50 000 DT  →  32 %
     *   > 50 000 DT         →  35 %
     */
    public BigDecimal calculerIRPP(BigDecimal salaireNetImposableMensuel) {
        if (salaireNetImposableMensuel == null
                || salaireNetImposableMensuel.compareTo(BigDecimal.ZERO) <= 0)
            return BigDecimal.ZERO;

        double annuel = salaireNetImposableMensuel.doubleValue() * 12.0;
        double irppAnnuel;

        if      (annuel <= 5_000)  irppAnnuel = 0;
        else if (annuel <= 20_000) irppAnnuel = (annuel - 5_000)  * 0.26;
        else if (annuel <= 30_000) irppAnnuel = 3_900  + (annuel - 20_000) * 0.28;
        else if (annuel <= 50_000) irppAnnuel = 6_700  + (annuel - 30_000) * 0.32;
        else                       irppAnnuel = 13_100 + (annuel - 50_000) * 0.35;

        return BigDecimal.valueOf(irppAnnuel / 12.0).setScale(3, RoundingMode.HALF_UP);
    }

    // ==============================
    // CALCUL GLOBAL DES DÉDUCTIONS
    // ==============================

    /**
     * Calcule l'ensemble des déductions fiscales pour un salaire brut donné.
     *
     * @param salaireBrut salaire brut mensuel
     * @return tableau [cnss, amg, irpp, totalDeductions]
     */
    public BigDecimal[] calculerDeductionsFiscales(BigDecimal salaireBrut) {
        BigDecimal cnss = calculerCNSS(salaireBrut);
        BigDecimal amg  = calculerAMG(salaireBrut);

        // Base imposable IRPP = Brut - CNSS - AMG
        BigDecimal baseImposable = salaireBrut.subtract(cnss).subtract(amg);
        BigDecimal irpp = calculerIRPP(baseImposable);

        BigDecimal total = cnss.add(amg).add(irpp);
        return new BigDecimal[]{cnss, amg, irpp, total};
    }

    // ==============================
    // SIMULATION FICHE DE PAIE
    // ==============================

    /**
     * Simule une fiche de paie sans la persister en base.
     * Les déductions (CNSS + AMG + IRPP) sont calculées automatiquement.
     *
     * @param idEmploye    identifiant employé
     * @param salaireBrut  salaire brut mensuel
     * @param totalPrimes  montant total des primes pour la période
     * @param mois         mois (ex: "Mars")
     * @param annee        année (ex: 2026)
     * @return FichePaie non persistée (idFiche = 0)
     */
    public FichePaie simulerFichePaie(int idEmploye, BigDecimal salaireBrut,
                                      BigDecimal totalPrimes, String mois, int annee) {
        BigDecimal[] d = calculerDeductionsFiscales(salaireBrut);
        BigDecimal totalDeductions = d[3]; // cnss + amg + irpp
        BigDecimal salaireNet = salaireBrut.add(totalPrimes)
                                           .subtract(totalDeductions)
                                           .setScale(3, RoundingMode.HALF_UP);

        return new FichePaie(mois, annee, salaireBrut,
                             totalPrimes, totalDeductions,
                             salaireNet, idEmploye);
    }

    // ==============================
    // GÉNÉRATEURS DE DÉDUCTIONS
    // ==============================

    /** Crée une Déduction CNSS pour un employé. */
    public Deduction genererDeductionCNSS(BigDecimal salaireBrut, int idEmploye) {
        return new Deduction("CNSS (9.18%)",
                calculerCNSS(salaireBrut),
                LocalDate.now(),
                idEmploye);
    }

    /** Crée une Déduction AMG pour un employé. */
    public Deduction genererDeductionAMG(BigDecimal salaireBrut, int idEmploye) {
        return new Deduction("AMG (4%)",
                calculerAMG(salaireBrut),
                LocalDate.now(),
                idEmploye);
    }

    /** Crée une Déduction IRPP pour un employé (basée sur la base imposable). */
    public Deduction genererDeductionIRPP(BigDecimal baseImposable, int idEmploye) {
        return new Deduction("IRPP",
                calculerIRPP(baseImposable),
                LocalDate.now(),
                idEmploye);
    }
}
