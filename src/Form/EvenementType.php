<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Family;
use App\Entity\TypeEvenement;
use App\Repository\TypeEvenementRepository;
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
        /** @var Family|null $family */
        $family = $options['family'];

        $builder
            ->add('titre', null, [
                'label' => 'Titre',
                'constraints' => [new Assert\NotBlank(message: 'Le titre est obligatoire.')],
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
                'constraints' => [new Assert\NotBlank(message: 'La date de debut est obligatoire.')],
            ])
            ->add('dateFin', null, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank(message: 'La date de fin est obligatoire.')],
            ])
            ->add('lieu', null, [
                'label' => 'Lieu',
                'constraints' => [new Assert\NotBlank(message: 'Le lieu est obligatoire.')],
                'attr' => ['placeholder' => 'Ex: Maison'],
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
                'constraints' => [new Assert\NotNull(message: "Le type d'evenement est obligatoire.")],
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
            'constraints' => [
                new Callback([$this, 'validateDates']),
            ],
        ]);
        $resolver->setAllowedTypes('family', ['null', Family::class]);
    }

    public function validateDates(Evenement $evenement, ExecutionContextInterface $context): void
    {
        $start = $evenement->getDateDebut();
        $end = $evenement->getDateFin();

        if ($start !== null && $end !== null && $end <= $start) {
            $context->buildViolation('La date de fin doit etre apres la date de debut.')
                ->atPath('dateFin')
                ->addViolation();
        }
    }
}

