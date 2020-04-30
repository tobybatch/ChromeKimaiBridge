<?php
namespace KimaiPlugin\TrelloBundle\Controller;

use App\Controller\TimesheetAbstractController;
use App\Entity\ProjectMeta;
use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use App\Form\Type\DateTimePickerType;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;
use KimaiPlugin\TrelloBundle\Repository\TrelloRepository;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 *
 * @Route(path="/trello/")
 * @Security("is_granted('create_own_timesheet')")
 */
class TimesheetController extends TimesheetAbstractController
{
    /**
     * @param TrelloRepository $repository
     * @param TimesheetService $timesheetService
     */
    public function __construct(
        TimesheetRepository $repository,
        TimesheetService $timesheetService
    ) {
        $this->repository = $repository;
    }

    /**
     * @Route(path="logtime/{cardId}", name="trello_loggedtime", methods={"GET", "POST"})
     *
     * @param Request $request
     * @param ProjectRepository $projectRepository
     * @param ActivityRepository $activityRepository
     * @param $projectId
     * @param $cardId
     * @return \Symfony\Component\HttpFoundation\Response | \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function loggedTimeAction(
        Request $request,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository,
        $cardId)
    {
        // Find card by id
        $entityManager = $this->getDoctrine()->getManager();
        $timesheetMetas = $entityManager->getRepository(TimesheetMeta::class)->findByValue($cardId);

        $timesheets = [];
        foreach ($timesheetMetas as $timesheet) {
            $timesheets[] = $timesheet->getEntity();
        }

        return $this->render(
            '@Trello/loggedtime.html.twig',
            [
                'timesheets' => $timesheets,
                'cardId' => $cardId,
            ]
        );

    }

    /**
     * @Route(path="logtime/{projectId}/{cardId}", name="trello_logtime", methods={"GET", "POST"})
     *
     * @param Request $request
     * @param LoggerInterface $logger
     * @param ProjectRepository $projectRepository
     * @param ActivityRepository $activityRepository
     * @param $projectId
     * @param $cardId
     * @return \Symfony\Component\HttpFoundation\Response | \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function logtimeAction(
        Request $request,
        LoggerInterface $logger,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository,
        $projectId,
        $cardId)
    {
        // Find project by id
        $entityManager = $this->getDoctrine()->getManager();
        $projectMeta = $entityManager->getRepository(ProjectMeta::class)->findOneByValue($projectId);
        if (!$projectMeta) {
            $logger->error('Could not find valid project meta data');
            return new RedirectResponse(
                $this->container->get('router')->generate(
                    'trello_logtime_boardnotfound', ['projectId' => $projectId, 'cardId' => $cardId]
                )
            );
        }
        $activities = $activityRepository->findByProject($projectMeta->getEntity());
        if (empty($activities)) {
            $logger->error('Could not find valid project');
            return new RedirectResponse(
                $this->container->get('router')->generate(
                    'trello_logtime_boardnotfound', ['projectId' => $projectId, 'cardId' => $cardId]
                )
            );
        }
        $choices = [];

        foreach ($activities as $activity) {
            $choices[$activity->getName()] = $activity->getId();
        }

        $buttonAttr = ['class' => 'btn-time'];

        $form = $this->createFormBuilder(/* Add hidden data here */)
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

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // data is an array with "name", "email", and "message" keys
            $data = $form->getData();
            dump($data);
            $activity = $activityRepository->find($data['activity']);
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
            $timesheet->setUser($this->getUser());

            $cardIdMeta = (new TimesheetMeta())->setName('Trello Card ID')->setValue($cardId);
            $timesheet->setMetaField($cardIdMeta);

            $entityManager->persist($timesheet);
            $entityManager->flush();;

            $this->container->get('router');
            return new RedirectResponse(
                $this->container->get('router')->generate(
                    'trello_logtime_sucess', ['projectId' => $projectId, 'cardId' => $cardId]
                )
            );
        }
        return $this->render(
            '@Trello/logtime.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @Route(path="logtime/{projectId}/{cardId}/sucess", name="trello_logtime_sucess")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function logtimeSucessAction($projectId, $cardId) {
        return $this->render(
            '@Trello/logtime_sucess.html.twig', ['projectId' => $projectId, 'cardId' => $cardId]
        );
    }

    /**
     * @Route(path="logtime/{projectId}/{cardId}/boardnotfound", name="trello_logtime_boardnotfound")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function logtimeBoardNotFoundAction($projectId, $cardId) {
        return $this->render(
            '@Trello/logtime_boardnotfound.html.twig', ['projectId' => $projectId, 'cardId' => $cardId]
        );
    }
    /*
Time button decisions...
mysql> select count(*) as cnt, duration/60 from kimai2_timesheet group by duration order by cnt desc limit 20;
+-----+-------------+
| cnt | duration/60 |
+-----+-------------+
| 539 |     60.0000 |
| 444 |     30.0000 |
| 339 |    120.0000 |
| 190 |    180.0000 |
| 177 |    240.0000 |
| 165 |     90.0000 |
| 153 |     15.0000 |
| 127 |    300.0000 |
| 120 |    360.0000 |
|  85 |     45.0000 |



     */
}
