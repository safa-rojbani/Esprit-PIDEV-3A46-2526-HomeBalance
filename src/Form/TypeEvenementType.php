<?php

namespace App\Form;

use App\Entity\Family;
use App\Entity\TypeEvenement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TypeEvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', null, [
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('couleur', ColorType::class, [
                'required' => false,
            ])
            ->add('description')
            ->add('family', EntityType::class, [
                'class' => Family::class,
                'choice_label' => 'id',
                'required' => false,
                'placeholder' => 'None',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TypeEvenement::class,
        ]);
    }
}
