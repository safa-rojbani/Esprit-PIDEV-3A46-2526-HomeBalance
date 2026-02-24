<?php

namespace App\Form\ModuleCharge;

use App\Entity\SavingGoal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SavingGoalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => "Nom de l'objectif",
                'required' => true,
            ])
            ->add('targetAmount', MoneyType::class, [
                'label' => 'Montant cible',
                'currency' => false,
                'required' => true,
                'scale' => 2,
            ])
            ->add('currentAmount', MoneyType::class, [
                'label' => 'Montant deja epargne',
                'currency' => false,
                'required' => false,
                'scale' => 2,
            ])
            ->add('targetDate', DateType::class, [
                'label' => 'Date cible (optionnel)',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SavingGoal::class,
        ]);
    }
}
