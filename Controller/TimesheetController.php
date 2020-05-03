<?php
namespace KimaiPlugin\TrelloBundle\Controller;

use App\Controller\TimesheetAbstractController;
use App\Entity\TimesheetMeta;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use KimaiPlugin\TrelloBundle\Repository\TrelloRepository;
use KimaiPlugin\TrelloBundle\Service\TrelloService;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
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
     * @var TrelloService
     */
    private TrelloService $trelloService;

    /**
     * @param TrelloRepository $repository
     * @param TrelloService $trelloService
     */
    public function __construct(
        TrelloRepository $repository,
        TrelloService $trelloService
    ) {
        $this->repository = $repository;
        $this->trelloService = $trelloService;
    }

    /**
     * @Route(path="logtime/{cardId}", name="trello_loggedtime", methods={"GET", "POST"})
     *
     * This route handled the card-back-section callback from the Trello power up.  The trello power up has been parked
     * for now as it couldn't easily be configured for different Kimai instances.  The plugin route can easily handle
     * different kimai.  This will be factored out unless I can figure out how to set up the Trello power up better.
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
            '@Trello/card-back-section.html.twig',
            [
                'timesheets' => $timesheets,
                'cardId' => $cardId,
            ]
        );

    }

    /**
     * @Route(path="logtime/{projectId}/{cardId}", name="trello_logtime", methods={"GET", "POST"})
     *
     * This route handled the card-buttons callback from the Trello power up.  See the doc comment above.
     *
     * @param Request $request
     * @param LoggerInterface $logger
     * @param ProjectRepository $projectRepository
     * @param ActivityRepository $activityRepository
     * @param $projectId
     * @param $cardId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function logtimeAction(
        Request $request,
        LoggerInterface $logger,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository,
        $projectId,
        $cardId)
    {
        try {
            $activities = $this->trelloService->getActivities($projectId);
        } catch (\RuntimeException $e) {
            $logger->error($e->getMessage());
            return $this->render(
                '@Trello/logtime_boardnotfound.html.twig',
                ['projectId' => $projectId, 'cardId' => $cardId, 'message' => $e->getMessage()]
            );
        }

        $form = $this->trelloService->buildLogForm(
            $this->createFormBuilder(/* Add hidden data here */),
            $activities
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->trelloService->processForm($form, $this->getUser(), $cardId);
            $this->container->get('router');
            return $this->render(
                '@Trello/logtime_sucess.html.twig', ['projectId' => $projectId, 'cardId' => $cardId]
            );
        }

        return $this->render(
            'card-buttons.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }


    /**
     * @Route(path="plugin/{projectId}/{cardId}", name="trello_plugin", methods={"GET", "POST"})
     *
     * Create the iframe for browser plugins..
     *
     * @param Request $request
     * @param LoggerInterface $logger
     * @param ProjectRepository $projectRepository
     * @param ActivityRepository $activityRepository
     * @param $projectId
     * @param $cardId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function pluginAction(
        Request $request,
        LoggerInterface $logger,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository,
        $projectId,
        $cardId)
    {
        // Log time tab
        try {
            $activities = $this->trelloService->getActivities($projectId);
        } catch (\RuntimeException $e) {
            $logger->error($e->getMessage());
            return $this->render(
                '@Trello/logtime_boardnotfound.html.twig',
                ['projectId' => $projectId, 'cardId' => $cardId, 'message' => $e->getMessage()]
            );
        }

        $form = $this->trelloService->buildLogForm(
            $this->createFormBuilder(/* Add hidden data here */),
            $activities
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->trelloService->processForm($form, $this->getUser(), $cardId);
            $this->container->get('router');
            return $this->render(
                '@Trello/logtime_sucess.html.twig', ['projectId' => $projectId, 'cardId' => $cardId]
            );
        }

        // Logged time tab
        $entityManager = $this->getDoctrine()->getManager();
        $timesheetMetas = $entityManager->getRepository(TimesheetMeta::class)->findByValue($cardId);

        $timesheets = [];
        foreach ($timesheetMetas as $timesheet) {
            $timesheets[] = $timesheet->getEntity();
        }


        return $this->render(
            '@Trello/pluggin.html.twig',
            [
                'form' => $form->createView(),
                'timesheets' => $timesheets,
                'boardId' => $projectId,
                'cardId' => $cardId,
            ]
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
