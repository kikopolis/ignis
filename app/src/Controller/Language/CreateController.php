<?php

declare(strict_types = 1);

namespace App\Controller\Language;

use App\Controller\Concerns\FlashFormErrors;
use App\Entity\Language;
use App\Entity\User;
use App\Event\Creators\IdCreateEvent;
use App\Form\Language\LanguageCreateType;
use App\Service\Contracts\Flashes;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

class CreateController extends AbstractController {
	use FlashFormErrors;
	
	public function __construct(private EntityManagerInterface $em, private LoggerInterface $logger, private EventDispatcherInterface $ed) { }
	
	/**
	 * @Route("/languages/create", name="languages-create", methods={"GET", "POST"})
	 */
	public function __invoke(Request $request): Response {
		if ($this->isGranted(User::ROLE_TEST_USER)) {
			return $this->createShowcase($request);
		}
		if ($this->isGranted(User::ROLE_ADD_LANGUAGE)) {
			return $this->create($request);
		}
		if (! $this->isGranted(User::ROLE_USER)) {
			throw $this->createAccessDeniedException();
		}
		throw $this->createNotFoundException();
	}
	
	private function create(Request $request): Response {
		$language = new Language();
		$form     = $this->createForm(LanguageCreateType::class, $language);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$this->ed->dispatch(new IdCreateEvent($language));
			try {
				$this->em->persist($language);
				$this->em->flush();
			} catch (Throwable $e) {
				$this->logger->critical($e->getMessage());
				throw $this->createNotFoundException();
			}
			$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Created a new language successfully.');
			return $this->redirectToRoute('languages-list');
		}
		$this->flashFormErrors($form);
		return $this->render('languages/create.html.twig', ['languageCreateForm' => $form->createView()]);
	}
	
	private function createShowcase(Request $request): Response {
		$language = new Language();
		$form     = $this->createForm(LanguageCreateType::class, $language);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$this->ed->dispatch(new IdCreateEvent($language));
			$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Created a new language successfully.');
			$this->addFlash(Flashes::INFO_MESSAGE, 'Actually nothing changed. Just a test user doing test user things!');
			return $this->redirectToRoute('languages-list');
		}
		$this->flashFormErrors($form);
		return $this->render('languages/create.html.twig', ['languageCreateForm' => $form->createView()]);
	}
}