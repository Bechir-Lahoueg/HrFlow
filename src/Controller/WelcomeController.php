<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class WelcomeController extends AbstractController
{
    #[Route('/welcome', name: 'app_welcome')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_welcome_admin');
        }

        if ($this->isGranted('ROLE_RH')) {
            return $this->redirectToRoute('app_welcome_rh');
        }

        if ($this->isGranted('ROLE_EMPLOYEE')) {
            return $this->redirectToRoute('app_welcome_employee');
        }

        throw $this->createAccessDeniedException('Role not supported for welcome page.');
    }

    #[Route('/welcome/admin', name: 'app_welcome_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function admin(): Response
    {
        return $this->render('DashboardAdmin/welcome_admin.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/welcome/rh', name: 'app_welcome_rh')]
    #[IsGranted('ROLE_RH')]
    public function rh(): Response
    {
        return $this->render('DashboardHr/welcome_rh.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/welcome/employee', name: 'app_welcome_employee')]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function employee(): Response
    {
        return $this->render('DashboardEmployee/welcome_employee.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}
