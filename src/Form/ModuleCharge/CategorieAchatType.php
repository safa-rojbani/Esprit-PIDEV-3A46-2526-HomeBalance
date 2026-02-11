<?php

namespace App\Form;


use App\Entity\CategorieAchat;
use App\Entity\Family;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
class CategorieAchatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
           ->add('nomCategorie', TextType::class, [
                'label' => 'Nom de la catégorie',
                'attr' => [
        'placeholder' => 'Ex : Alimentaire, Transport, Santé…',
        'required' => true,
        'minlength' => 2,
        'maxlength' => 255,
        'pattern' => '.{2,255}', // simple
    ],
            ]);
            
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CategorieAchat::class,
        ]);
    }
}
