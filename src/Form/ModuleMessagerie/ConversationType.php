<?php

namespace App\Form\ModuleMessagerie;

use App\Entity\Conversation;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConversationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('conversationName', TextType::class , [
            'label' => 'Conversation Name (for groups)',
            'required' => false,
            'attr' => [
                'class' => 'form-control',
                'placeholder' => 'Enter group name...',
            ],
        ])
            ->add('participants', EntityType::class , [
            'class' => User::class ,
            'multiple' => true,
            'expanded' => false,
            'label' => 'Select Participants',
            'choice_label' => function (User $user) {
            return $user->getFirstName() . ' ' . $user->getLastName();
        },
            'mapped' => false,
            'attr' => [
                'class' => 'form-select select2',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Conversation::class ,
        ]);
    }
}
