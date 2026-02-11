<?php

namespace App\Form;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\TaskAssignment;
use App\Enum\FamilyRole;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class TaskAssignmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $family = $options['family'];

        $builder
            // ✅ Tâches UNIQUEMENT de la famille
            ->add('task', EntityType::class, [
                'class' => Task::class,
                'choice_label' => 'title',
                'query_builder' => function (EntityRepository $er) use ($family) {
                    return $er->createQueryBuilder('t')
                        ->where('t.family = :family')
                        ->andWhere('t.isActive = true')
                        ->setParameter('family', $family);
                },
            ])

            // ✅ UNIQUEMENT les ENFANTS de la famille
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn (User $u) => $u->getFirstName().' '.$u->getLastName(),
                'query_builder' => function (EntityRepository $er) use ($family) {
                    return $er->createQueryBuilder('u')
                        ->where('u.family = :family')
                        ->andWhere('u.familyRole = :role')
                        ->setParameter('family', $family)
                        ->setParameter('role', FamilyRole::CHILD->value);
                },
            ])

            ->add('dueDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskAssignment::class,
            'family' => null, // 👈 obligatoire
        ]);
    }
}
