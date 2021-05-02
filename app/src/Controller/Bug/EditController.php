<?php

declare(strict_types = 1);

namespace App\Controller\Bug;

use App\Entity\Bug;
use App\Entity\Project;
use App\Entity\Role;
use App\Entity\User;
use App\Event\Creators\CreateEntityHistoryEvent;
use App\Event\Updators\EntityTimeStampableUpdatedEvent;
use App\Form\Bug\BugEditType;
use App\Service\Contracts\Flashes;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use function dd;
use function sprintf;

class EditController extends AbstractController {
	public function __construct(private EntityManagerInterface $em, private LoggerInterface $logger, private EventDispatcherInterface $ed) { }
	
	/**
	 * @Route("/projects/{project_uuid}/bugs/{bug_uuid}/edit", name="bug-edit", methods={"GET", "POST", "PUT"})
	 * @ParamConverter("project", class="App\Entity\Project", options={"mapping": {"project_uuid" = "uuid"}})
	 * @ParamConverter("bug", class="App\Entity\Bug", options={"mapping":{"bug_uuid" = "uuid"}})
	 */
	public function __invoke(Request $request, Project $project, Bug $bug): Response {
		if ($this->isGranted(Role::ROLE_TEST_USER)) {
			return $this->showcaseEdit($request, $project, $bug);
		}
		if ($this->isGranted(Role::ROLE_VIEW_PROJECT, $project) && $this->isGranted(Role::ROLE_EDIT_BUG, $bug)) {
			return $this->edit($request, $project, $bug);
		}
		throw $this->createNotFoundException();
		
	}
	
	private function edit(Request $request, Project $project, Bug $bug): Response {
		$oldBug = $bug;
		$form   = $this->createForm(BugEditType::class, $bug);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$this->ed->dispatch(new EntityTimeStampableUpdatedEvent($bug));
			// todo temporary edit, fix to have a more automated workflow
			if ($bug->getTitle() !== $oldBug->getTitle()) {
				$this->ed->dispatch(new CreateEntityHistoryEvent($bug, 'title', (string) $oldBug->getTitle()));
			}
			if ($bug->getDescription() !== $oldBug->getDescription()) {
				$this->ed->dispatch(new CreateEntityHistoryEvent($bug, 'description', (string) $oldBug->getDescription()));
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
					'User "%s" with id "%d", edited bug: "%s", with id: "%d".',
					$user->getName(), $user->getId(), $bug->getTitle(), $bug->getId()
				)
			);
			$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Bug edited! Your changes were saved successfully.');
			// todo implement backup
			return $this->redirectToRoute('project-show-bugs', ['project_uuid' => $project->getUuid()]);
		}
		return $this->render('bugs/edit.html.twig', ['project' => $project, 'bug' => $bug, 'bugEditForm' => $form->createView()]);
	}
	
	private function showcaseEdit(Request $request, Project $project, Bug $bug): Response {
		$bugTemp = clone $bug;
		$form    = $this->createForm(BugEditType::class, $bugTemp);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Bug edited! Your changes were saved successfully.');
			$this->addFlash(Flashes::INFO_MESSAGE, 'Actually nothing changed. Just a test user doing test user things!');
			return $this->render('bugs/test-show.html.twig', ['bug' => $bugTemp]);
		}
		return $this->render('bugs/edit.html.twig', ['project' => $project, 'bug' => $bugTemp, 'bugEditForm' => $form->createView()]);
	}
}