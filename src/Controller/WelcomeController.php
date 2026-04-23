<?php

namespace App\Controller;

use App\Service\Shared\ExternalApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
    public function admin(Request $request, ExternalApiService $api): Response
    {
        $refresh = $request->query->get('refresh');
        $all = $refresh === 'all';

        return $this->render('DashboardAdmin/welcome_admin.html.twig', [
            'user' => $this->getUser(),
            'weather' => $api->getWeatherTunis($all || $refresh === 'weather'),
            'forecast' => $api->getForecastTunis($all || $refresh === 'forecast'),
            'rates' => $api->getExchangeRates($all || $refresh === 'rates'),
            'news' => $api->getBusinessNews('économie entreprise', $all || $refresh === 'news'),
            'holidays' => $api->getUpcomingHolidaysTN($all || $refresh === 'holidays'),
            'quote' => $api->getDailyQuote('business', $all || $refresh === 'quote'),
            'serverTime' => new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis')),
        ]);
    }

    #[Route('/welcome/rh', name: 'app_welcome_rh')]
    #[IsGranted('ROLE_RH')]
    public function rh(Request $request, ExternalApiService $api): Response
    {
        $refresh = $request->query->get('refresh');
        $all = $refresh === 'all';

        return $this->render('DashboardHr/welcome_rh.html.twig', [
            'user' => $this->getUser(),
            'weather' => $api->getWeatherTunis($all || $refresh === 'weather'),
            'forecast' => $api->getForecastTunis($all || $refresh === 'forecast'),
            'holidays' => $api->getUpcomingHolidaysTN($all || $refresh === 'holidays'),
            'news' => $api->getBusinessNews('ressources humaines', $all || $refresh === 'news'),
            'quote' => $api->getDailyQuote('leadership', $all || $refresh === 'quote'),
            'rates' => $api->getExchangeRates($all || $refresh === 'rates'),
            'serverTime' => new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis')),
        ]);
    }

    #[Route('/welcome/employee', name: 'app_welcome_employee')]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function employee(Request $request, ExternalApiService $api): Response
    {
        $refresh = $request->query->get('refresh');
        $all = $refresh === 'all';
        $holidays = $api->getUpcomingHolidaysTN($all || $refresh === 'holidays');

        return $this->render('DashboardEmployee/welcome_employee.html.twig', [
            'user' => $this->getUser(),
            'weather' => $api->getWeatherTunis($all || $refresh === 'weather'),
            'forecast' => $api->getForecastTunis($all || $refresh === 'forecast'),
            'quote' => $api->getDailyQuote('motivational', $all || $refresh === 'quote'),
            'advice' => $api->getAdviceOfTheDay($all || $refresh === 'advice'),
            'nextHoliday' => $holidays[0] ?? null,
            'holidays' => $holidays,
            'news' => $api->getBusinessNews('bien-être travail', $all || $refresh === 'news'),
            'serverTime' => new \DateTimeImmutable('now', new \DateTimeZone('Africa/Tunis')),
        ]);
    }
}
