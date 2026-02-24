<?php

namespace App\Form\ModuleCharge;

use App\Entity\Revenu;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $types = array_values(array_filter(
            array_unique($options['types']),
            static fn ($type): bool => is_string($type) && trim($type) !== ''
        ));
        $choices = array_combine($types, $types) ?: [];
        if (count($types) > 0) {
            $choices['Autre (saisir ci-dessous)'] = '__custom__';
        }

        $builder
            ->add('typeRevenu', ChoiceType::class, [
                'choices' => $choices,
                'required' => false,
                'label' => 'Type de revenu',
                'placeholder' => count($types) > 0 ? 'Choisir un type' : 'Aucun type admin disponible',
                'attr' => [
                    'class' => 'form-select form-select-lg',
                ],
            ])
            ->add('typeRevenuLibre', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Nouveau type',
                'attr' => [
                    'placeholder' => 'Saisir un type si necessaire',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('montant', MoneyType::class, [
                'currency' => false,
                'required' => true,
                'html5' => true,
                'scale' => 2,
                'invalid_message' => 'Veuillez saisir un montant valide.',
            ])
            ->add('dateRevenu', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ]);

        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            $form = $event->getForm();
            $revenu = $event->getData();

            if (!$revenu instanceof Revenu) {
                return;
            }

            $selectedType = trim((string) $form->get('typeRevenu')->getData());
            $customType = trim((string) $form->get('typeRevenuLibre')->getData());
            if ($selectedType === '__custom__') {
                $selectedType = '';
            }

            $finalType = $customType !== '' ? $customType : $selectedType;
            $revenu->setTypeRevenu($finalType !== '' ? $finalType : null);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Revenu::class,
            'types' => [],
        ]);
    }
}
