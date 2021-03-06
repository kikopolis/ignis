<?php

declare(strict_types = 1);

namespace App\Controller\Project;

use App\Entity\Project;
use App\Entity\User;
use App\Event\Updators\DeleteEvent;
use App\Service\Contracts\Flashes;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DeleteController extends AbstractController {
	public function __construct(private EntityManagerInterface $em, private LoggerInterface $logger, private EventDispatcherInterface $ed) { }
	
	/**
	 * @Route("/projects/{project_uuid}/delete", name="project-delete", methods={"GET", "DELETE"})
	 * @ParamConverter("project", class="App\Entity\Project", options={"mapping": {"project_uuid" = "uuid"}})
	 */
	public function __invoke(Project $project): Response {
		if ($this->isGranted(User::ROLE_TEST_USER)) {
			return $this->showcaseDelete();
		}
		if ($this->isGranted(User::ROLE_DELETE_PROJECT, $project)) {
			return $this->delete($project);
		}
		if (! $this->isGranted(User::ROLE_USER)) {
			throw $this->createAccessDeniedException();
		}
		throw $this->createNotFoundException();
	}
	
	private function delete(Project $project): Response {
		$this->ed->dispatch(new DeleteEvent($project));
		$this->em->flush();
		/** @var User $user */
		$user = $this->getUser();
		$this->logger->info(
			sprintf(
				'User "%s" with id "%d", removed project: "%s", with id: "%d".',
				$user->getName(), $user->getId(), $project->getName(), $project->getId()
			)
		);
		if ($project->getHardDeleted()) {
			$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Deleted the project! It is now gone and forgotten!');
		} else {
			$this->addFlash(Flashes::SUCCESS_MESSAGE, 'The project is now soft deleted to trash! Only admins and project author can see it.');
		}
		return $this->redirectToRoute('home');
	}
	
	private function showcaseDelete(): Response {
		$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Deleted the project! It is now gone and forgotten!');
		$this->addFlash(Flashes::INFO_MESSAGE, 'Actually nothing changed. Just a test user doing test user things!');
		return $this->redirectToRoute('home');
	}
}