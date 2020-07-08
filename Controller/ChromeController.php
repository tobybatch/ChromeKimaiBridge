<?php
namespace KimaiPlugin\ChromePluginBundle\Controller;

use App\Controller\TimesheetAbstractController;
use App\Entity\Activity;
use App\Entity\ProjectMeta;
use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use App\Form\Type\DateTimePickerType;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\ChromePluginBundle\Exception\NoActivitiesException;
use KimaiPlugin\ChromePluginBundle\Exception\ProjectNotFoundException;
use KimaiPlugin\ChromePluginBundle\Service\ChromePluginService;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 *
 * @Route(path="/chrome/")
 * @Security("is_granted('create_own_timesheet')")
 */
class ChromeController extends TimesheetAbstractController
{
    /**
     * @var TimesheetService
     */
    private TimesheetService $timesheetService;
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * @var ActivityRepository
     */
    private ActivityRepository $activityRepository;

    /**
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     * @param ActivityRepository $activityRepository
     * @param TimesheetRepository $repository
     * @param TimesheetService $timesheetService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        ActivityRepository $activityRepository,
        TimesheetRepository $repository,
        TimesheetService $timesheetService
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->activityRepository = $activityRepository;
        $this->repository = $repository;
        $this->timesheetService = $timesheetService;
    }

    /**
     * @Route(path="popup/{projectId}/{cardId}", name="chrome_popup", methods={"GET", "POST"})
     *
     * Create the iframe for browser plugins..
     *
     * @param Request $request
     * @param LoggerInterface $logger
     * @param TimesheetRepository $timesheetRepository
     * @param ProjectRepository $projectRepository
     * @param $projectId
     * @param $cardId
     * @return Response
     */
    public function pluginAction(
        Request $request,
        LoggerInterface $logger,
        TimesheetRepository $timesheetRepository,
        ProjectRepository $projectRepository,
        $projectId,
        $cardId = false)
    {
        $logger->info("Project ID:" . $projectId);
        // Log time tab
        try {
            $activities = $this->getActivities($projectId);
        } catch (RuntimeException $exception) {
            $logger->error($exception->getMessage());
            return $this->render(
                '@ChromePlugin/logtime_boardnotfound.html.twig',
                ['projectId' => $projectId, 'cardId' => $cardId, 'message' => $exception->getMessage()]
            );
        }

        $logTimeForm = $this->buildLogForm(
            $this->createFormBuilder(/* Add hidden data here */),
            $activities
        );

        $logTimeForm->handleRequest($request);

        $show = false;
        if ($logTimeForm->isSubmitted() && $logTimeForm->isValid()) {
            $this->processForm($logTimeForm, $this->getUser(), $cardId);
            $this->container->get('router');
            $this->addFlash('success', 'Time logged!');
            $show = 'tabs-2';
        }

        $projects = $this->getProjectsById($projectId);

        // Logged time tab
        $timesheets = [];
        if ($cardId) {
            $timesheetMetas = $this->getDoctrine()->getManager()
                ->getRepository(TimesheetMeta::class)
                ->findByValue($cardId);
            foreach ($timesheetMetas as $timesheet) {
                $timesheets[$projects[0]->getName()] = $timesheet->getEntity();
            }
        } else {
            foreach ($projects as $project) {
                $timesheets[$project->getName()] = $timesheetRepository->findBy(["project" => $project]);
            }
        }

        return $this->render(
            '@ChromePlugin/pluggin.html.twig',
            [
                'form' => $logTimeForm->createView(),
                'timesheets' => $timesheets,
                'boardId' => $projectId,
                'cardId' => $cardId,
                'show' => $show,
            ]
        );
    }

    private function getProjectsById($projectId) {
        $builder = $this->entityManager->getRepository(ProjectMeta::class)->createQueryBuilder('p');
        $query = $builder->where($builder->expr()->like('p.value', ':projectid'))
            ->setParameter('projectid', '%' . $projectId . '%')
            ->getQuery();
        $projectMetas = $query->getResult();
        if (!count($projectMetas)) {
            return [];
        }
        else {
            $projects = [];
            foreach ($projectMetas as $projectMeta) {
                $projects[] = $projectMeta->getEntity();
            }
        }
        return $projects;
    }

    /**
     * @param $projectId
     * @return array
     */
    private function getActivities($projectId) {
        $projects = $this->getProjectsById($projectId);
        if (count($projects) == 0) {
            throw new ProjectNotFoundException("Could not find valid project meta data");
        }

        $activities = [];
        foreach ($projects as $project) {
            $projectActivities = $this->activityRepository->findByProject($project);
            if (count($projectActivities)) {
                $activities[$project->getName()] = $projectActivities;
            }
        }

        $builder = $this->entityManager->getRepository(Activity::class)->createQueryBuilder('a');
        $query = $builder->where('a.project IS NULL')->getQuery();
        $globalActivities = $query->getResult();
        if (count($globalActivities)) {
            $activities["__GLOBAL"] = $globalActivities;
        };

        if (empty($activities)) {
            throw new NoActivitiesException('Could not find valid activity meta data');
        }

        return $activities;
    }

    /**
     * @param FormBuilderInterface $formbuilder
     * @param Activity[] $activities
     * @return FormInterface
     */
    private function buildLogForm(FormBuilderInterface $formbuilder, array $activities) {
        $choices = [];

        foreach ($activities as $project => $_activities) {
            $subChoices = [];
            foreach ($_activities as $activity) {
                $subChoices[$activity->getName()] = $activity->getId();
            }
            $choices[$project] = $subChoices;
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

    private function processForm($form, $user, $cardId) {
        // data is an array with "name", "email", and "message" keys
        $data = $form->getData();
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

        if ($cardId) {
            $cardIdMeta = (new TimesheetMeta())->setName('ChromePlugin Card ID')->setValue($cardId);
            $timesheet->setMetaField($cardIdMeta);
        }

        $this->entityManager->persist($timesheet);
        $this->entityManager->flush();
    }
}
