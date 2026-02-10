<?php

namespace App\Form\ModuleCharge;

use App\Entity\Achat;
use App\Entity\CategorieAchat;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
class AchatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomArticle', null, [
                  'required' => false,
    'label' => false,
    'attr' => [
        'placeholder' => 'Ex: Lait, Pain, Shampooing...',
        'minlength' => 2,
        'maxlength' => 255,
    ],
])
            ->add('categorie', EntityType::class, [
                'class' => CategorieAchat::class,
                'choice_label' => 'nomCategorie', 
                'placeholder' => 'Choisir une catégorie',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Achat::class,
        ]);
    }
}
