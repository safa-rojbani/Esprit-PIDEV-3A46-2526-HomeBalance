<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;

class AccountProfileFormType extends AbstractType
{
    public function getBlockPrefix(): string
    {
        return '';
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'First name is required.'),
                    new Length(max: 255),
                ],
            ])
            ->add('lastName', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Last name is required.'),
                    new Length(max: 255),
                ],
            ])
            ->add('email', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Email is required.'),
                    new EmailConstraint(message: 'Please provide a valid email address.'),
                ],
            ])
            ->add('organization', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('phoneNumber', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 32)],
            ])
            ->add('address', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('state', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('zipCode', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 12)],
            ])
            ->add('country', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('language', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 10)],
            ])
            ->add('timeZones', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 32)],
            ])
            ->add('currency', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 32)],
            ])
            ->add('avatar', FileType::class, [
                'required' => false,
                'mapped' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'data_class' => null,
        ]);
    }
}
