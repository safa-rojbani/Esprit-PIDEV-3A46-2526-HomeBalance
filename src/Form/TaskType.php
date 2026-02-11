<?php

namespace App\Form;

use App\Entity\Family;
use App\Entity\Task;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Enum\TaskDifficulty;
use App\Enum\TaskRecurrence;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;


class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('description')
            ->add('difficulty', ChoiceType::class, [
    'choices' => TaskDifficulty::cases(),
    'choice_label' => fn (TaskDifficulty $choice) => $choice->name,
    'placeholder' => 'Choisir une difficulté',
])

->add('recurrence', ChoiceType::class, [
    'choices' => TaskRecurrence::cases(),
    'choice_label' => fn (TaskRecurrence $choice) => $choice->name,
    'placeholder' => 'Choisir une récurrence',
]);

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
        ]);
    }
}
