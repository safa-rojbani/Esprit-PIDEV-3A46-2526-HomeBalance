<?php

namespace App\Form;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ConversationType extends AbstractType
{
    public function __construct(private
        Security $security
        )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $family = $currentUser->getFamily();

        $builder
            ->add('conversationName', TextType::class , [
            'label' => 'Group Name (Optional for 2 people)',
            'required' => false,
            'attr' => [
                'placeholder' => 'e.g., Family Vacation Planning',
                'class' => 'form-control',
            ],
            'constraints' => [
                new Assert\Length([
                    'max' => 255,
                    'maxMessage' => 'Name cannot be longer than {{ limit }} characters',
                ]),
            ],
        ])
            ->add('participants', EntityType::class , [
            'class' => User::class ,
            'label' => 'Select Participants',
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'mapped' => false, // Handled manually in controller to create ConversationParticipant entities
            'query_builder' => function (UserRepository $er) use ($family, $currentUser) {
            return $er->createQueryBuilder('u')
            ->where('u.family = :family')
            ->andWhere('u.id != :currentUserId') // Exclude current user
            ->setParameter('family', $family)
            ->setParameter('currentUserId', $currentUser->getId())
            ->orderBy('u.FirstName', 'ASC');
        },
            'choice_label' => function (User $user) {
            return $user->getFirstName() . ' ' . $user->getLastName();
        },
            'constraints' => [
                new Assert\Count([
                    'min' => 1,
                    'minMessage' => 'You must select at least one participant',
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
