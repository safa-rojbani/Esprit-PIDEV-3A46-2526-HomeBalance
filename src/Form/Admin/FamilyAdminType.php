<?php

namespace App\Form\Admin;

use App\Entity\Family;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class FamilyAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Family name is required.'),
                    new Length(max: 255),
                ],
            ])
            ->add('joinCode', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('codeExpiresAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
            ])
            ->add('createdBy', EntityType::class, [
                'required' => false,
                'class' => User::class,
                'choice_label' => fn (User $user) => $user->getUserIdentifier(),
                'placeholder' => 'Select a creator',
                'constraints' => [new NotBlank(message: 'Creator is required.')],
            ])
            ->add('createdAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'Created at is required.')],
            ])
            ->add('updatedAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
                'input' => 'datetime_immutable',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Family::class,
            'csrf_protection' => true,
        ]);
    }
}
