<?php

namespace App\Controller\rh;

use App\Repository\Rh\EmployeeRepository;
use App\Security\DbUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/rh')]
final class ApiEmployeeController extends AbstractController
{
    /**
     * AJAX autocomplete: search employees by name.
     * Returns JSON array of {id, name, jobTitle}.
     */
    #[Route('/employees/search', name: 'api_rh_employees_search', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function search(Request $request, EmployeeRepository $employeeRepository): JsonResponse
    {
        /** @var DbUser $user */
        $user = $this->getUser();
        $query = trim((string) $request->query->get('q', ''));

        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        $employees = $employeeRepository->searchByName($user->getId(), $query, 10);

        $results = array_map(fn($e) => [
            'id' => $e->getId(),
            'name' => $e->getFullName(),
            'jobTitle' => $e->getJobTitle(),
        ], $employees);

        return $this->json($results);
    }
}
