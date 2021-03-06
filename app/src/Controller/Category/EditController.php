<?php

declare(strict_types = 1);

namespace App\Controller\Category;

use App\Controller\Concerns\FlashFormErrors;
use App\Entity\Category;
use App\Entity\User;
use App\Event\Creators\VersionCreateEvent;
use App\Form\Category\CategoryEditType;
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
use function sprintf;

class EditController extends AbstractController {
	use FlashFormErrors;
	
	public function __construct(private EntityManagerInterface $em, private LoggerInterface $logger, private EventDispatcherInterface $ed) { }
	
	/**
	 * @Route("/categories/{category_uuid}/edit", name="category-edit", methods={"GET", "POST"})
	 * @ParamConverter("category", class="App\Entity\Category", options={"mapping": {"category_uuid": "uuid"}})
	 */
	public function __invoke(Request $request, Category $category): Response {
		if ($this->isGranted(User::ROLE_TEST_USER)) {
			return $this->showcaseEdit($request, $category);
		}
		if ($this->isGranted(User::ROLE_EDIT_CATEGORY, $category)) {
			return $this->edit($request, $category);
		}
		if (! $this->isGranted(User::ROLE_USER)) {
			throw $this->createAccessDeniedException();
		}
		throw $this->createNotFoundException();
	}
	
	private function edit(Request $request, Category $category): Response {
		$oldName = $category->getName();
		$form    = $this->createForm(CategoryEditType::class, $category);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			// todo temporary edit, fix to have a more automated workflow
			if ($category->getName() !== $oldName) {
				$this->ed->dispatch(new VersionCreateEvent($category, 'name', (string) $oldName));
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
					'User "%s" with id "%d", edited category: "%s", with id: "%d".',
					$user->getName(), $user->getId(), $category->getName(), $category->getId()
				)
			);
			$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Category edited! Your changes were saved successfully.');
			return $this->redirectToRoute('category-show', ['category_uuid' => $category->getUuid()]);
		}
		$this->flashFormErrors($form);
		return $this->render('categories/edit.html.twig', ['category' => $category, 'categoryEditForm' => $form->createView()]);
	}
	
	private function showcaseEdit(Request $request, Category $category): Response {
		$categoryTemp = clone $category;
		$form         = $this->createForm(CategoryEditType::class, $categoryTemp);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Category edited! Your changes were saved successfully.');
			$this->addFlash(Flashes::INFO_MESSAGE, 'Actually nothing changed. Just a test user doing test user things!');
			return $this->render('categories/test-show.html.twig', ['category' => $categoryTemp]);
		}
		$this->flashFormErrors($form);
		return $this->render('categories/edit.html.twig', ['category' => $categoryTemp, 'categoryEditForm' => $form->createView()]);
	}
}