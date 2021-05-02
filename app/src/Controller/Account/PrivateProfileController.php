<?php

declare(strict_types = 1);

namespace App\Controller\Account;

use App\Entity\Role;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PrivateProfileController extends AbstractController {
	/**
	 * @Route("/profile", name="profile-show-self", methods={"GET"})
	 */
	public function __invoke(): Response {
		if ($this->isGranted(Role::ROLE_TEST_USER)) {
			return $this->render('account/show-self.html.twig', ['user' => $this->getUser()]);
		}
		if ($this->isGranted(Role::ROLE_USER)) {
			return $this->render('account/show-self.html.twig', ['user' => $this->getUser()]);
		}
		throw $this->createNotFoundException();
	}
}