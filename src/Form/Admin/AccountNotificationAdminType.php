<?php

namespace App\Form\Admin;

use App\Entity\AccountNotification;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AccountNotificationAdminType extends AbstractType
{
    private const STATUSES = ['PENDING', 'SENT', 'FAILED', 'SKIPPED'];
    private const CHANNELS = ['email', 'browser', 'app'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'required' => false,
                'class' => User::class,
                'choice_label' => fn (User $user) => $user->getUserIdentifier(),
                'constraints' => [new NotBlank(message: 'User is required.')],
            ])
            ->add('key', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Notification key is required.'),
                    new Length(max: 64),
                ],
            ])
            ->add('channel', ChoiceType::class, [
                'required' => false,
                'choices' => array_combine(self::CHANNELS, self::CHANNELS),
                'constraints' => [new NotBlank(message: 'Channel is required.')],
            ])
            ->add('status', ChoiceType::class, [
                'required' => false,
                'choices' => array_combine(self::STATUSES, self::STATUSES),
                'constraints' => [new NotBlank(message: 'Status is required.')],
            ])
            ->add('createdAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'Created at is required.')],
            ])
            ->add('sentAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
                'input' => 'datetime_immutable',
            ])
            ->add('lastError', TextType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AccountNotification::class,
            'csrf_protection' => true,
        ]);
    }
}
