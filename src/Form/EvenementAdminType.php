<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Family;
use App\Entity\TypeEvenement;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class EvenementAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', null, [
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('description')
            ->add('dateDebut', null, [
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('dateFin', null, [
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('lieu', null, [
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('typeEvenement', EntityType::class, [
                'class' => TypeEvenement::class,
                'choice_label' => 'nom',
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('shareWithFamily', CheckboxType::class, [
                'required' => false,
                'label' => 'Share with family',
            ])
            ->add('family', EntityType::class, [
                'class' => Family::class,
                'choice_label' => 'id',
                'required' => false,
                'placeholder' => 'None',
            ])
            ->add('createdBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'required' => false,
                'placeholder' => 'None',
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
            $context->buildViolation('End date must be after start date.')
                ->atPath('dateFin')
                ->addViolation();
        }
    }
}
