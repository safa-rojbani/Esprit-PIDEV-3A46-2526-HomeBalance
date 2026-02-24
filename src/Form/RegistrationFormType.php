<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Username is required.'),
                    new Length(min: 4, max: 40, minMessage: 'Username must be at least {{ limit }} characters.'),
                ],
            ])
            ->add('first_name', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'First name is required.'),
                    new Length(max: 255),
                ],
            ])
            ->add('last_name', TextType::class, [
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
            ->add('password', PasswordType::class, [
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Password is required.'),
                    new Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters long.'),
                ],
            ])
            ->add('terms', CheckboxType::class, [
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'You must accept the privacy policy & terms to continue.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'data_class' => null,
            'allow_extra_fields' => true,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
