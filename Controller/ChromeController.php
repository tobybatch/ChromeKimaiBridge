<?php
namespace KimaiPlugin\TrelloBundle\Controller;

use App\Controller\TimesheetAbstractController;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimesheetRepository;
use App\Timesheet\TimesheetService;
use KimaiPlugin\TrelloBundle\Repository\TrelloRepository;
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
     * @Route(path="popup/{boardId}/{cardId}", name="chrome_popup", methods={"GET"})
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


        return $this->render(
            '@Trello/popup.html.twig',
            [
                'timesheets' => $timesheets,
                'cardId' => $cardId,
            ]
        );

    }

}
