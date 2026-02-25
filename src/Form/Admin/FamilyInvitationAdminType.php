<?php

namespace App\Form\Admin;

use App\Entity\Family;
use App\Entity\FamilyInvitation;
use App\Entity\User;
use App\Enum\InvitationStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class FamilyInvitationAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('invitedEmail', TextType::class, [
                'required' => false,
                'constraints' => [
                    new EmailConstraint(message: 'Please provide a valid email address.'),
                    new Length(max: 255),
                ],
            ])
            ->add('joinCode', TextType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank(message: 'Join code is required.'),
                    new Length(max: 255),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'required' => false,
                'choices' => $this->enumChoices(InvitationStatus::cases()),
                'choice_label' => fn (InvitationStatus $status) => ucfirst(strtolower($status->name)),
                'choice_value' => static fn (?InvitationStatus $status) => $status?->value,
                'constraints' => [new NotBlank(message: 'Status is required.')],
            ])
            ->add('expiresAt', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => false,
            ])
            ->add('family', EntityType::class, [
                'required' => false,
                'class' => Family::class,
                'choice_label' => fn (Family $family) => sprintf('%s (#%d)', $family->getName(), $family->getId()),
                'constraints' => [new NotBlank(message: 'Family is required.')],
            ])
            ->add('createdBy', EntityType::class, [
                'required' => false,
                'class' => User::class,
                'choice_label' => fn (User $user) => $user->getUserIdentifier(),
                'constraints' => [new NotBlank(message: 'Creator is required.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FamilyInvitation::class,
            'csrf_protection' => true,
        ]);
    }

    /**
     * @param array<int, \UnitEnum> $cases
     * @return array<string, \UnitEnum>
     */
    private function enumChoices(array $cases): array
    {
        $choices = [];
        foreach ($cases as $case) {
            $choices[$case->value] = $case;
        }

        return $choices;
    }
}
