<?php

namespace App\Service\AI\Tool\Communication;

use App\Service\AI\Tool\ToolInterface;
use App\Repository\Recrutement\ApplicationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bundle\SecurityBundle\Security;

class SendCandidateEmailTool implements ToolInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private ApplicationRepository $applicationRepository,
        private Security $security
    ) {}

    public function getName(): string
    {
        return 'send_candidate_email';
    }

    public function getDefinition(): array
    {
        return [
            'name' => 'send_candidate_email',
            'description' => 'Sends a personalized email to a candidate regarding their application.',
            'parameters' => [
                'type' => 'object',
                'properties' => (object)[
                    'application_id' => [
                        'type' => 'integer',
                        'description' => 'The ID of the application'
                    ],
                    'subject' => [
                        'type' => 'string',
                        'description' => 'The email subject'
                    ],
                    'body' => [
                        'type' => 'string',
                        'description' => 'The email body content (plain text or basic HTML)'
                    ]
                ],
                'required' => ['application_id', 'subject', 'body'],
                'additionalProperties' => false
            ]
        ];
    }

    public function execute(array $args): mixed
    {
        $user = $this->security->getUser();
        if (!$user) return ['error' => 'Non authentifié'];

        $app = $this->applicationRepository->find($args['application_id']);
        if (!$app) return ['error' => "Candidature non trouvée"];

        // Check ownership
        if ($app->getJobOffer()?->getCreatedBy() !== $user->getId()) {
            return ['error' => "Vous n'avez pas l'autorisation d'envoyer un email pour cette candidature."];
        }

        $email = (new Email())
            ->from('hr@hrflow.ai')
            ->to($app->getEmailAddress())
            ->subject($args['subject'])
            ->html(nl2br($args['body']));

        $this->mailer->send($email);

        return [
            'status' => 'sent',
            'recipient' => $app->getCandidateName(),
            'email' => $app->getEmailAddress(),
            'subject' => $args['subject'],
            'message' => "L'email a été envoyé avec succès à {$app->getCandidateName()}."
        ];
    }
}
