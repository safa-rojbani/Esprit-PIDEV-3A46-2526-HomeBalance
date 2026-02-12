<?php

namespace App\Form;

use App\Entity\Family;
use App\Entity\Task;
use App\Entity\TaskAssignment;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskAssignmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Family $family */
        $family = $options['family'];

        $builder
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
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn (User $u) => trim(($u->getFirstName() ?? '').' '.($u->getLastName() ?? '')),
                'query_builder' => function (EntityRepository $er) use ($family) {
                    return $er->createQueryBuilder('u')
                        ->where('u.id IN (
                            SELECT IDENTITY(m.user)
                            FROM App\Entity\FamilyMembership m
                            WHERE m.family = :family AND m.leftAt IS NULL
                        )')
                        ->setParameter('family', $family)
                        ->orderBy('u.FirstName', 'ASC')
                        ->addOrderBy('u.LastName', 'ASC');
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
            'family' => null,
        ]);
        $resolver->setRequired('family');
        $resolver->setAllowedTypes('family', Family::class);
    }
}
