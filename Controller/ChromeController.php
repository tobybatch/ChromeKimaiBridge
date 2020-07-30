<?php
declare(strict_types=1);

namespace KimaiPlugin\ChromePluginBundle\Controller;

use App\Controller\TimesheetAbstractController;
use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\ProjectMeta;
use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use App\Export\ServiceExport;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;
use App\Timesheet\TrackingModeService;
use App\Timesheet\UserDateTimeFactory;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\ChromePluginBundle\EventSubscriber\ProjectFieldSubscriber;
use KimaiPlugin\ChromePluginBundle\Exception\NoActivitiesException;
use KimaiPlugin\ChromePluginBundle\Exception\ProjectNotFoundException;
use KimaiPlugin\ChromePluginBundle\Repository\ChromeSettingRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    private ActivityRepository $activityRepo;

    /**
     * ChromeController constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     * @param ActivityRepository $activityRepository
     * @param TimesheetRepository $repository
     * @param TimesheetService $timesheetService
     * @param UserDateTimeFactory $dateTime
     * @param TrackingModeService $trackingModeService
     * @param EventDispatcherInterface $dispatcher
     * @param ServiceExport $exportService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        ActivityRepository $activityRepository,
        TimesheetRepository $repository,
        TimesheetService $timesheetService,
        UserDateTimeFactory $dateTime,
        TrackingModeService $trackingModeService,
        EventDispatcherInterface $dispatcher,
        ServiceExport $exportService
    )
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->activityRepo = $activityRepository;
        $this->repository = $repository;
        $this->timesheetService = $timesheetService;
        $this->dateTime = $dateTime;
        $this->repository = $repository;
        $this->trackingModeService = $trackingModeService;
        $this->dispatcher = $dispatcher;
        $this->exportService = $exportService;
    }

    /**
     * @Route(path="status", name="chrome_status", methods={"GET"})
     */
    public function status()
    {
        return new JsonResponse(
            [
                'name' => "Kimai chrome plugin",
                'version' => "1.0.0",
            ]
        );
    }

    /**
     * @Route(path="uri", name="chrome_uri", methods={"GET", "POST"})
     *
     * Create the iframe for browser plugins..
     *
     * @param Request $request
     * @param LoggerInterface $logger
     * @param TimesheetRepository $timesheetRepository
     * @param ChromeSettingRepository $chromeSettingRepo
     * @return Response
     */
    public function popupWithUri(
        Request $request,
        LoggerInterface $logger,
        TimesheetRepository $timesheetRepository,
        ChromeSettingRepository $chromeSettingRepo)
    {
        // extract the board/card ids from the URI
        $uri_from_plugin = $request->query->get('uri');
        $logger->debug(sprintf("URI Received: %s", $uri_from_plugin));
        $hostname = parse_url($uri_from_plugin, PHP_URL_HOST);
        $all_settings = $chromeSettingRepo->findAll();
        if (!array_key_exists($hostname, $all_settings)) {
            throw $this->createNotFoundException('Host not configured in Kimai');
        }
        $settings = $all_settings[$hostname];
        if (empty($settings['regex2'])) {
            $matches = [];
            preg_match("/" . $settings['regex1'] . "/", $uri_from_plugin, $matches);
            $board_id = $matches[0] ?? false;
            $card_id = $matches[1] ?? false;
        } else {
            $matches = [];
            preg_match("/" . $settings['regex1'] . "/", $uri_from_plugin, $matches);
            $board_id = $matches[0] ?? false;
            $matches = [];
            preg_match("/" . $settings['regex2'] . "/", $uri_from_plugin, $matches);
            $card_id = $matches[0] ?? false;
        }
        // forward to the chrome_popup route.
        $logger->debug(sprintf("Returning board id=%s, card id=%s", $board_id, $card_id));
        return new JsonResponse(
            [
                'boardId' => $board_id,
                'cardId' => $card_id,
            ]
        );
    }

    /**
     * @Route(path="popup/{projectId}/{cardId}", name="chrome_popup", methods={"GET", "POST"})
     *
     * Create the iframe for browser plugins..
     *
     * @param Request $request
     * @param LoggerInterface $logger
     * @param TimesheetRepository $timesheetRepo
     * @param string $projectId
     * @param string|bool $cardId
     * @return Response
     */
    public function popupWithIds(
        Request $request,
        LoggerInterface $logger,
        TimesheetRepository $timesheetRepo,
        string $projectId,
        $cardId = false)
    {
        $logger->info("Project ID:" . $projectId);
        // Log time tab
        try {
            $activities = $this->getActivities($projectId);
        } catch (RuntimeException $exception) {
            $logger->error($exception->getMessage());
            return $this->redirectToRoute('chrome_project', [
                "projectId" => $projectId,
                "cardId" => $cardId ?? "false",
            ]);
        }

        $log_time_form = $this->buildLogForm(
            $this->createFormBuilder(/* Add hidden data here */),
            $activities
        );

        $log_time_form->handleRequest($request);

        $show_log = false;
        if ($log_time_form->isSubmitted() && $log_time_form->isValid()) {
            $this->processForm($log_time_form, $this->getUser(), $cardId);
            $this->container->get('router');
            $this->addFlash('success', 'Time logged!');
            $show_log = 'tabs-2';
        }

        $projects = $this->getProjectsById($projectId);

        // Logged time tab
        $timesheets = [];
        if ($cardId) {
            $timesheet_metas = $this->getDoctrine()->getManager()
                ->getRepository(TimesheetMeta::class)
                ->findByValue($cardId);
            $sheets = [];
            foreach ($timesheet_metas as $timesheet) {
                $sheets[] = $timesheet->getEntity();
            }
            usort($sheets, [$this, "compareSheetsByDate"]);
            $timesheets[$projects[0]->getName()] = $sheets;
        } else {
            foreach ($projects as $project) {
                $sheets = $timesheetRepo->findBy(["project" => $project]);
                usort($sheets, [$this, "compareSheetsByDate"]);
                $timesheets[$project->getName()] = $sheets;
            }
        }

        return $this->render(
            '@ChromePlugin/pages/pluggin.html.twig',
            [
                'form' => $log_time_form->createView(),
                'timesheets' => $timesheets,
                'boardId' => $projectId,
                'cardId' => $cardId,
                'show_log' => $show_log,
            ]
        );
    }

    /**
     * @param $projectId
     * @return array
     */
    private function getActivities($projectId)
    {
        $projects = $this->getProjectsById($projectId);
        if (count($projects) == 0) {
            throw new ProjectNotFoundException("Could not find valid project meta data");
        }

        $activities = [];
        foreach ($projects as $project) {
            $proj_activities = $this->activityRepo->findByProject($project);
            if (count($proj_activities)) {
                $activities[$project->getName()] = $proj_activities;
            }
        }

        $builder = $this->entityManager->getRepository(Activity::class)->createQueryBuilder('a');
        $query = $builder->where('a.project IS NULL')->getQuery();
        $global_act = $query->getResult();
        if (count($global_act)) {
            $activities["Global"] = $global_act;
        }

        if (empty($activities)) {
            throw new NoActivitiesException('Could not find valid activity meta data');
        }

        return $activities;
    }

    /**
     * @param $projectId
     * @return array
     */
    private function getProjectsById($projectId)
    {
        $builder = $this->entityManager->getRepository(ProjectMeta::class)->createQueryBuilder('p');
        $query = $builder->where($builder->expr()->like('p.value', ':projectid'))
            ->setParameter('projectid', '%' . $projectId . '%')
            ->getQuery();
        $project_metas = $query->getResult();
        if (!count($project_metas)) {
            return [];
        } else {
            $projects = [];
            foreach ($project_metas as $project_meta) {
                $projects[] = $project_meta->getEntity();
            }
        }
        return $projects;
    }

    /**
     * @param FormBuilderInterface $form_builder
     * @param Activity[] $activities
     * @return FormInterface
     */
    private function buildLogForm(FormBuilderInterface $form_builder, array $activities)
    {
        $choices = [];

        foreach ($activities as $project => $proj_activities) {
            $sub_choices = [];
            foreach ($proj_activities as $activity) {
                $sub_choices[$activity->getName()] = $activity->getId();
            }
            $choices[$project] = $sub_choices;
        }

        $button_attr = ['class' => 'btn-time'];

        return $form_builder
            ->add('activity', ChoiceType::class, ['choices' => $choices])
            ->add(
                'startDateTime',
                DateType::class,
                [
                    'html5' => true,
                    'format' => 'yyyy-MM-dd',
                    'data' => new DateTime(),
                    'widget' => "single_text",
                ]
            )
            ->add('description', TextareaType::class, ['required' => false])
            ->add('duration', NumberType::class)
            ->add('inc15', ButtonType::class, [
                'label' => '+', 'attr' => array_merge($button_attr, ['data-time' => '15'])
            ])
            ->add('dec15', ButtonType::class, [
                'label' => '-', 'attr' => array_merge($button_attr, ['data-time' => '-15'])
            ])
            ->add('inc60', ButtonType::class, [
                'label' => '+', 'attr' => array_merge($button_attr, ['data-time' => '60'])
            ])
            ->add('dec60', ButtonType::class, [
                'label' => '-', 'attr' => array_merge($button_attr, ['data-time' => '-60'])
            ])
            ->add('send', SubmitType::class)
            ->getForm();
    }

    /**
     * @param $form
     * @param $user
     * @param $cardId
     */
    private function processForm($form, $user, $cardId)
    {
        // data is an array with "name", "email", and "message" keys
        $form_data = $form->getData();
        $activity = $this->activityRepo->find($form_data['activity']);
        $start_time = $form_data['startDateTime'];
        $duration = DateInterval::createFromDateString(round($form_data['duration']) . ' minutes');
        $end_time = clone $start_time;
        $end_time->add($duration);
        $timesheet = new Timesheet();
        $timesheet->setActivity($activity);
        $timesheet->setBegin($start_time);
        $timesheet->setEnd($end_time);
        $timesheet->setDescription($form_data['description']);
        $timesheet->setProject($activity->getProject());
        $timesheet->setUser($user);

        if ($cardId) {
            $card_id_meta = (new TimesheetMeta())->setName('ChromePlugin Card ID')->setValue($cardId);
            $timesheet->setMetaField($card_id_meta);
        }

        $this->entityManager->persist($timesheet);
        $this->entityManager->flush();
    }

    /**
     * @Route(path="settings", name="chrome_settings", methods={"GET", "POST"})
     *
     * @param ChromeSettingRepository $chromeSettingRepository
     * @param Request $request
     * @return Response
     */
    public function settings(ChromeSettingRepository $chromeSettingRepository, Request $request)
    {
        $form_builder = $this->createFormBuilder();
        $forms = [];

        // Process a delete, if present
        $hostname = $request->query->get('hostname');
        if ($hostname) {
            $chromeSettingRepository->remove($hostname);
        }

        // Create the empty add form
        $empty_form = $form_builder
            ->add('hostname', TextType::class)
            ->add('regex1', TextType::class)
            ->add('regex2', TextType::class, [
                    'required' => false]
            )
            ->add('send', SubmitType::class, [
                'label' => "Add"
            ])
            ->getForm();

        $empty_form->handleRequest($request);
        if ($empty_form->isSubmitted() && $empty_form->isValid()) {
            $settings = $this->processEditForm($empty_form);
            $hostname = array_key_first($settings);
            $chromeSettingRepository->save($hostname, $settings[$hostname]);
            // By redirecting post save we get a clear add form
            return $this->redirect($request->getUri());
        }
        //Don't add the form view yet, we'll add it last so it's at the end of the list.

        //Load the settings
        $settings = $chromeSettingRepository->findAll();
        if (count($settings) == 0) {
            // If it's empty add github
            $settings = [
                "github.com" => [
                    "regex1" => "(?<=^https:\/\/github.com\/)(\w+\/\w+)",
                    "regex2" => "\d+$",
                ]
            ];
            $chromeSettingRepository->saveAll($settings);
        }

        // Create edit forms for the existing settings
        foreach ($settings as $hostname => $setting) {
            $forms[] = $form_builder
                ->add('hostname', TextType::class, [
                    'data' => $hostname,
                ])
                ->add('regex1', TextType::class, [
                    'data' => $setting['regex1'],
                ])
                ->add('regex2', TextType::class, [
                        'data' => $setting['regex2'] ?? "",
                        'required' => false]
                )
                ->add('send', SubmitType::class, [
                    'label' => "Update"
                ])
                ->getForm();
        }


        $form_views = [];
        foreach ($forms as $form) {
            $form_views[] = $form->createView();
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $settings = $settings + $this->processEditForm($form);
            }
        }
        $chromeSettingRepository->saveAll($settings);

        // Add the empty (add) form at the end
        $form_views[] = $empty_form->createView();

        return $this->render(
            '@ChromePlugin/admin/settings.html.twig',
            [
                'forms' => $form_views,
            ]
        );
    }

    private function processEditForm($form)
    {
        $form_data = $form->getData();
        $hostname = $form_data['hostname'];
        $regex1 = $form_data['regex1'];
        $regex2 = $form_data['regex2'];
        return [
            $hostname => [
                'regex1' => $regex1,
                'regex2' => $regex2,

            ]
        ];
    }

    /**
     * @Route(path="project/{projectId}/{cardId}", name="chrome_project", methods={"GET", "POST"})
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param ProjectRepository $projectRepo
     * @param string $projectId
     * @param string $cardId
     * @return Response
     */
    public function setProjectAssociation(
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectRepository $projectRepo,
        string $projectId,
        string $cardId = "")
    {
        if (empty($cardId)) {
            $cardId = false;
        }
        $all_projects = $projectRepo->findAll();
        $projects = [];
        foreach ($all_projects as $project) {
            $customer = $project->getCustomer()->getName();
            if (array_key_exists($customer, $projects)) {
                $projects[$project->getCustomer()->getName()] += [$project->getName() => $project->getId()];
            } else {
                $projects[$project->getCustomer()->getName()] = [$project->getName() => $project->getId()];
            }
        }
        $project_form = $this->createFormBuilder()
            ->add('projects', ChoiceType::class, ['choices' => $projects])
            ->add('save', SubmitType::class, ['label' => "Save"])
            ->getForm();

        $project_form->handleRequest($request);
        if ($project_form->isSubmitted() && $project_form->isValid()) {
            $data = $project_form->getData();
            /**
             * @var Project
             */
            $project_to_update = $entityManager
                ->getRepository(Project::class)
                ->findOneById($data["projects"]);
            $existing_id_meta = $project_to_update->getMetaField(ProjectFieldSubscriber::NAME);
            if (!$existing_id_meta) {
                $existing_id_meta = (new ProjectMeta())->setName(ProjectFieldSubscriber::NAME);
                $existing_id_list = [];
            } else {
                $existing_id_list = $existing_id_meta ? explode(",", $existing_id_meta->getValue()) : [];
            }
            $existing_id_list[] = $projectId;

            $existing_id_meta->setValue(implode(",",$existing_id_list));
            $project_to_update->setMetaField($existing_id_meta);
            $entityManager->persist($project_to_update);
            $entityManager->flush();
            return $this->redirectToRoute('chrome_popup', ["projectId" => $projectId, "cardId" => $cardId]);
        }

        return $this->render(
            '@ChromePlugin/admin/project.html.twig',
            [
                'projectId' => $projectId,
                'cardId' => $cardId,
                'projects' => $project_form->createView(),
            ]
        );
    }

    /**
     * @param Timesheet $sheet1
     * @param Timesheet $sheet2
     * @return bool
     * @noinspection PhpMethodNamingConventionInspection
     */
    protected function compareSheetsByDate(Timesheet $sheet1, Timesheet $sheet2): bool
    {
        return $sheet1->getBegin() > $sheet2->getBegin();
    }
}
