<?php

namespace App\Repository\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Formation\SessionFeedback;
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

    /** @return Formation[] */
    public function findAllFiltered(string $search = '', string $type = '', string $sort = 'createdAt', string $dir = 'DESC', string $organisme = ''): array
    {
        $qb = $this->createQueryBuilder('f');

        if ($search !== '') {
            $qb->andWhere('f.titre LIKE :search OR f.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($type !== '') {
            $qb->andWhere('f.type = :type')
               ->setParameter('type', $type);
        }

        if ($organisme !== '') {
            $qb->andWhere('f.organisme = :organisme')
               ->setParameter('organisme', $organisme);
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
             FROM App\Entity\Formation\SessionFormation s
             JOIN s.formation f
             WHERE s.statut = :statut AND f.rhId = :rhId'
        )
        ->setParameter('statut', 'En cours')
        ->setParameter('rhId', $rhId)
        ->getSingleScalarResult();

        $totalParticipants = (int) $this->getEntityManager()->createQuery(
            'SELECT COUNT(p.id)
             FROM App\Entity\Formation\ParticipationFormation p
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

    /** @return array<int, array<string, mixed>> */
    public function findTopFormationsByRh(int $rhId, int $limit = 5): array
    {
        return $this->createQueryBuilder('f')
            ->select('f.id AS formationId, f.titre AS titre, f.organisme AS organisme, COUNT(DISTINCT p.id) AS demandesCount, COUNT(DISTINCT sf.id) AS feedbackCount, AVG(sf.rating) AS averageRating')
            ->leftJoin('f.sessions', 's')
            ->leftJoin('s.participations', 'p')
            ->leftJoin(SessionFeedback::class, 'sf', 'WITH', 'sf.formation = f')
            ->where('f.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->groupBy('f.id')
            ->orderBy('demandesCount', 'DESC')
            ->addOrderBy('averageRating', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /** @return array<int, array<string, mixed>> */
    public function findTopFormateursByRh(int $rhId, int $limit = 5): array
    {
        $demandRows = $this->createQueryBuilder('f')
            ->select('f.organisme AS organisme, COUNT(DISTINCT f.id) AS formationsCount, COUNT(DISTINCT p.id) AS demandesCount')
            ->leftJoin('f.sessions', 's')
            ->leftJoin('s.participations', 'p')
            ->where('f.rhId = :rhId')
            ->andWhere('f.organisme IS NOT NULL')
            ->andWhere('f.organisme != :empty')
            ->setParameter('rhId', $rhId)
            ->setParameter('empty', '')
            ->groupBy('f.organisme')
            ->getQuery()
            ->getArrayResult();

        $ratingRows = $this->getEntityManager()->createQueryBuilder()
            ->select('f.organisme AS organisme, COUNT(sf.id) AS feedbackCount, AVG(sf.rating) AS averageRating')
            ->from(SessionFeedback::class, 'sf')
            ->join('sf.formation', 'f')
            ->where('f.rhId = :rhId')
            ->andWhere('f.organisme IS NOT NULL')
            ->andWhere('f.organisme != :empty')
            ->setParameter('rhId', $rhId)
            ->setParameter('empty', '')
            ->groupBy('f.organisme')
            ->getQuery()
            ->getArrayResult();

        $ratingMap = [];
        foreach ($ratingRows as $row) {
            $ratingMap[(string) $row['organisme']] = [
                'feedbackCount' => (int) ($row['feedbackCount'] ?? 0),
                'averageRating' => isset($row['averageRating']) ? round((float) $row['averageRating'], 1) : null,
            ];
        }

        $rows = [];
        foreach ($demandRows as $row) {
            $org = (string) ($row['organisme'] ?? '');
            $ratings = $ratingMap[$org] ?? ['feedbackCount' => 0, 'averageRating' => null];

            $rows[] = [
                'organisme' => $org,
                'formationsCount' => (int) ($row['formationsCount'] ?? 0),
                'demandesCount' => (int) ($row['demandesCount'] ?? 0),
                'feedbackCount' => (int) $ratings['feedbackCount'],
                'averageRating' => $ratings['averageRating'],
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $cmpDemandes = $b['demandesCount'] <=> $a['demandesCount'];
            if ($cmpDemandes !== 0) {
                return $cmpDemandes;
            }

            $cmpRating = ($b['averageRating'] ?? 0) <=> ($a['averageRating'] ?? 0);
            if ($cmpRating !== 0) {
                return $cmpRating;
            }

            return strcmp($a['organisme'], $b['organisme']);
        });

        return array_slice($rows, 0, max(1, $limit));
    }
}
