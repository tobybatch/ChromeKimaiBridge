<?php
declare(strict_types=1);

namespace KimaiPlugin\ChromePluginBundle\EventSubscriber;

use App\Entity\EntityWithMetaFields;
use App\Entity\MetaTableTypeInterface;
use App\Entity\TimesheetMeta;
use App\Event\TimesheetMetaDefinitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Length;

class TimesheetFieldSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TimesheetMetaDefinitionEvent::class => ['loadTimesheetMeta', 200],
        ];
    }

    public function loadTimesheetMeta(TimesheetMetaDefinitionEvent $event)
    {
        $this->prepareEntity($event->getEntity(), new TimesheetMeta());
    }

    private function prepareEntity(EntityWithMetaFields $entity, MetaTableTypeInterface $definition)
    {
        $definition
            ->setName('External Card/Project ID')
            ->setType(TextType::class)
            ->addConstraint(new Length(['max' => 255]))
            ->setIsVisible(true);

        $entity->setMetaField($definition);
    }
}