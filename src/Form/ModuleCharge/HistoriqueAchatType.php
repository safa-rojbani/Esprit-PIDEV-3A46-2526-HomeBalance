<?php

namespace App\Form;

use App\Entity\Achat;
use App\Entity\Family;
use App\Entity\HistoriqueAchat;
use App\Entity\Revenu;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HistoriqueAchatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montantAchete')
            ->add('quantiteAchete')
            ->add('dateAchat', null, [
                'widget' => 'single_text',
            ])
            ->add('achat', EntityType::class, [
                'class' => Achat::class,
                'choice_label' => 'id',
            ])
            ->add('revenu', EntityType::class, [
                'class' => Revenu::class,
                'choice_label' => 'id',
            ])
            ->add('family', EntityType::class, [
                'class' => Family::class,
                'choice_label' => 'id',
            ])
            ->add('paidBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HistoriqueAchat::class,
        ]);
    }
}
