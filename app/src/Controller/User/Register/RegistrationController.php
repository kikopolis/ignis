<?php

declare(strict_types = 1);

namespace App\Controller\User\Register;

use App\Controller\Concerns\FlashFormErrors;
use App\Entity\User;
use App\Event\Creators\IdCreateEvent;
use App\Event\Creators\TimeStampableCreatedEvent;
use App\Event\Security\PasswordHashEvent;
use App\Form\User\Account\RegisterType;
use App\Security\EmailVerifier;
use App\Repository\UserRepository;
use App\Service\Contracts\Flashes;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController {
	public function __construct(private EmailVerifier $emailVerifier, private EntityManagerInterface $em) { }
	
	/**
	 * @Route("/register", name="security-register")
	 * @IsGranted("IS_ANONYMOUS")
	 */
	public function register(Request $request, EventDispatcherInterface $ed): Response {
		$user = new User();
		$form = $this->createForm(RegisterType::class, $user);
		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$ed->dispatch(new IdCreateEvent($user));
			$ed->dispatch(new PasswordHashEvent($user));
			$ed->dispatch(new TimeStampableCreatedEvent($user));
			$this->em->persist($user);
			$this->em->flush();
			$this->emailVerifier->sendEmailConfirmation(
				'verify-email', $user,
				(new TemplatedEmail())
					->from(new Address('no-reply@kikopolis.tech', 'Admin Bot'))
					->to((string) $user->getEmail())
					->subject('Please Confirm your Email')
					->htmlTemplate('registration/confirmation_email.html.twig')
			);
			return $this->redirectToRoute('home');
		}
		
		return $this->render('user/registration/register.html.twig', ['form' => $form->createView()]);
	}
	
	/**
	 * @Route("/verify/email", name="verify-email")
	 * @IsGranted("IS_ANONYMOUS")
	 */
	public function verifyUserEmail(Request $request, UserRepository $userRepository): Response {
		$id = $request->get('id');
		if ($id === null) {
			return $this->redirectToRoute('security-register');
		}
		$user = $userRepository->find($id);
		if ($user === null) {
			return $this->redirectToRoute('security-register');
		}
		try {
			$this->emailVerifier->handleEmailConfirmation($request, $user);
		} catch (VerifyEmailExceptionInterface $exception) {
			$this->addFlash(Flashes::DANGER_MESSAGE, $exception->getReason());
			return $this->redirectToRoute('security-register');
		}
		$this->addFlash(Flashes::SUCCESS_MESSAGE, 'Your email address has been verified.');
		return $this->redirectToRoute('security-login');
	}
	
	/**
	 * @Route("/register/check-email", name="security-check-email-exists", methods={"POST"})
	 * @IsGranted("IS_ANONYMOUS")
	 */
	public function emailExists(Request $request, UserRepository $userRepository): Response {
		$email = $request->get('_email');
		if ($email === null) {
			return $this->json(['message' => 'Cannot read email data from client.', Response::HTTP_BAD_REQUEST]);
		}
		$user = $userRepository->findOneBy(['email' => $email]);
		if ($user !== null) {
			return $this->json(['message' => 'Email is already registered. Click below to reset your password.'], Response::HTTP_CONFLICT);
		}
		return $this->json(['message' => 'Email is available.'], Response::HTTP_OK);
	}
}
