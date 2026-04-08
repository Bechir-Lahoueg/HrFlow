<?php

namespace App\Controller\employee;

use App\Repository\EmployeeRepository;
use App\Repository\FichePaieRepository;
use App\Repository\PrimeRepository;
use App\Repository\DeductionRepository;
use App\Security\DbUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employee/payroll')]
final class EmployeePayrollController extends AbstractController
{
    // ==================== FICHES DE PAIE ====================

    #[Route('/fiches-paie', name: 'app_employee_payroll_fiches', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function fichePaieList(
        FichePaieRepository $fichePaieRepository,
    ): Response {
        $employeeId = $this->getCurrentEmployeeId();
        $fiches = $fichePaieRepository->findBy(
            ['employee' => $employeeId],
            ['annee' => 'DESC', 'mois' => 'DESC']
        );

        return $this->render('DashboardEmployee/payroll/fiches_paie_list.html.twig', [
            'user' => $this->getUser(),
            'fiches' => $fiches,
        ]);
    }

    #[Route('/fiches-paie/{id}', name: 'app_employee_payroll_fiche_show', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function fichePaieShow(
        string $id,
        FichePaieRepository $fichePaieRepository,
        PrimeRepository $primeRepository,
        DeductionRepository $deductionRepository,
    ): Response {
        $idInt = (int) $id;
        $employeeId = $this->getCurrentEmployeeId();
        
        // Filtre strict : l'employé ne peut voir que ses propres fiches
        $fiche = $fichePaieRepository->find($idInt);
        if (!$fiche || $fiche->getEmployee()->getId() !== $employeeId) {
            throw $this->createAccessDeniedException('You do not have access to this pay slip');
        }

        $primes = $primeRepository->findByEmployeeAndPeriod(
            $employeeId,
            $fiche->getMois(),
            $fiche->getAnnee()
        );

        $deductions = $deductionRepository->findByEmployeeAndPeriod(
            $employeeId,
            $fiche->getMois(),
            $fiche->getAnnee()
        );

        // Calculate totals dynamically from fresh data instead of using stale stored values
        $totalPrimes = 0;
        foreach ($primes as $prime) {
            $totalPrimes += (float) $prime->getMontant();
        }

        $totalDeductions = 0;
        foreach ($deductions as $deduction) {
            $totalDeductions += (float) $deduction->getMontant();
        }

        return $this->render('DashboardEmployee/payroll/fiche_paie_detail.html.twig', [
            'user' => $this->getUser(),
            'fiche' => $fiche,
            'primes' => $primes,
            'deductions' => $deductions,
            'totalPrimes' => number_format($totalPrimes, 2),
            'totalDeductions' => number_format($totalDeductions, 2),
        ]);
    }

    // ==================== PRIMES ====================

    #[Route('/primes', name: 'app_employee_payroll_primes', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function primeList(
        PrimeRepository $primeRepository,
    ): Response {
        $employeeId = $this->getCurrentEmployeeId();
        $primes = $primeRepository->findByEmployee($employeeId);

        $totalPrimes = 0;
        foreach ($primes as $prime) {
            $totalPrimes += (float) $prime->getMontant();
        }

        return $this->render('DashboardEmployee/payroll/primes_list.html.twig', [
            'user' => $this->getUser(),
            'primes' => $primes,
            'totalPrimes' => number_format($totalPrimes, 2),
        ]);
    }

    // ==================== DÉDUCTIONS ====================

    #[Route('/deductions', name: 'app_employee_payroll_deductions', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function deductionList(
        DeductionRepository $deductionRepository,
    ): Response {
        $employeeId = $this->getCurrentEmployeeId();
        $deductions = $deductionRepository->findByEmployee($employeeId);

        $totalDeductions = 0;
        foreach ($deductions as $deduction) {
            $totalDeductions += (float) $deduction->getMontant();
        }

        return $this->render('DashboardEmployee/payroll/deductions_list.html.twig', [
            'user' => $this->getUser(),
            'deductions' => $deductions,
            'totalDeductions' => number_format($totalDeductions, 2),
        ]);
    }

    private function getCurrentEmployeeId(): int
    {
        $user = $this->getUser();

        if (!$user instanceof DbUser) {
            throw $this->createAccessDeniedException('Invalid user.');
        }

        // L'ID de l'utilisateur correspond à l'ID d'employé
        // (à adapter selon votre structure)
        return $user->getId();
    }
}
