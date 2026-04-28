<?php

declare(strict_types=1);

namespace App\Command;

use App\AI\Domain\Enum\ChangesetStatus;
use App\AI\Domain\ValueObject\PendingChangeset;
use App\Repository\AI\PendingChangesetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:expire-changesets',
    description: 'Expire pending changesets past TTL',
)]
class ExpireChangesetsCommand extends Command
{
    private const TTL_MINUTES = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cutoff = (new \DateTimeImmutable())
            ->modify('-' . self::TTL_MINUTES . ' minutes');

        $repository = $this->em->getRepository(\App\Entity\AI\PendingChangesetEntity::class);
        $qb = $this->em->createQueryBuilder();

        $qb->select('c')
            ->from(\App\Entity\AI\PendingChangesetEntity::class, 'c')
            ->where('c.status = :status')
            ->andWhere('c.createdAt < :cutoff')
            ->setParameter('status', ChangesetStatus::PENDING->value)
            ->setParameter('cutoff', $cutoff);

        $entities = $qb->getQuery()->getResult();
        $count = 0;

        foreach ($entities as $entity) {
            $entity->setStatus(ChangesetStatus::EXPIRED->value);
            ++$count;
        }

        if ($count > 0) {
            $this->em->flush();
        }

        $output->writeln("Expired {$count} pending changeset(s).");

        return Command::SUCCESS;
    }
}