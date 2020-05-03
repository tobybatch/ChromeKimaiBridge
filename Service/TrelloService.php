<?php

namespace KimaiPlugin\TrelloBundle\Service;

use App\Entity\Activity;
use App\Entity\ProjectMeta;
use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use App\Form\Type\DateTimePickerType;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\TrelloBundle\Exception\NoActivitiesException;
use KimaiPlugin\TrelloBundle\Exception\ProjectNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * Class TrelloService
 * @package KimaiPlugin\TrelloBundle\Service
 */
class TrelloService {
    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;
    /**
     * @var ActivityRepository
     */
    private ActivityRepository $activityRepository;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * @var EntityManager
     */
    private EntityManager $entityManager;

    /**
     * @param ContainerInterface $container
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     * @param ActivityRepository $activityRepository
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        ActivityRepository $activityRepository
    ) {
        $this->container = $container;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->activityRepository = $activityRepository;
    }

    /**
     * @param $projectId
     * @return Activity[]
     */
    public function getActivities($projectId) {
        $projectMeta = $this->entityManager->getRepository(ProjectMeta::class)->findOneByValue($projectId);

        if (!$projectMeta) {
            throw new ProjectNotFoundException("Could not find valid project meta data");
        }

        $activities = $this->activityRepository->findByProject($projectMeta->getEntity());
        if (empty($activities)) {
            throw new NoActivitiesException('Could not find valid project meta data');
        }

        return $this->activityRepository->findByProject($projectMeta->getEntity());
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $formbuilder
     * @param Activity[] $activities
     * @return \Symfony\Component\Form\FormInterface
     */
    public function buildLogForm(\Symfony\Component\Form\FormBuilderInterface $formbuilder, array $activities) {
        $choices = [];

        foreach ($activities as $activity) {
            $choices[$activity->getName()] = $activity->getId();
        }

        $buttonAttr = ['class' => 'btn-time'];

        return $formbuilder
            ->add('activity', ChoiceType::class, [ 'choices' => $choices ])
            ->add(
                'startDateTime',
                DateTimePickerType::class,
                [
                    'html5' => true,
                    'format' => 'yyyy-MM-dd',
                    'data' => new \DateTime(),
                    'widget' => 'single_text',
                ]
            )
            ->add('description', TextareaType::class, [ 'required' => false ])
            ->add('duration', NumberType::class)
            ->add('inc15', ButtonType::class, [
                'label' => '+', 'attr' => array_merge( $buttonAttr, [ 'data-time' => '15'])
            ])
            ->add('dec15', ButtonType::class, [
                'label' => '-', 'attr' => array_merge( $buttonAttr, [ 'data-time' => '-15'])
            ])
            ->add('inc60', ButtonType::class, [
                'label' => '+', 'attr' => array_merge( $buttonAttr, [ 'data-time' => '60'])
            ])
            ->add('dec60', ButtonType::class, [
                'label' => '-', 'attr' => array_merge( $buttonAttr, [ 'data-time' => '-60'])
            ])
            ->add('send', SubmitType::class)
            ->getForm();
    }

    public function processForm($form, $user, $cardId) {
        // data is an array with "name", "email", and "message" keys
        $data = $form->getData();
        dump($data);
        $activity = $this->activityRepository->find($data['activity']);
        $begin = $data['startDateTime'];
        $duration = \DateInterval::createFromDateString(round($data['duration']) . ' minutes');
        $end = clone $begin;
        $end->add($duration);
        $timesheet = new Timesheet();
        $timesheet->setActivity($activity);
        $timesheet->setBegin($begin);
        $timesheet->setEnd($end);
        $timesheet->setDescription($data['description']);
        $timesheet->setProject($activity->getProject());
        $timesheet->setUser($user);

        $cardIdMeta = (new TimesheetMeta())->setName('Trello Card ID')->setValue($cardId);
        $timesheet->setMetaField($cardIdMeta);

        $this->entityManager->persist($timesheet);
        $this->entityManager->flush();
    }
}