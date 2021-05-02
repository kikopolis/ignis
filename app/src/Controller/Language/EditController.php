<?php

declare(strict_types = 1);

namespace App\Controller\Language;

use App\Entity\Language;
use App\Entity\Role;
use App\Entity\User;
use App\Event\Creators\CreateEntityHistoryEvent;
use App\Form\Language\LanguageEditType;
use App\Service\Contracts\Flashes;
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
	 * @Route("/languages/{language_uuid}/edit", name="language-edit", methods={"GET", "POST"})
	 * @ParamConverter("language", class="App\Entity\Language", options={"mapping": {"language_uuid": "uuid"}})
	 * @IsGranted("ROLE_PROJECT_LEAD")
	 * @IsGranted("ROLE_EDIT_LANGUAGE", subject="language")
	 */
	public function __invoke(Request $request, Language $language): Response {
		if ($this->isGranted(Role::ROLE_TEST_USER)) {
			return $this->showcaseEdit($request, $language);
		}
		if ($this->isGranted(Role::ROLE_EDIT_LANGUAGE, $language) && $this->isGranted(Role::ROLE_PROJECT_LEAD)) {
			return $this->edit($request, $language);
		}
		throw $this->createNotFoundException();
	}
	
	private function edit(Request $request, Language $language): Response {
		$oldLanguage = $language;
		$form        = $this->createForm(LanguageEditType::class, $language);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			// todo temporary edit, fix to have a more automated workflow
			if ($language->getName() !== $oldLanguage->getName()) {
				$this->ed->dispatch(new CreateEntityHistoryEvent($language, 'name', (string) $oldLanguage->getName()));
			}
			if ($language->getDescription() !== $oldLanguage->getDescription()) {
				$this->ed->dispatch(new CreateEntityHistoryEvent($language, 'description', (string) $oldLanguage->getDescription()));
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
					'User "%s" with id "%d", edited language: "%s", with id: "%d".',
					$user->getName(), $user->getId(), $language->getName(), $language->getId()
				)
			);
			$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Language edited! Your changes were saved successfully.');
			return $this->redirectToRoute('languages-list');
		}
		return $this->render('languages/edit.html.twig', ['language' => $language, 'languageEditForm' => $form->createView()]);
	}
	
	private function showcaseEdit(Request $request, Language $language): Response {
		$languageClone = clone $language;
		$form          = $this->createForm(LanguageEditType::class, $languageClone);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Language edited! Your changes were saved successfully.');
			$this->addFlash(Flashes::INFO_MESSAGE, 'Actually nothing changed. Just a test user doing test user things!');
			return $this->render('languages/test-show.html.twig', ['language' => $languageClone]);
		}
		return $this->render('languages/edit.html.twig', ['language' => $languageClone, 'languageEditForm' => $form->createView()]);
	}
}