<?php

namespace App\Controller\rh;

use App\Repository\Rh\EmployeeRepository;
use App\Security\DbUser;
use App\Service\FichePaieService;
use App\Service\PrimeService;
use App\Service\DeductionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/welcome/rh/payroll')]
final class RhPayrollController extends AbstractController
{
    #[Route('/fiches-paie', name: 'app_rh_payroll_fiches', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function fichePaieList(
        Request $request,
        FichePaieService $fichePaieService,
    ): Response {
        $rhId = $this->getCurrentRhId();
        $employeeSearch = trim((string) $request->query->get('employee', ''));
        $periodSearch = trim((string) $request->query->get('period', ''));
        $sortQuery = (string) $request->query->get('sort', 'createdAt-DESC');

        $fiches = $fichePaieService->searchFichePaies($rhId, $employeeSearch, $periodSearch, $sortQuery);
        $stats = $fichePaieService->getStatsByRh($rhId);

        return $this->render('DashboardHr/payroll/fiches_paie_index.html.twig', [
            'user' => $this->getUser(),
            'fiches' => $fiches,
            'stats' => $stats,
            'filters' => [
                'employee' => $employeeSearch,
                'period' => $periodSearch,
                'sort' => $sortQuery,
            ],
        ]);
    }

    #[Route('/fiches-paie/create', name: 'app_rh_payroll_fiche_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function fichePaieCreate(
        Request $request,
        FichePaieService $fichePaieService,
        EmployeeRepository $employeeRepository,
    ): Response {
        $employees = $employeeRepository->findByRh($this->getUser());
        
        // Check if there are employees
        if (empty($employees)) {
            $this->addFlash('warning', 'Vous n\'avez aucun employé assigné. Impossible d\'ajouter une fiche de paie.');
            return $this->redirectToRoute('app_rh_payroll_fiches');
        }
        
        if ($request->isMethod('POST')) {
            $employeeId = (int) $request->request->get('employee_id', 0);
            $mois = (int) $request->request->get('mois', 0);
            $annee = (int) $request->request->get('annee', 0);
            $salaireBrut = (string) $request->request->get('salaire_brut', '0');
            $notes = trim((string) $request->request->get('notes', ''));

            if (!$this->isCsrfTokenValid('create_fiche_paie', (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_fiche_create');
            }

            $result = $fichePaieService->createFichePaie($employeeId, $mois, $annee, $salaireBrut, $notes ?: null);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
                return $this->redirectToRoute('app_rh_payroll_fiches');
            } else {
                $this->addFlash('error', $result['message']);
            }
        }

        return $this->render('DashboardHr/payroll/fiche_paie_form.html.twig', [
            'user' => $this->getUser(),
            'fiche' => null,
            'employees' => $employees,
            'isEdit' => false,
        ]);
    }

    #[Route('/fiches-paie/{id}/edit', name: 'app_rh_payroll_fiche_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function fichePaieEdit(
        string $id,
        Request $request,
        FichePaieService $fichePaieService,
        EmployeeRepository $employeeRepository,
    ): Response {
        $idInt = (int) $id;
        $fiche = $fichePaieService->getFichePaieById($idInt);
        if (!$fiche || $fiche->getEmployee()->getRhId() !== $this->getCurrentRhId()) {
            throw $this->createAccessDeniedException('Pay slip not found or access denied');
        }

        if ($request->isMethod('POST')) {
            $mois = (int) $request->request->get('mois', 0);
            $annee = (int) $request->request->get('annee', 0);
            $salaireBrut = (string) $request->request->get('salaire_brut', '0');
            $notes = trim((string) $request->request->get('notes', ''));

            if (!$this->isCsrfTokenValid('edit_fiche_paie_' . $idInt, (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_fiche_edit', ['id' => $idInt]);
            }

            $result = $fichePaieService->updateFichePaie($idInt, $mois, $annee, $salaireBrut, $notes ?: null);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
                return $this->redirectToRoute('app_rh_payroll_fiche_show', ['id' => $idInt]);
            } else {
                $this->addFlash('error', $result['message']);
            }
        }

        return $this->render('DashboardHr/payroll/fiche_paie_form.html.twig', [
            'user' => $this->getUser(),
            'fiche' => $fiche,
            'employees' => $employeeRepository->findByRh($this->getUser()),
            'isEdit' => true,
        ]);
    }

    #[Route('/fiches-paie/{id}/delete', name: 'app_rh_payroll_fiche_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function fichePaieDelete(
        string $id,
        Request $request,
        FichePaieService $fichePaieService,
    ): RedirectResponse {
        $idInt = (int) $id;
        $fiche = $fichePaieService->getFichePaieById($idInt);
        if (!$fiche || $fiche->getEmployee()->getRhId() !== $this->getCurrentRhId()) {
            throw $this->createAccessDeniedException('Pay slip not found or access denied');
        }

        if (!$this->isCsrfTokenValid('delete_fiche_paie_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_payroll_fiches');
        }

        $result = $fichePaieService->deleteFichePaie($idInt);
        $this->addFlash($result['success'] ? 'success' : 'error', $result['message']);

        return $this->redirectToRoute('app_rh_payroll_fiches');
    }

    #[Route('/fiches-paie/{id}', name: 'app_rh_payroll_fiche_show', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function fichePaieShow(
        string $id,
        FichePaieService $fichePaieService,
        PrimeService $primeService,
        DeductionService $deductionService,
    ): Response {
        $fiche = $fichePaieService->getFichePaieById((int) $id);
        if (!$fiche || $fiche->getEmployee()->getRhId() !== $this->getCurrentRhId()) {
            throw $this->createAccessDeniedException('Pay slip not found or access denied');
        }

        $primes = $primeService->getPrimesByEmployeeAndPeriod(
            $fiche->getEmployee()->getId(),
            $fiche->getMois(),
            $fiche->getAnnee()
        );

        $deductions = $deductionService->getDeductionsByEmployeeAndPeriod(
            $fiche->getEmployee()->getId(),
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

        return $this->render('DashboardHr/payroll/fiche_paie_show.html.twig', [
            'user' => $this->getUser(),
            'fiche' => $fiche,
            'primes' => $primes,
            'deductions' => $deductions,
            'totalPrimes' => number_format($totalPrimes, 2),
            'totalDeductions' => number_format($totalDeductions, 2),
        ]);
    }

    // ==================== PRIMES ====================

    #[Route('/primes', name: 'app_rh_payroll_primes', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function primeList(
        Request $request,
        PrimeService $primeService,
    ): Response {
        $rhId = $this->getCurrentRhId();
        $employeeSearch = trim((string) $request->query->get('employee', ''));
        $typeSearch = trim((string) $request->query->get('type', ''));
        $sortQuery = (string) $request->query->get('sort', 'dateAttribution-DESC');

        $primes = $primeService->searchPrimes($rhId, $employeeSearch, $typeSearch, $sortQuery);
        $stats = $primeService->getStatsByRh($rhId);

        return $this->render('DashboardHr/payroll/primes_index.html.twig', [
            'user' => $this->getUser(),
            'primes' => $primes,
            'stats' => $stats,
            'filters' => [
                'employee' => $employeeSearch,
                'type' => $typeSearch,
                'sort' => $sortQuery,
            ],
        ]);
    }

    #[Route('/primes/create', name: 'app_rh_payroll_prime_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function primeCreate(
        Request $request,
        PrimeService $primeService,
        EmployeeRepository $employeeRepository,
    ): Response {
        $employees = $employeeRepository->findByRh($this->getUser());
        
        // Check if there are employees
        if (empty($employees)) {
            $this->addFlash('warning', 'Vous n\'avez aucun employé assigné. Impossible d\'ajouter une prime.');
            return $this->redirectToRoute('app_rh_payroll_primes');
        }
        
        if ($request->isMethod('POST')) {
            $employeeId = (int) $request->request->get('employee_id', 0);
            $typePrime = trim((string) $request->request->get('type_prime', ''));
            $montant = (string) $request->request->get('montant', '0');
            $dateAttribution = trim((string) $request->request->get('date_attribution', ''));
            $motif = trim((string) $request->request->get('motif', ''));

            if (!$this->isCsrfTokenValid('create_prime', (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_prime_create');
            }

            $result = $primeService->createPrime($employeeId, $typePrime, $montant, $dateAttribution, $motif ?: null);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
                return $this->redirectToRoute('app_rh_payroll_primes');
            } else {
                $this->addFlash('error', $result['message']);
            }
        }

        return $this->render('DashboardHr/payroll/prime_form.html.twig', [
            'user' => $this->getUser(),
            'prime' => null,
            'employees' => $employees,
            'isEdit' => false,
        ]);
    }

    #[Route('/primes/{id}/edit', name: 'app_rh_payroll_prime_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function primeEdit(
        string $id,
        Request $request,
        PrimeService $primeService,
        EmployeeRepository $employeeRepository,
    ): Response {
        $idInt = (int) $id;
        $prime = $primeService->getPrimeById($idInt);
        if (!$prime || $prime->getEmployee()->getRhId() !== $this->getCurrentRhId()) {
            throw $this->createAccessDeniedException('Prime not found or access denied');
        }

        if ($request->isMethod('POST')) {
            $typePrime = trim((string) $request->request->get('type_prime', ''));
            $montant = (string) $request->request->get('montant', '0');
            $dateAttribution = trim((string) $request->request->get('date_attribution', ''));
            $motif = trim((string) $request->request->get('motif', ''));

            if (!$this->isCsrfTokenValid('edit_prime_' . $idInt, (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_prime_edit', ['id' => $idInt]);
            }

            $result = $primeService->updatePrime($idInt, $typePrime, $montant, $dateAttribution, $motif ?: null);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
                return $this->redirectToRoute('app_rh_payroll_primes');
            } else {
                $this->addFlash('error', $result['message']);
            }
        }

        return $this->render('DashboardHr/payroll/prime_form.html.twig', [
            'user' => $this->getUser(),
            'prime' => $prime,
            'employees' => $employeeRepository->findByRh($this->getUser()),
            'isEdit' => true,
        ]);
    }

    #[Route('/primes/{id}/delete', name: 'app_rh_payroll_prime_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function primeDelete(
        string $id,
        Request $request,
        PrimeService $primeService,
    ): RedirectResponse {
        $idInt = (int) $id;
        $prime = $primeService->getPrimeById($idInt);
        if (!$prime || $prime->getEmployee()->getRhId() !== $this->getCurrentRhId()) {
            throw $this->createAccessDeniedException('Prime not found or access denied');
        }

        if (!$this->isCsrfTokenValid('delete_prime_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_payroll_primes');
        }

        $result = $primeService->deletePrime($idInt);
        $this->addFlash($result['success'] ? 'success' : 'error', $result['message']);

        return $this->redirectToRoute('app_rh_payroll_primes');
    }

    // ==================== DEDUCTIONS ====================

    #[Route('/deductions', name: 'app_rh_payroll_deductions', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function deductionList(
        Request $request,
        DeductionService $deductionService,
    ): Response {
        $rhId = $this->getCurrentRhId();
        $employeeSearch = trim((string) $request->query->get('employee', ''));
        $typeSearch = trim((string) $request->query->get('type', ''));
        $sortQuery = (string) $request->query->get('sort', 'dateDeduction-DESC');

        $deductions = $deductionService->searchDeductions($rhId, $employeeSearch, $typeSearch, $sortQuery);
        $stats = $deductionService->getStatsByRh($rhId);

        return $this->render('DashboardHr/payroll/deductions_index.html.twig', [
            'user' => $this->getUser(),
            'deductions' => $deductions,
            'stats' => $stats,
            'filters' => [
                'employee' => $employeeSearch,
                'type' => $typeSearch,
                'sort' => $sortQuery,
            ],
        ]);
    }

    #[Route('/deductions/create', name: 'app_rh_payroll_deduction_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function deductionCreate(
        Request $request,
        DeductionService $deductionService,
        EmployeeRepository $employeeRepository,
    ): Response {
        $employees = $employeeRepository->findByRh($this->getUser());
        
        // Check if there are employees
        if (empty($employees)) {
            $this->addFlash('warning', 'Vous n\'avez aucun employé assigné. Impossible d\'ajouter une déduction.');
            return $this->redirectToRoute('app_rh_payroll_deductions');
        }
        
        if ($request->isMethod('POST')) {
            $employeeId = (int) $request->request->get('employee_id', 0);
            $typeDeduction = trim((string) $request->request->get('type_deduction', ''));
            $montant = (string) $request->request->get('montant', '0');
            $dateDeduction = trim((string) $request->request->get('date_deduction', ''));
            $motif = trim((string) $request->request->get('motif', ''));

            if (!$this->isCsrfTokenValid('create_deduction', (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_deduction_create');
            }

            $result = $deductionService->createDeduction($employeeId, $typeDeduction, $montant, $dateDeduction, $motif ?: null);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
                return $this->redirectToRoute('app_rh_payroll_deductions');
            } else {
                $this->addFlash('error', $result['message']);
            }
        }

        return $this->render('DashboardHr/payroll/deduction_form.html.twig', [
            'user' => $this->getUser(),
            'deduction' => null,
            'employees' => $employees,
            'isEdit' => false,
        ]);
    }

    #[Route('/deductions/{id}/edit', name: 'app_rh_payroll_deduction_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function deductionEdit(
        string $id,
        Request $request,
        DeductionService $deductionService,
        EmployeeRepository $employeeRepository,
    ): Response {
        $idInt = (int) $id;
        $deduction = $deductionService->getDeductionById($idInt);
        if (!$deduction || $deduction->getEmployee()->getRhId() !== $this->getCurrentRhId()) {
            throw $this->createAccessDeniedException('Deduction not found or access denied');
        }

        if ($request->isMethod('POST')) {
            $typeDeduction = trim((string) $request->request->get('type_deduction', ''));
            $montant = (string) $request->request->get('montant', '0');
            $dateDeduction = trim((string) $request->request->get('date_deduction', ''));
            $motif = trim((string) $request->request->get('motif', ''));

            if (!$this->isCsrfTokenValid('edit_deduction_' . $idInt, (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_deduction_edit', ['id' => $idInt]);
            }

            $result = $deductionService->updateDeduction($idInt, $typeDeduction, $montant, $dateDeduction, $motif ?: null);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
                return $this->redirectToRoute('app_rh_payroll_deductions');
            } else {
                $this->addFlash('error', $result['message']);
            }
        }

        return $this->render('DashboardHr/payroll/deduction_form.html.twig', [
            'user' => $this->getUser(),
            'deduction' => $deduction,
            'employees' => $employeeRepository->findByRh($this->getUser()),
            'isEdit' => true,
        ]);
    }

    #[Route('/deductions/{id}/delete', name: 'app_rh_payroll_deduction_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function deductionDelete(
        string $id,
        Request $request,
        DeductionService $deductionService,
    ): RedirectResponse {
        $idInt = (int) $id;
        $deduction = $deductionService->getDeductionById($idInt);
        if (!$deduction || $deduction->getEmployee()->getRhId() !== $this->getCurrentRhId()) {
            throw $this->createAccessDeniedException('Deduction not found or access denied');
        }

        if (!$this->isCsrfTokenValid('delete_deduction_' . $idInt, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_payroll_deductions');
        }

        $result = $deductionService->deleteDeduction($idInt);
        $this->addFlash($result['success'] ? 'success' : 'error', $result['message']);

        return $this->redirectToRoute('app_rh_payroll_deductions');
    }

    private function getCurrentRhId(): int
    {
        $user = $this->getUser();

        if (!$user instanceof DbUser) {
            throw $this->createAccessDeniedException('Invalid RH user.');
        }

        return $user->getId();
    }
}
