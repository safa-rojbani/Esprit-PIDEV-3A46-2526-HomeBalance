<?php

namespace App\Form\ModuleDocuments\FrontOffice;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'Parcourir',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'attr' => [
                'novalidate' => 'novalidate',
            ],
            'validation_groups' => static function (FormInterface $form): array {
                /** @var Document|null $document */
                $document = $form->getData();
                if ($document instanceof Document && $document->getId() !== null) {
                    return ['Default'];
                }

                return ['Default', 'document_create'];
            },
        ]);
    }
}
