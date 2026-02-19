<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\TypeEvenement;
use App\Repository\TypeEvenementRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', null, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Ex: Reunion familiale'],
            ])
            ->add('description', null, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['placeholder' => "Details de l'evenement"],
            ])
            ->add('dateDebut', null, [
                'label' => 'Date de debut',
                'widget' => 'single_text',
            ])
            ->add('dateFin', null, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
            ])
            ->add('lieu', null, [
                'label' => 'Lieu',
                'attr' => ['placeholder' => 'Ex: Maison'],
            ])
            ->add('typeEvenement', EntityType::class, [
                'label' => "Type d'evenement",
                'class' => TypeEvenement::class,
                'choice_label' => 'nom',
                'query_builder' => static fn (TypeEvenementRepository $repo) => $repo
                    ->createQueryBuilder('t')
                    ->andWhere('t.family IS NULL')
                    ->orderBy('t.nom', 'ASC'),
                'placeholder' => 'Choisir un type',
            ])
            ->add('shareWithFamily', CheckboxType::class, [
                'required' => false,
                'label' => 'Visible pour toutes les familles',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
