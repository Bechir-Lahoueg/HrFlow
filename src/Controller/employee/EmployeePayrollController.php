<?php

namespace App\Controller\employee;

use App\Repository\Rh\EmployeeRepository;
use App\Security\DbUser;
use App\Service\DeductionService;
use App\Service\FichePaieService;
use App\Service\FichePaiePdfService;
use App\Service\PrimeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * EmployeePayrollController - Employee payroll view-only (fiches, primes, deductions)
 */
#[Route('/employee/payroll')]
final class EmployeePayrollController extends AbstractController
{
    /**
     * List all pay slips for current employee
     */
    #[Route('/fiches-paie', name: 'app_employee_payroll_fiches', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function fichePaieList(
        FichePaieService $fichePaieService,
    ): Response {
        $employeeId = $this->getCurrentEmployeeId();

        try {
            $fiches = $fichePaieService->getFichePaiesByEmployee($employeeId);
        } catch (\Exception $e) {
            $fiches = [];
        }

        return $this->render('DashboardEmployee/payroll/fiches_paie_list.html.twig', [
            'user' => $this->getUser(),
            'fiches' => $fiches,
        ]);
    }

    /**
     * Show pay slip detail with primes and deductions
     */
    #[Route('/fiches-paie/{id}', name: 'app_employee_payroll_fiche_show', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function fichePaieShow(
        int $id,
        FichePaieService $fichePaieService,
        PrimeService $primeService,
        DeductionService $deductionService,
    ): Response {
        $employeeId = $this->getCurrentEmployeeId();

        try {
            $fiche = $fichePaieService->getFichePaieById($id);
            
            // Ensure employee can only see their own fiches
            if ($fiche->employeeId !== $employeeId) {
                throw $this->createAccessDeniedException('You do not have access to this pay slip');
            }
        } catch (\Exception $e) {
            throw $this->createAccessDeniedException('Pay slip not found or access denied');
        }

        try {
            $primes = $primeService->getPrimesByEmployeeAndPeriod(
                $employeeId,
                $fiche->mois,
                $fiche->annee
            );
        } catch (\Exception $e) {
            $primes = [];
        }

        try {
            $deductions = $deductionService->getDeductionsByEmployeeAndPeriod(
                $employeeId,
                $fiche->mois,
                $fiche->annee
            );
        } catch (\Exception $e) {
            $deductions = [];
        }

        return $this->render('DashboardEmployee/payroll/fiche_paie_detail.html.twig', [
            'user' => $this->getUser(),
            'fiche' => $fiche,
            'primes' => $primes,
            'deductions' => $deductions,
        ]);
    }

    /**
     * Download a pay slip as PDF
     */
    #[Route('/fiches-paie/{id}/pdf', name: 'app_employee_payroll_fiche_pdf', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function fichePaiePdf(
        int $id,
        FichePaieService $fichePaieService,
        PrimeService $primeService,
        DeductionService $deductionService,
        FichePaiePdfService $pdfService,
    ): Response {
        $employeeId = $this->getCurrentEmployeeId();

        try {
            $fiche = $fichePaieService->getFichePaieById($id);
            if ($fiche->employeeId !== $employeeId) {
                throw $this->createAccessDeniedException();
            }
        } catch (\Exception) {
            throw $this->createAccessDeniedException('Pay slip not found or access denied');
        }

        try {
            $primes = $primeService->getPrimesByEmployeeAndPeriod($employeeId, $fiche->mois, $fiche->annee);
        } catch (\Exception) {
            $primes = [];
        }

        try {
            $deductions = $deductionService->getDeductionsByEmployeeAndPeriod($employeeId, $fiche->mois, $fiche->annee);
        } catch (\Exception) {
            $deductions = [];
        }

        ['fileName' => $fileName, 'content' => $content] = $pdfService->generatePdf($fiche, $primes, $deductions);

        return new Response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
            'Content-Length'      => strlen($content),
        ]);
    }

    /**
     * List all primes for current employee
     */
    #[Route('/primes', name: 'app_employee_payroll_primes', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function primeList(
        PrimeService $primeService,
    ): Response {
        $employeeId = $this->getCurrentEmployeeId();

        try {
            $primes = $primeService->getPrimesByEmployee($employeeId);
        } catch (\Exception $e) {
            $primes = [];
        }

        return $this->render('DashboardEmployee/payroll/primes_list.html.twig', [
            'user' => $this->getUser(),
            'primes' => $primes,
        ]);
    }

    /**
     * List all deductions for current employee
     */
    #[Route('/deductions', name: 'app_employee_payroll_deductions', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function deductionList(
        DeductionService $deductionService,
    ): Response {
        $employeeId = $this->getCurrentEmployeeId();

        try {
            $deductions = $deductionService->getDeductionsByEmployee($employeeId);
        } catch (\Exception $e) {
            $deductions = [];
        }

        return $this->render('DashboardEmployee/payroll/deductions_list.html.twig', [
            'user' => $this->getUser(),
            'deductions' => $deductions,
        ]);
    }

    private function getCurrentEmployeeId(): int
    {
        $user = $this->getUser();

        if (!$user instanceof DbUser) {
            throw $this->createAccessDeniedException('Invalid user.');
        }

        return $user->getId();
    }
}

