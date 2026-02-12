<?php

namespace App\Form;

use App\Entity\Rappel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RappelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('offsetMinutes', IntegerType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Le delai est obligatoire.'),
                    new Assert\Positive(message: 'Le delai doit etre un nombre positif.'),
                ],
                'label' => 'Minutes avant l\'événement',
                'attr' => ['placeholder' => 'Ex: 60'],
            ])
            ->add('canal', ChoiceType::class, [
                'choices' => [
                    'Popup' => 'popup',
                    'Email' => 'email',
                    'SMS' => 'sms',
                ],
                'constraints' => [new Assert\NotBlank(message: 'Le canal est obligatoire.')],
                'label' => 'Canal',
            ])
            ->add('actif', CheckboxType::class, [
                'required' => false,
                'label' => 'Actif',
            ])
            ->add('estLu', CheckboxType::class, [
                'required' => false,
                'label' => 'Lu',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Rappel::class,
        ]);
    }
}
