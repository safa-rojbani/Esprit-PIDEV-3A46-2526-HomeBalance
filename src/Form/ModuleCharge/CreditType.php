<?php

namespace App\Form\ModuleCharge;

use App\Entity\Credit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du credit/dette',
                'required' => true,
            ])
            ->add('principal', MoneyType::class, [
                'label' => 'Montant emprunte',
                'currency' => false,
                'required' => true,
                'scale' => 2,
            ])
            ->add('termMonths', IntegerType::class, [
                'label' => 'Duree (mois)',
                'required' => true,
                'attr' => ['min' => 1],
            ])
            ->add('useApiRate', CheckboxType::class, [
                'label' => 'Utiliser le taux API externe',
                'mapped' => false,
                'required' => false,
                'data' => true,
            ])
            ->add('countryCode', ChoiceType::class, [
                'label' => 'Pays de reference',
                'mapped' => false,
                'required' => true,
                'data' => 'TN',
                'choices' => [
                    'Tunisie (TN)' => 'TN',
                    'France (FR)' => 'FR',
                    'Etats-Unis (US)' => 'US',
                    'Algerie (DZ)' => 'DZ',
                ],
            ])
            ->add('annualRate', NumberType::class, [
                'label' => 'Taux annuel manuel (%)',
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'min' => 0,
                    'step' => '0.01',
                    'placeholder' => 'Ex: 8.5',
                ],
                'help' => 'Si vide et API active, le taux sera recupere automatiquement.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Credit::class,
        ]);
    }
}
