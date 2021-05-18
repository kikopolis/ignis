<?php

declare(strict_types = 1);

namespace App\Form\Project;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class ChooseUserAddViewerType extends AbstractType {
	public function __construct(private UserRepository $userRepository, private Security $security) { }
	
	/**
	 * @param   FormBuilderInterface<FormBuilder>   $builder
	 * @param   array<mixed, mixed>                 $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options = []): void {
		$builder
			->add(
				'_canView',
				EntityType::class,
				[
					'class'        => User::class,
					'choices'      => $this->userRepository->findAllButNot([...$builder->getData()->getCanView()->toArray(), $this->security->getUser(),]),
					'choice_label' => fn (User $user) => $user->getName(),
					'choice_value' => fn (?User $user) => $user?->getUuid(),
					'placeholder'  => 'Please choose a user to add',
					'expanded'     => false,
					'multiple'     => true,
					'mapped'       => false,
				]
			)
			->add('save', SubmitType::class)
		;
	}
	
	public function configureOptions(OptionsResolver $resolver): void {
		$resolver->setDefaults(
			[
				'data_class'      => Project::class,
				'csrf_field_name' => '_token',
				'csrf_token_id'   => '_choose_user[_csrf_token]',
			]
		);
	}
}