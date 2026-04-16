<?php

namespace App\Controller\rh;

use App\DTO\Payroll\FichePaieRequestDTO;
use App\DTO\Payroll\PrimeRequestDTO;
use App\DTO\Payroll\DeductionRequestDTO;
use App\Exception\Payroll\DuplicateFichePaieException;
use App\Exception\Payroll\InvalidPeriodException;
use App\Exception\Payroll\InvalidSalaryException;
use App\Exception\Payroll\EmployeeNotFoundException;
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

/**
 * RhPayrollController - Manages payroll (fiches de paie), primes, and deductions
 * Entry point: /remuneration (list employees) → employee detail → fiche detail
 */
#[Route('/welcome/rh/remuneration')]
#[Route('/welcome/rh/payroll')]
final class RhPayrollController extends AbstractController
{
    /**
     * Main page: List employees for payroll management
     */
    #[Route('', name: 'app_rh_remuneration_index', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function index(
        Request $request,
        EmployeeRepository $employeeRepository,
        FichePaieService $fichePaieService,
    ): Response {
        $rhId = $this->getCurrentRhId();
        $search = trim((string) $request->query->get('search', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $queryBuilder = $employeeRepository->createQueryBuilder('e')
            ->where('e.rhId = :rhId')
            ->setParameter('rhId', $rhId);

        if ($search !== '') {
            $queryBuilder
                ->andWhere('LOWER(CONCAT(e.firstName, \' \', e.lastName)) LIKE :search')
                ->setParameter('search', '%' . strtolower($search) . '%');
        }

        $total = count($queryBuilder->getQuery()->getResult());
        $employees = $queryBuilder
            ->orderBy('e.firstName', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

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
        $rhId = $this->getCurrentRhId();
        $employees = $employeeRepository->findByRh($this->getUser());

        if (empty($employees)) {
            $this->addFlash('warning', 'Vous n\'avez aucun employé assigné.');
            return $this->redirectToRoute('app_rh_payroll_fiches');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_fiche_paie', (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_fiche_create');
            }

            try {
                $dto = new FichePaieRequestDTO(
                    employeeId: (int) $request->request->get('employee_id', 0),
                    mois: (int) $request->request->get('mois', 0),
                    annee: (int) $request->request->get('annee', 0),
                    salaireBrut: (string) $request->request->get('salaire_brut', '0'),
                    notes: trim((string) $request->request->get('notes', '')) ?: null,
                );

                $fichePaieService->createFichePaie($dto);
                $this->addFlash('success', 'Fiche de paie créée avec succès.');
                return $this->redirectToRoute('app_rh_payroll_fiches');
            } catch (InvalidPeriodException|InvalidSalaryException|EmployeeNotFoundException|DuplicateFichePaieException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('DashboardHr/remuneration/fiche_paie_form.html.twig', [
            'user' => $this->getUser(),
            'fiche' => null,
            'employees' => $employees,
        ]);
    }

    #[Route('/fiches-paie/{id}/edit', name: 'app_rh_payroll_fiche_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function fichePaieEdit(
        int $id,
        Request $request,
        FichePaieService $fichePaieService,
        EmployeeRepository $employeeRepository,
    ): Response {
        $rhId = $this->getCurrentRhId();

        try {
            $fiche = $fichePaieService->getFichePaieById($id);
            $employee = $employeeRepository->find($fiche->employeeId);
            if (!$employee || $employee->getRhId() !== $rhId) {
                throw $this->createAccessDeniedException('Access denied');
            }
        } catch (\Exception $e) {
            throw $this->createAccessDeniedException('Fiche de paie non trouvée');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_fiche_paie_' . $id, (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_fiche_edit', ['id' => $id]);
            }

            $result = $fichePaieService->updateFichePaie($idInt, $mois, $annee, $salaireBrut, $notes ?: null);

            if ($result['success']) {
                $this->addFlash('success', $result['message']);
                return $this->redirectToRoute('app_rh_payroll_fiche_show', ['id' => $idInt]);
            } else {
                $this->addFlash('error', $result['message']);
            }
        }

        return $this->render('DashboardHr/remuneration/fiche_paie_form.html.twig', [
            'user' => $this->getUser(),
            'fiche' => $fiche,
            'employees' => $employeeRepository->findByRh($this->getUser()),
        ]);
    }

    #[Route('/fiches-paie/{id}/delete', name: 'app_rh_payroll_fiche_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function fichePaieDelete(
        int $id,
        Request $request,
        FichePaieService $fichePaieService,
        EmployeeRepository $employeeRepository,
    ): RedirectResponse {
        $rhId = $this->getCurrentRhId();

        try {
            $fiche = $fichePaieService->getFichePaieById($id);
            $employee = $employeeRepository->find($fiche->employeeId);
            if (!$employee || $employee->getRhId() !== $rhId) {
                throw $this->createAccessDeniedException('Access denied');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Fiche de paie non trouvée');
            return $this->redirectToRoute('app_rh_payroll_fiches');
        }

        if (!$this->isCsrfTokenValid('delete_fiche_paie_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_payroll_fiches');
        }

        try {
            $fichePaieService->deleteFichePaie($id);
            $this->addFlash('success', 'Fiche de paie supprimée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_rh_payroll_fiches');
    }

    #[Route('/fiches-paie/{id}', name: 'app_rh_payroll_fiche_show', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function fichePaieShow(
        int $id,
        FichePaieService $fichePaieService,
        PrimeService $primeService,
        DeductionService $deductionService,
        EmployeeRepository $employeeRepository,
    ): Response {
        $rhId = $this->getCurrentRhId();

        try {
            $fiche = $fichePaieService->getFichePaieById($id);
            $employee = $employeeRepository->find($fiche->employeeId);
            if (!$employee || $employee->getRhId() !== $rhId) {
                throw $this->createAccessDeniedException('Access denied');
            }
        } catch (\Exception $e) {
            throw $this->createAccessDeniedException('Fiche de paie non trouvée');
        }

        $primes = $primeService->getPrimesByEmployeeAndPeriod(
            $fiche->employeeId,
            $fiche->mois,
            $fiche->annee
        );

        $deductions = $deductionService->getDeductionsByEmployeeAndPeriod(
            $fiche->employeeId,
            $fiche->mois,
            $fiche->annee
        );

        return $this->render('DashboardHr/remuneration/fiche_paie_show.html.twig', [
            'user' => $this->getUser(),
            'fiche' => $fiche,
            'primes' => $primes,
            'deductions' => $deductions,
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

        return $this->render('DashboardHr/remuneration/primes_index.html.twig', [
            'user' => $this->getUser(),
            'primes' => $primes,
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

        if (empty($employees)) {
            $this->addFlash('warning', 'Vous n\'avez aucun employé assigné.');
            return $this->redirectToRoute('app_rh_payroll_primes');
        }

        // Check for pre-selected employee from query parameter
        $preSelectedEmployeeId = null;
        if ($request->query->has('employee_id')) {
            $preSelectedEmployeeId = (int) $request->query->get('employee_id');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_prime', (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_prime_create');
            }

            try {
                $dateStr = trim((string) $request->request->get('date_attribution', ''));
                $dateAttribution = $dateStr ? new \DateTime($dateStr) : null;

                $dto = new PrimeRequestDTO(
                    employeeId: (int) $request->request->get('employee_id', 0),
                    typePrime: $this->parsePrimeType((string) $request->request->get('type_prime', '')),
                    montant: (string) $request->request->get('montant', '0'),
                    dateAttribution: $dateAttribution,
                    motif: trim((string) $request->request->get('motif', '')) ?: null,
                );

                $primeService->createPrime($dto);
                $this->addFlash('success', 'Prime créée avec succès.');
                return $this->redirectToRoute('app_rh_payroll_primes');
            } catch (EmployeeNotFoundException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('app_rh_payroll_prime_create');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur: ' . $e->getMessage());
                return $this->redirectToRoute('app_rh_payroll_prime_create');
            }
        }

        return $this->render('DashboardHr/remuneration/prime_form.html.twig', [
            'user' => $this->getUser(),
            'prime' => null,
            'employees' => $employees,
            'typeChoices' => PrimeService::getPrimeTypeChoices(),
            'preSelectedEmployeeId' => $preSelectedEmployeeId,
            'isEditing' => false,
        ]);
    }

    #[Route('/primes/{id}/edit', name: 'app_rh_payroll_prime_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function primeEdit(
        int $id,
        Request $request,
        PrimeService $primeService,
        EmployeeRepository $employeeRepository,
    ): Response {
        $rhId = $this->getCurrentRhId();

        try {
            $prime = $primeService->getPrimeById($id);
            $employee = $employeeRepository->find($prime->employeeId);
            if (!$employee || $employee->getRhId() !== $rhId) {
                throw $this->createAccessDeniedException('Access denied');
            }
        } catch (\Exception $e) {
            throw $this->createAccessDeniedException('Prime non trouvée');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_prime_' . $id, (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_prime_edit', ['id' => $id]);
            }

            try {
                $dateStr = trim((string) $request->request->get('date_attribution', ''));
                $dateAttribution = $dateStr ? new \DateTime($dateStr) : null;

                $dto = new PrimeRequestDTO(
                    employeeId: $prime->employeeId,
                    typePrime: $this->parsePrimeType((string) $request->request->get('type_prime', '')),
                    montant: (string) $request->request->get('montant', '0'),
                    dateAttribution: $dateAttribution,
                    motif: trim((string) $request->request->get('motif', '')) ?: null,
                );

                $primeService->updatePrime($id, $dto);
                $this->addFlash('success', 'Prime mise à jour avec succès.');
                return $this->redirectToRoute('app_rh_payroll_primes');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur: ' . $e->getMessage());
                return $this->redirectToRoute('app_rh_payroll_prime_edit', ['id' => $id]);
            }
        }

        return $this->render('DashboardHr/remuneration/prime_form.html.twig', [
            'user' => $this->getUser(),
            'prime' => $prime,
            'employees' => $employeeRepository->findByRh($this->getUser()),
            'typeChoices' => PrimeService::getPrimeTypeChoices(),
            'preSelectedEmployeeId' => null,
            'isEditing' => true,
        ]);
    }

    #[Route('/primes/{id}/delete', name: 'app_rh_payroll_prime_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function primeDelete(
        int $id,
        Request $request,
        PrimeService $primeService,
        EmployeeRepository $employeeRepository,
    ): RedirectResponse {
        $rhId = $this->getCurrentRhId();

        try {
            $prime = $primeService->getPrimeById($id);
            $employee = $employeeRepository->find($prime->employeeId);
            if (!$employee || $employee->getRhId() !== $rhId) {
                throw $this->createAccessDeniedException('Access denied');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Prime non trouvée');
            return $this->redirectToRoute('app_rh_payroll_primes');
        }

        if (!$this->isCsrfTokenValid('delete_prime_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_payroll_primes');
        }

        try {
            $primeService->deletePrime($id);
            $this->addFlash('success', 'Prime supprimée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_rh_payroll_primes');
    }

    #[Route('/primes/{id}', name: 'app_rh_payroll_prime_show', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function primeShow(
        int $id,
        PrimeService $primeService,
        EmployeeRepository $employeeRepository,
    ): Response {
        try {
            $prime = $primeService->getPrimeById($id);
            $employee = $employeeRepository->find($prime->employeeId);
            if (!$employee || $employee->getRhId() !== $this->getCurrentRhId()) {
                throw $this->createAccessDeniedException('Access denied');
            }

            return $this->render('DashboardHr/remuneration/prime_show.html.twig', [
                'user' => $this->getUser(),
                'prime' => $prime,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Prime non trouvée');
            return $this->redirectToRoute('app_rh_payroll_primes');
        }
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

        return $this->render('DashboardHr/remuneration/deductions_index.html.twig', [
            'user' => $this->getUser(),
            'deductions' => $deductions,
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

        if (empty($employees)) {
            $this->addFlash('warning', 'Vous n\'avez aucun employé assigné.');
            return $this->redirectToRoute('app_rh_payroll_deductions');
        }

        // Check for pre-selected employee from query parameter
        $preSelectedEmployeeId = null;
        if ($request->query->has('employee_id')) {
            $preSelectedEmployeeId = (int) $request->query->get('employee_id');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('create_deduction', (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_deduction_create');
            }

            try {
                $dateStr = trim((string) $request->request->get('date_deduction', ''));
                $dateDeduction = $dateStr ? new \DateTime($dateStr) : null;

                $dto = new DeductionRequestDTO(
                    employeeId: (int) $request->request->get('employee_id', 0),
                    typeDeduction: $this->parseDeductionType((string) $request->request->get('type_deduction', '')),
                    montant: (string) $request->request->get('montant', '0'),
                    dateDeduction: $dateDeduction,
                    motif: trim((string) $request->request->get('motif', '')) ?: null,
                );

                $deductionService->createDeduction($dto);
                $this->addFlash('success', 'Déduction créée avec succès.');
                return $this->redirectToRoute('app_rh_payroll_deductions');
            } catch (EmployeeNotFoundException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('app_rh_payroll_deduction_create');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur: ' . $e->getMessage());
                return $this->redirectToRoute('app_rh_payroll_deduction_create');
            }
        }

        return $this->render('DashboardHr/remuneration/deduction_form.html.twig', [
            'user' => $this->getUser(),
            'deduction' => null,
            'employees' => $employees,
            'typeChoices' => DeductionService::getDeductionTypeChoices(),
            'preSelectedEmployeeId' => $preSelectedEmployeeId,
            'isEditing' => false,
        ]);
    }

    #[Route('/deductions/{id}/edit', name: 'app_rh_payroll_deduction_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_RH')]
    public function deductionEdit(
        int $id,
        Request $request,
        DeductionService $deductionService,
        EmployeeRepository $employeeRepository,
    ): Response {
        $rhId = $this->getCurrentRhId();

        try {
            $deduction = $deductionService->getDeductionById($id);
            $employee = $employeeRepository->find($deduction->employeeId);
            if (!$employee || $employee->getRhId() !== $rhId) {
                throw $this->createAccessDeniedException('Access denied');
            }
        } catch (\Exception $e) {
            throw $this->createAccessDeniedException('Déduction non trouvée');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_deduction_' . $id, (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_rh_payroll_deduction_edit', ['id' => $id]);
            }

            try {
                $dateStr = trim((string) $request->request->get('date_deduction', ''));
                $dateDeduction = $dateStr ? new \DateTime($dateStr) : null;

                $dto = new DeductionRequestDTO(
                    employeeId: $deduction->employeeId,
                    typeDeduction: $this->parseDeductionType((string) $request->request->get('type_deduction', '')),
                    montant: (string) $request->request->get('montant', '0'),
                    dateDeduction: $dateDeduction,
                    motif: trim((string) $request->request->get('motif', '')) ?: null,
                );

                $deductionService->updateDeduction($id, $dto);
                $this->addFlash('success', 'Déduction mise à jour avec succès.');
                return $this->redirectToRoute('app_rh_payroll_deductions');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur: ' . $e->getMessage());
                return $this->redirectToRoute('app_rh_payroll_deduction_edit', ['id' => $id]);
            }
        }

        return $this->render('DashboardHr/remuneration/deduction_form.html.twig', [
            'user' => $this->getUser(),
            'deduction' => $deduction,
            'employees' => $employeeRepository->findByRh($this->getUser()),
            'typeChoices' => DeductionService::getDeductionTypeChoices(),
            'preSelectedEmployeeId' => null,
            'isEditing' => true,
        ]);
    }

    #[Route('/deductions/{id}/delete', name: 'app_rh_payroll_deduction_delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function deductionDelete(
        int $id,
        Request $request,
        DeductionService $deductionService,
        EmployeeRepository $employeeRepository,
    ): RedirectResponse {
        $rhId = $this->getCurrentRhId();

        try {
            $deduction = $deductionService->getDeductionById($id);
            $employee = $employeeRepository->find($deduction->employeeId);
            if (!$employee || $employee->getRhId() !== $rhId) {
                throw $this->createAccessDeniedException('Access denied');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Déduction non trouvée');
            return $this->redirectToRoute('app_rh_payroll_deductions');
        }

        if (!$this->isCsrfTokenValid('delete_deduction_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_rh_payroll_deductions');
        }

        try {
            $deductionService->deleteDeduction($id);
            $this->addFlash('success', 'Déduction supprimée avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_rh_payroll_deductions');
    }

    #[Route('/deductions/{id}', name: 'app_rh_payroll_deduction_show', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function deductionShow(
        int $id,
        DeductionService $deductionService,
        EmployeeRepository $employeeRepository,
    ): Response {
        try {
            $deduction = $deductionService->getDeductionById($id);
            $employee = $employeeRepository->find($deduction->employeeId);
            if (!$employee || $employee->getRhId() !== $this->getCurrentRhId()) {
                throw $this->createAccessDeniedException('Access denied');
            }

            return $this->render('DashboardHr/remuneration/deduction_show.html.twig', [
                'user' => $this->getUser(),
                'deduction' => $deduction,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Déduction non trouvée');
            return $this->redirectToRoute('app_rh_payroll_deductions');
        }
    }

    private function getCurrentRhId(): int
    {
        $user = $this->getUser();

        if (!$user instanceof DbUser) {
            throw $this->createAccessDeniedException('Invalid RH user.');
        }

        return $user->getId();
    }

    private function parsePrimeType(string $value): ?\App\Enum\PrimeType
    {
        if (empty($value)) {
            return null;
        }
        try {
            return \App\Enum\PrimeType::from($value);
        } catch (\ValueError) {
            return null;
        }
    }

    private function parseDeductionType(string $value): ?\App\Enum\DeductionType
    {
        if (empty($value)) {
            return null;
        }
        try {
            return \App\Enum\DeductionType::from($value);
        } catch (\ValueError) {
            return null;
        }
    }
}

