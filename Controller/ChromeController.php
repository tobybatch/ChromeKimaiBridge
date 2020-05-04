<?php
namespace KimaiPlugin\TrelloBundle\Controller;

use App\Controller\TimesheetAbstractController;
use App\Entity\TimesheetMeta;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;
use KimaiPlugin\TrelloBundle\Repository\TrelloRepository;
use KimaiPlugin\TrelloBundle\Service\TrelloService;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
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
     * @var TrelloService
     */
    private TrelloService $trelloService;

    /**
     * @param TimesheetRepository $repository
     * @param TimesheetService $timesheetService
     */
    public function __construct(
        TimesheetRepository $repository,
        TimesheetService $timesheetService,
        TrelloService $trelloService
    ) {
        $this->repository = $repository;
        $this->timesheetService = $timesheetService;
        $this->trelloService = $trelloService;
    }

    /**
     * @Route(path="popup/{projectId}/{cardId}", name="chrome_popup", methods={"GET", "POST"})
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
}
