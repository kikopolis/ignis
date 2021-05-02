<?php

declare(strict_types = 1);

namespace App\Controller\Project;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\ProjectRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ListController extends AbstractController {
	public function __construct(private ProjectRepository $projectRepository, private PaginatorInterface $paginator) { }
	
	/**
	 * @Route("/projects/{page}", name="projects-list", methods={"GET"}, defaults={"page"=1}, requirements={"page"="\d+"})
	 */
	public function __invoke(int $page): Response {
		if ($this->isGranted(Role::ROLE_TEST_USER)) {
			return $this->showcaseList($page);
		}
		if ($this->isGranted(Role::ROLE_VIEW_PROJECT)) {
			return $this->showcaseList($page);
		}
		throw $this->createNotFoundException();
	}
	
	private function showcaseList($page): Response {
		/** @var User $user */
		$user = $this->getUser();
		return $this->render(
			'projects/list.html.twig',
			[
				'pagination' => $this->paginator->paginate($this->projectRepository->findAllUserCanViewQ($user), $page, 12),
				'pageTitle'  => 'Your Projects',
			]
		);
	}
}