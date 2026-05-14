<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DocsController extends AbstractController
{
    private const TEMPLATE_MAP = [
        ''                                    => 'docs/index.html.twig',
        'index'                               => 'docs/index.html.twig',
        'getting-started/login'               => 'docs/getting-started/login.html.twig',
        'getting-started/first-steps'         => 'docs/getting-started/first-steps.html.twig',
        'installation/linux'                  => 'docs/installation/linux.html.twig',
        'installation/windows'                => 'docs/installation/windows.html.twig',
        'installation/macos'                  => 'docs/installation/macos.html.twig',
        'installation/database'               => 'docs/installation/database.html.twig',
        'user-guides/admin'                   => 'docs/user-guides/admin.html.twig',
        'user-guides/hr-manager'              => 'docs/user-guides/hr-manager.html.twig',
        'user-guides/employee'                => 'docs/user-guides/employee.html.twig',
        'modules/recruitment'                 => 'docs/modules/recruitment.html.twig',
        'modules/training'                    => 'docs/modules/training.html.twig',
        'modules/leave'                       => 'docs/modules/leave.html.twig',
        'modules/payroll'                     => 'docs/modules/payroll.html.twig',
        'modules/employee-relations'          => 'docs/modules/employee-relations.html.twig',
        'modules/ai-reporting'                => 'docs/modules/ai-reporting.html.twig',
        'faq'                                 => 'docs/faq.html.twig',
        'troubleshooting'                     => 'docs/troubleshooting.html.twig',
    ];

    #[Route('/docs/{path}', name: 'app_docs', requirements: ['path' => '.*'])]
    public function docs(string $path = ''): Response
    {
        if (!isset(self::TEMPLATE_MAP[$path])) {
            throw $this->createNotFoundException('Documentation page not found');
        }

        return $this->render(self::TEMPLATE_MAP[$path]);
    }
}
