<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints as Assert;

class ParentRefusalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('parentComment', TextareaType::class, [
            'label' => 'Pourquoi refusez-vous cette tâche ?',
            'required' => true,
            'constraints' => [
                new Assert\NotBlank([
                    'message' => 'Veuillez expliquer le refus',
                ]),
                new Assert\Length([
                    'min' => 5,
                    'minMessage' => 'Message trop court',
                ]),
            ],
        ]);
    }
}
