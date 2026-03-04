<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Family;
use App\Entity\TypeEvenement;
use App\Repository\TypeEvenementRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Family|null $family */
        $family = $options['family'];

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
            ->add('imageFiles', FileType::class, [
                'label' => 'Images (optionnel)',
                'required' => false,
                'mapped' => false,
                'multiple' => true,
                'attr' => ['accept' => 'image/*'],
            ])
            ->add('typeEvenement', EntityType::class, [
                'label' => "Type d'evenement",
                'class' => TypeEvenement::class,
                'choice_label' => 'nom',
                'query_builder' => static function (TypeEvenementRepository $repo) use ($family) {
                    $qb = $repo->createQueryBuilder('t')
                        ->orderBy('t.nom', 'ASC');

                    if ($family === null) {
                        return $qb->andWhere('t.family IS NULL');
                    }

                    return $qb
                        ->andWhere('(t.family = :family OR t.family IS NULL)')
                        ->setParameter('family', $family);
                },
                'placeholder' => 'Choisir un type',
            ])
            ->add('shareWithFamily', CheckboxType::class, [
                'required' => false,
                'label' => 'Partager avec la famille',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
            'family' => null,
        ]);
        $resolver->setAllowedTypes('family', ['null', Family::class]);
    }
}
