<?php declare(strict_types=1);

namespace DOMJudgeBundle\Form\Type;

use DOMJudgeBundle\Entity\Contest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FinalizeContestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('b', IntegerType::class);
        $builder->add('finalizecomment', TextareaType::class, [
            'label' => 'Comment',
            'required' => false,
        ]);
        $builder->add('finalize', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => Contest::class]);
    }
}
