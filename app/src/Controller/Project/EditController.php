<?php

declare(strict_types = 1);

namespace App\Controller\Project;

use App\Entity\Project;
use App\Entity\Role;
use App\Entity\User;
use App\Event\Creators\CreateEntityHistoryEvent;
use App\Event\Updators\EntityTimeStampableUpdatedEvent;
use App\Form\Project\ProjectEditType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function sprintf;

class EditController extends AbstractController {
	public function __construct(private EntityManagerInterface $em, private LoggerInterface $logger, private EventDispatcherInterface $ed) { }
	
	/**
	 * @Route("/projects/{project_uuid}/edit", name="project-edit", methods={"GET", "POST"})
	 * @ParamConverter("project", class="App\Entity\Project", options={"mapping": {"project_uuid" = "uuid"}})
	 */
	public function __invoke(Request $request, Project $project): Response {
		if ($this->isGranted(Role::ROLE_TEST_USER)) {
			return $this->showcaseEdit($request, $project);
		}
		if ($this->isGranted(Role::ROLE_EDIT_PROJECT, $project) && $this->isGranted(Role::ROLE_PROJECT_LEAD)) {
			return $this->edit($request, $project);
		}
		throw $this->createNotFoundException();
	}
	
	private function edit(Request $request, Project $project): Response {
		$oldProject = $project;
		$form       = $this->createForm(ProjectEditType::class, $project);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$this->ed->dispatch(new EntityTimeStampableUpdatedEvent($project));
			// todo temporary edit, fix to have a more automated workflow
			if ($project->getName() !== $oldProject->getName()) {
				$this->ed->dispatch(new CreateEntityHistoryEvent($project, 'name', (string) $oldProject->getName()));
			}
			if ($project->getDescription() !== $oldProject->getDescription()) {
				$this->ed->dispatch(new CreateEntityHistoryEvent($project, 'description', (string) $oldProject->getDescription()));
			}
			try {
				$this->em->flush();
			} catch (Throwable $e) {
				$this->logger->critical($e->getMessage());
				throw $this->createNotFoundException();
			}
			/** @var User $user */
			$user = $this->getUser();
			$this->logger->info(
				sprintf(
					'User "%s" with id "%d", edited project: "%s", with id: "%d".',
					$user->getName(), $user->getId(), $project->getName(), $project->getId()
				)
			);
			return $this->redirectToRoute('project-show', ['project_uuid' => $project->getUuid()]);
		}
		return $this->render('projects/edit.html.twig', ['projectEditForm' => $form->createView(), 'project' => $project]);
	}
	
	private function showcaseEdit(Request $request, Project $project): Response {
		$projectClone = clone $project;
		$form         = $this->createForm(ProjectEditType::class, $projectClone);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$this->ed->dispatch(new EntityTimeStampableUpdatedEvent($projectClone));
			return $this->render('projects/test-show.html.twig', ['project' => $projectClone]);
		}
		return $this->render('projects/edit.html.twig', ['projectEditForm' => $form->createView(), 'project' => $projectClone]);
	}
}