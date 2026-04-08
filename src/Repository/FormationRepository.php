<?php

namespace App\Repository;

use App\Entity\Formation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Formation>
 */
class FormationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Formation::class);
    }

    /** @return Formation[] */
    public function findByRh(int $rhId, string $search = '', string $type = '', string $sort = 'createdAt', string $dir = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.rhId = :rhId')
            ->setParameter('rhId', $rhId);

        if ($search !== '') {
            $qb->andWhere('f.titre LIKE :search OR f.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($type !== '') {
            $qb->andWhere('f.type = :type')
               ->setParameter('type', $type);
        }

        $allowedSorts = ['createdAt', 'titre', 'duree'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'createdAt';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('f.' . $sort, $dir);

        return $qb->getQuery()->getResult();
    }

    public function getStatsByRh(int $rhId): array
    {
        $total = (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getSingleScalarResult();

        $activeSessions = (int) $this->getEntityManager()->createQuery(
            'SELECT COUNT(s.id)
             FROM App\Entity\SessionFormation s
             JOIN s.formation f
             WHERE s.statut = :statut AND f.rhId = :rhId'
        )
        ->setParameter('statut', 'En cours')
        ->setParameter('rhId', $rhId)
        ->getSingleScalarResult();

        $totalParticipants = (int) $this->getEntityManager()->createQuery(
            'SELECT COUNT(p.id)
             FROM App\Entity\ParticipationFormation p
             JOIN p.session s
             JOIN s.formation f
             WHERE f.rhId = :rhId'
        )
        ->setParameter('rhId', $rhId)
        ->getSingleScalarResult();

        return [
            'total_formations' => $total,
            'active_sessions' => $activeSessions,
            'total_participants' => $totalParticipants,
        ];
    }
}
