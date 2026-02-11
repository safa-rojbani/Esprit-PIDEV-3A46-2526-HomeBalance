<?php

namespace App\Form\ModuleCharge;

use App\Entity\Revenu;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
class RevenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $types = $options['types'];

        $builder
            ->add('typeRevenu', ChoiceType::class, [
                'choices' => array_combine($types, $types),
                'placeholder' => 'Choisir un type (ou écrire un nouveau)',
                'required' => True,
            ])
            ->add('typeRevenuLibre', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Nouveau type (optionnel)',
            ])
            ->add('montant', MoneyType::class, [
                'currency' => 'TND',
                'required' => True,
            ])
            ->add('dateRevenu', DateType::class, [
                'widget' => 'single_text',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Revenu::class,
            'types' => [],
        ]);
    }
}
