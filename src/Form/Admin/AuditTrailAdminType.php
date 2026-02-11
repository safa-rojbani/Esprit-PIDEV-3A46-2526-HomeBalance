<?php

namespace App\Form\Admin;

use App\Entity\AuditTrail;
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

class AuditTrailAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('action', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 255)],
            ])
            ->add('user', EntityType::class, [
                'required' => false,
                'class' => User::class,
                'choice_label' => fn (User $user) => $user->getUserIdentifier(),
                'placeholder' => 'Select a user',
            ])
            ->add('family', EntityType::class, [
                'required' => false,
                'class' => Family::class,
                'choice_label' => fn (Family $family) => sprintf('%s (#%d)', $family->getName(), $family->getId()),
                'placeholder' => 'No family',
            ])
            ->add('channel', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 32)],
            ])
            ->add('ipAddress', TextType::class, [
                'required' => false,
                'constraints' => [new Length(max: 64)],
            ])
            ->add('userAgent', TextType::class, [
                'required' => false,
            ])
            ->add('createdAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'Created at is required.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AuditTrail::class,
            'csrf_protection' => true,
        ]);
    }
}
