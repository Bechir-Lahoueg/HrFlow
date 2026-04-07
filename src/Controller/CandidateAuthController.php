<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class CandidateAuthController extends AbstractController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/candidat/inscription', name: 'app_candidate_register')]
    public function register(Request $request): Response
    {
        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $username = trim($request->request->get('username', ''));
            $email = trim($request->request->get('email', ''));
            $password = $request->request->get('password', '');
            $confirmPassword = $request->request->get('confirm_password', '');
            $firstName = trim($request->request->get('first_name', ''));
            $lastName = trim($request->request->get('last_name', ''));
            $phone = trim($request->request->get('phone', ''));

            // Validation
            if (empty($username) || empty($email) || empty($password)) {
                $error = 'Veuillez remplir tous les champs obligatoires.';
            } elseif (strlen($password) < 6) {
                $error = 'Le mot de passe doit contenir au moins 6 caracteres.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Les mots de passe ne correspondent pas.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Veuillez entrer une adresse email valide.';
            } else {
                // Check if username or email already exists
                $existing = $this->connection->fetchAssociative(
                    'SELECT id FROM candidates WHERE username = :username OR email = :email LIMIT 1',
                    ['username' => $username, 'email' => $email]
                );

                if ($existing) {
                    $error = 'Ce nom d\'utilisateur ou email est deja utilise.';
                } else {
                    // Hash password using SHA-256 (to match existing system)
                    $hashedPassword = hash('sha256', $password);

                    // Insert new candidate
                    $this->connection->insert('candidates', [
                        'username' => $username,
                        'email' => $email,
                        'password' => $hashedPassword,
                        'first_name' => $firstName ?: null,
                        'last_name' => $lastName ?: null,
                        'phone' => $phone ?: null,
                    ]);

                    $success = 'Votre compte a ete cree avec succes ! Vous pouvez maintenant vous connecter.';
                }
            }
        }

        return $this->render('Auth/candidate_register.html.twig', [
            'error' => $error,
            'success' => $success,
            'last_username' => $request->request->get('username', ''),
            'last_email' => $request->request->get('email', ''),
            'last_first_name' => $request->request->get('first_name', ''),
            'last_last_name' => $request->request->get('last_name', ''),
            'last_phone' => $request->request->get('phone', ''),
        ]);
    }

    #[Route('/candidat/connexion', name: 'app_candidate_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('Auth/candidate_login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }
}
