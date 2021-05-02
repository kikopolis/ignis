<?php

declare(strict_types = 1);

namespace App\Controller\Language;

use App\Entity\Language;
use App\Entity\Role;
use App\Entity\User;
use App\Event\Creators\CreateEntityBackupEvent;
use App\Service\Contracts\Flashes;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function sprintf;

class DeleteController extends AbstractController {
	public function __construct(private EntityManagerInterface $em, private LoggerInterface $logger, private EventDispatcherInterface $ed) { }
	
	/**
	 * @Route("languages/{language_uuid}/delete", name="language-delete", methods={"GET"})
	 * @ParamConverter("language", class="App\Entity\Language", options={"mapping": {"language_uuid": "uuid"}})
	 */
	public function __invoke(Language $language): Response {
		if ($this->isGranted(Role::ROLE_TEST_USER)) {
			return $this->showcaseDelete($language);
		}
		if ($this->isGranted(Role::ROLE_DELETE_LANGUAGE, $language) && $this->isGranted(Role::ROLE_PROJECT_LEAD)) {
			return $this->delete($language);
		}
		throw $this->createNotFoundException();
	}
	
	private function delete(Language $language): Response {
		$this->ed->dispatch(new CreateEntityBackupEvent($language));
		$this->em->remove($language);
		$this->em->flush();
		/** @var User $user */
		$user = $this->getUser();
		$this->logger->info(
			sprintf(
				'User "%s" with id "%d", deleted language: "%s", with id: "%d".',
				$user->getName(), $user->getId(), $language->getName(), $language->getId()
			)
		);
		$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Deleted the language! It is now gone and forgotten!');
		// todo implement backup
		return $this->redirectToRoute('languages-list');
	}
	
	private function showcaseDelete(Language $language): Response {
		$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Deleted the language! It is now gone and forgotten!');
		$this->addFlash(Flashes::INFO_MESSAGE, 'Actually nothing changed. Just a test user doing test user things!');
		return $this->redirectToRoute('languages-list');
	}
}