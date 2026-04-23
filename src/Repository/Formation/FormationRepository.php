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

    /**
     * @return array{
     *   total_formations:int,
     *   total_participations:int,
     *   accepted_participations:int,
     *   participation_rate:float,
     *   formations_by_month:array<string,int>,
     *   formations_by_category:array<string,int>,
     *   participation_status_counts:array<string,int>
     * }
     */
    public function getRhDashboardMetrics(int $rhId, int $months = 6): array
    {
        $months = max(3, min(12, $months));
        $startMonth = (new \DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months');

        $totalFormations = (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->getQuery()
            ->getSingleScalarResult();

        $totalParticipations = (int) $this->getEntityManager()->createQuery(
            'SELECT COUNT(p.id)
             FROM App\Entity\Formation\ParticipationFormation p
             JOIN p.session s
             JOIN s.formation f
             WHERE f.rhId = :rhId'
        )
        ->setParameter('rhId', $rhId)
        ->getSingleScalarResult();

        $acceptedParticipations = (int) $this->getEntityManager()->createQuery(
            'SELECT COUNT(p.id)
             FROM App\Entity\Formation\ParticipationFormation p
             JOIN p.session s
             JOIN s.formation f
             WHERE f.rhId = :rhId AND p.statutParticipation IN (:acceptedStatuses)'
        )
        ->setParameter('rhId', $rhId)
        ->setParameter('acceptedStatuses', ['Accepte', 'Certificat obtenu'])
        ->getSingleScalarResult();

        $participationRate = $totalParticipations > 0
            ? round(($acceptedParticipations / $totalParticipations) * 100, 1)
            : 0.0;

        $categoryRows = $this->createQueryBuilder('f')
            ->select('f.type AS category, COUNT(f.id) AS total')
            ->where('f.rhId = :rhId')
            ->setParameter('rhId', $rhId)
            ->groupBy('f.type')
            ->getQuery()
            ->getArrayResult();

        $categoryMap = [];
        foreach ($categoryRows as $row) {
            $category = trim((string) ($row['category'] ?? 'Autre'));
            if ($category === '') {
                $category = 'Autre';
            }
            $categoryMap[$category] = (int) ($row['total'] ?? 0);
        }

        $statusRows = $this->getEntityManager()->createQuery(
            'SELECT p.statutParticipation AS status, COUNT(p.id) AS total
             FROM App\Entity\Formation\ParticipationFormation p
             JOIN p.session s
             JOIN s.formation f
             WHERE f.rhId = :rhId
             GROUP BY p.statutParticipation'
        )
        ->setParameter('rhId', $rhId)
        ->getArrayResult();

        $statusBuckets = [
            'accepted' => 0,
            'refused' => 0,
            'pending' => 0,
        ];

        foreach ($statusRows as $row) {
            $status = mb_strtolower(trim((string) ($row['status'] ?? '')));
            $count = (int) ($row['total'] ?? 0);

            if (in_array($status, ['accepte', 'certificat obtenu'], true)) {
                $statusBuckets['accepted'] += $count;
                continue;
            }

            if ($status === 'refuse') {
                $statusBuckets['refused'] += $count;
                continue;
            }

            if (in_array($status, ['inscrit', 'en attente'], true)) {
                $statusBuckets['pending'] += $count;
            }
        }

        $monthMap = [];
        for ($i = 0; $i < $months; $i++) {
            $current = $startMonth->modify('+' . $i . ' months');
            $monthMap[$current->format('M Y')] = 0;
        }

        $formationRows = $this->createQueryBuilder('f')
            ->select('f.createdAt AS createdAt')
            ->where('f.rhId = :rhId')
            ->andWhere('f.createdAt >= :startMonth')
            ->setParameter('rhId', $rhId)
            ->setParameter('startMonth', $startMonth)
            ->orderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        foreach ($formationRows as $row) {
            $createdAt = $row['createdAt'] ?? null;
            if (!$createdAt instanceof \DateTimeInterface) {
                continue;
            }

            $label = $createdAt->format('M Y');
            if (array_key_exists($label, $monthMap)) {
                $monthMap[$label]++;
            }
        }

        return [
            'total_formations' => $totalFormations,
            'total_participations' => $totalParticipations,
            'accepted_participations' => $acceptedParticipations,
            'participation_rate' => $participationRate,
            'formations_by_month' => $monthMap,
            'formations_by_category' => $categoryMap,
            'participation_status_counts' => $statusBuckets,
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
