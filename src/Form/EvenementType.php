<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\TypeEvenement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', null, [
                'label' => 'Titre',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['placeholder' => 'Ex: Réunion famille'],
            ])
            ->add('description', null, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['placeholder' => 'Détails de l\'événement'],
            ])
            ->add('dateDebut', null, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('dateFin', null, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('lieu', null, [
                'label' => 'Lieu',
                'constraints' => [new Assert\NotBlank()],
                'attr' => ['placeholder' => 'Ex: Maison'],
            ])
            ->add('typeEvenement', EntityType::class, [
                'label' => 'Type d\'événement',
                'class' => TypeEvenement::class,
                'choice_label' => 'nom',
                'constraints' => [new Assert\NotNull()],
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
            'constraints' => [
                new Callback([$this, 'validateDates']),
            ],
        ]);
    }

    public function validateDates(Evenement $evenement, ExecutionContextInterface $context): void
    {
        $start = $evenement->getDateDebut();
        $end = $evenement->getDateFin();

        if ($start !== null && $end !== null && $end <= $start) {
            $context->buildViolation('La date de fin doit être après la date de début.')
                ->atPath('dateFin')
                ->addViolation();
        }
    }
}
