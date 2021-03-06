<?php

declare(strict_types = 1);

namespace App\Repository;

use App\Entity\Language;
use App\Repository\Concerns\RepositoryUuidFinderConcern;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Language|null find($id, $lockMode = null, $lockVersion = null)
 * @method Language|null findOneBy(array $criteria, array $orderBy = null)
 * @method Language[]    findAll()
 * @method Language[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LanguageRepository extends ServiceEntityRepository {
	use RepositoryUuidFinderConcern;
	
	public function __construct(ManagerRegistry $registry) {
		parent::__construct($registry, Language::class);
	}
}
