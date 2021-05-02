<?php

declare(strict_types = 1);

namespace App\Controller\Security\Login;

use App\Controller\Concerns\FlashFormErrors;
use App\Form\User\PasswordForgottenType;
use App\Repository\UserRepository;
use App\Service\Contracts\Flashes;
use App\Service\Mailer;
use App\Service\TimeCreator;
use App\Service\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PasswordForgottenController extends AbstractController {
	use FlashFormErrors;
	
	public function __construct(
		private UserRepository $userRepository, private TokenGenerator $token, private EntityManagerInterface $em, private Mailer $mailer
	) {
	}
	
	/**
	 * @Route("/credentials/request/forgotten-password", name="credentials-forgot-password", methods={"GET", "POST"})
	 * @IsGranted("IS_ANONYMOUS")
	 */
	public function __invoke(Request $request): Response {
		$form = $this->createForm(PasswordForgottenType::class);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$user = $this->userRepository->findOneBy(['email' => $form->get('_email')->getData()]);
			if (! $user) {
				return $this->returnSuccess();
			}
			$user
				->setPasswordResetToken($this->token->alphanumericToken(64))
				->setPasswordResetTokenRequestedAt(TimeCreator::now())
				->setPasswordResetTokenRequestedFromIp($request->getClientIp())
			;
			$this->em->flush();
			$this->mailer->htmlMessage(
				(string) $user->getEmail(),
				'Password reset token requested.',
				'base/mail-templates/forgot-password.html.twig',
				['user' => $user]
			);
			return $this->returnSuccess();
		}
		$this->flashFormErrors($form);
		return $this->render('security/login/password-forgotten.html.twig', ['form' => $form]);
	}
	
	private function returnSuccess(): RedirectResponse {
		$this->addFlash(
			Flashes::SUCCESS_MESSAGE,
			"You have successfully requested a password reset token. Please check your email and follow the instructions. It may take some time for the email to arrive."
		);
		
		return $this->redirectToRoute('home');
	}
}