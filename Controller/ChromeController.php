<?php

namespace KimaiPlugin\ChromePluginBundle\Controller;

use App\Entity\Project;
use App\Entity\ProjectMeta;
use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use App\Repository\ActivityRepository;
use App\Repository\UserRepository;
use DateInterval;
use DateTime;
use Exception;
use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use KimaiPlugin\ChromePluginBundle\EventSubscriber\ProjectFieldSubscriber;
use KimaiPlugin\ChromePluginBundle\EventSubscriber\TimesheetFieldSubscriber;
use KimaiPlugin\ChromePluginBundle\Exception\ProjectNotFoundException;
use KimaiPlugin\ChromePluginBundle\Repository\SettingRepo;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 *
 * @ Security("is_granted('create_own_timesheet')")
 * @Route(path="/api/chrome/json")
 */
class ChromeController extends BaseController
{
    /**
     * @Route(path="/init", name="chrome_init", methods={"GET"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getInit(Request $request): JsonResponse
    {
        $uri = $request->get("uri");
        if (empty($uri)) {
            throw new RuntimeException("No URI specified.");
        }
        try {
            $data = $this->getInitData($uri);
            return $this->makeJsonResponse($data);
        } catch (ProjectNotFoundException $projectNotFoundException) {
            return $this->makeJsonResponse($this->settingsEntityToArray($projectNotFoundException->getSettingEntity()), 422);
        }
    }

    /**
     * @Route(path="/registeredHosts", name="chrome_options", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getRegisteredHosts(): JsonResponse
    {
        $settingsEntities = $this->settingRepo->findAll();
        return $this->makeJsonResponse(
            array_map(
                function (SettingEntity $settingsEntity) {
                    return $this->settingsEntityToArray($settingsEntity);
                },
                $settingsEntities
            )
        );
    }

    /**
     * @Route(path="/project/{project}", name="chrome_set_project", methods={"POST"})
     *
     * @param Request $request
     * @param Project $project
     * @return JsonResponse
     */
    public function setProject(Request $request, Project $project): JsonResponse
    {
        $projectFromPlugin = $request->get("projectId");

        $meta = new ProjectMeta();
        $meta->setName(ProjectFieldSubscriber::PROJECT_ID);
        $meta->setValue($projectFromPlugin);
        $project->setMetaField($meta);

        $this->logger->debug("Setting project assoc", ["projectId" => $project->getId(), "projectFromPlugin" => $projectFromPlugin]);

        // TODO Do I need both persists?
        $this->entityManager->persist($meta);
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $this->makeJsonResponse($this->projectToArray($project));
    }

    /**
     * @Route(path="/project/{hostname}", name="chrome_delete_project", methods={"DELETE"})
     *
     * @param SettingRepo $settingRepo
     * @param string $hostname
     * @return JsonResponse
     */
    public function deleteProject(SettingRepo $settingRepo, string $hostname): JsonResponse
    {
        $settingRepo->removeByHost($hostname);
        return $this->makeJsonResponse([]);
    }

    /**
     * @Route(path="/project", name="chrome_savehost", methods={"POST"})
     *
     * @param Request $request
     * @param SettingRepo $settingRepo
     * @return JsonResponse
     * @throws Exception
     */
    public function postSaveHostSetting(Request $request, SettingRepo $settingRepo): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $this->logger->debug("Save host", ['payload' => $payload]);

        $settingEntity = new SettingEntity();

        $settingEntity->setHostname($payload['hostname']);
        $settingEntity->setProjectRegex($payload['projectRegex']);
        $settingEntity->setIssueRegex($payload['issueRegex']);

        $settingRepo->save($settingEntity);

        if ($payload['oldHostName'] != $payload['hostname']) {
            $settingRepo->removeByHost($payload['oldHostName']);
        }

        return $this->makeJsonResponse($this->settingsEntityToArray($settingEntity));
    }

    /**
     * @Route(path="/logtime", name="chrome_logtime", methods={"POST"})
     *
     * @param Request $request
     * @param UserRepository $userRepository
     * @param ActivityRepository $activityRepository
     * @return JsonResponse
     * @throws Exception
     */
    public function postLogTime(
        Request $request,
        UserRepository $userRepository,
        ActivityRepository $activityRepository
    ): JsonResponse {
        $issueId = $request->get('issueId');
        $payload = json_decode($request->getContent(), true);
        $this->logger->debug("postLogTime", ["issueId" => $issueId, "payload" => $payload]);

        $activityId = $payload['activity'];
        $duration = $payload['duration'];
        $date = $payload['date'];
        $description = $payload['description'];
        $link = $payload['link'];

        $currentUser = $this->getUser();
        if (!$currentUser) {
            // During dev there is no user so if we are in dev mode log time as user 1
            if ($this->kernel->getEnvironment() != "dev") {
                throw new HttpException(sprintf("No such user: '%s'", $currentUser));
            }
            $users = $userRepository->findAll();
            $user = $users[0];
        } else {
            $user = $userRepository->findOneBy(['username' => $currentUser->getUsername()]);
        }

        $activity = $activityRepository->find($activityId);
        $begin = new DateTime($date);
        $duration = DateInterval::createFromDateString(round($duration) . ' minutes');
        $end = clone $begin;
        $end->add($duration);
        $timesheet = new Timesheet();
        $timesheet->setActivity($activity);
        $timesheet->setBegin($begin);
        $timesheet->setEnd($end);
        $timesheet->setDescription($description);
        $timesheet->setProject($activity->getProject());
        $timesheet->setUser($user);

        $issueIdMeta = (new TimesheetMeta())->setName(TimesheetFieldSubscriber::ISSUE_ID)->setValue($issueId);
        $timesheet->setMetaField($issueIdMeta);
        $issueLinkMeta = (new TimesheetMeta())->setName(TimesheetFieldSubscriber::ISSUE_LINK)->setValue($link);
        $timesheet->setMetaField($issueLinkMeta);

        $this->entityManager->persist($timesheet);
        $this->entityManager->flush();

        return $this->makeJsonResponse(['timesheetId' => $timesheet->getId()]);
    }

    /**
     * @Route(path="/activities", name="chrome_activities", methods={"GET"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getActivities(Request $request): JsonResponse
    {
        return $this->makeJsonResponse($this->activities($request->get("boardId")));
    }

    /**
     * @Route(path="/projects", name="chrome_projects", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getProjects(): JsonResponse
    {
        // Fetch all projects
        $projectEntities = $this->entityManager
            ->getRepository(Project::class)
            ->findAll();
        $projects = [];
        foreach ($projectEntities as $entity) {
            if ($entity->isVisible()) {
                $projects[] = $this->projectToArray($entity);
            }
        }
        return $this->makeJsonResponse($projects);
    }

    /**
     * @Route(path="/history/{issueId}", name="chrome_history", methods={"GET"})
     *
     * @param string $issueId
     * @return JsonResponse
     */
    public function getHistory(string $issueId): JsonResponse
    {
        $history = $this->history($issueId);
        // TODO inline this when it's working
        $data = array_map(
            function (Timesheet $timesheet) {
                return [
                    'id' => $timesheet->getId(),
                    'duration' => $timesheet->getDuration(),
                    'user' => $timesheet->getUser()->getUsername(),
                    'activity' => $timesheet->getActivity()->getName(),
                    'description' => $timesheet->getDescription(),
                    'date' => $timesheet->getBegin()->format("d/m/Y"),
                ];
            },
            $history
        );
        return $this->makeJsonResponse($data);
    }
}