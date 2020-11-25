<?php

namespace KimaiPlugin\ChromePluginBundle\Controller;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\ProjectMeta;
use App\Entity\TimesheetMeta;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use KimaiPlugin\ChromePluginBundle\EventSubscriber\TimesheetFieldSubscriber;
use KimaiPlugin\ChromePluginBundle\Exception\ProjectNotFoundException;
use KimaiPlugin\ChromePluginBundle\Repository\SettingRepo;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;

class BaseController extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;
    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    /**
     * @var SettingRepo
     */
    protected SettingRepo $settingRepo;
    /**
     * @var KernelInterface
     */
    protected KernelInterface $kernel;

    public function __construct(
        EntityManagerInterface $entityManager,
        SettingRepo $settingsRepo,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->settingRepo = $settingsRepo;
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    protected function roles(): array
    {
        $roles = [];
        $user = $this->getUser();
        if ($user) {
            $roles = $this->getUser()->getRoles();
        }

        if ($this->getParameter('kernel.environment') === "dev") {
            $roles[] = "ROLE_ADMIN";
        }

        return $roles;
    }

    /**
     * @param SettingEntity $settingsEntity
     * @return array
     */
    protected function settingsEntityToArray(SettingEntity $settingsEntity): array
    {
        return [
            'hostname' => $settingsEntity->getHostname(),
            'projectRegex' => $settingsEntity->getProjectRegex(),
            'issueRegex' => $settingsEntity->getIssueRegex(),
        ];
    }

    /**
     * @param Project $project
     * @return array
     */
    protected function projectToArray(Project $project): array
    {
        return
            [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'customer' => [
                    'id' => $project->getCustomer()->getId(),
                    'name' => $project->getCustomer()->getName(),
                ]
            ];
    }

    /**
     * @param $projectId
     * @return array
     */
    protected function getProjectsById($projectId): array
    {
        $this->logger->debug("getProjectsById", ["projectId" => $projectId]);
        $builder = $this->entityManager->getRepository(ProjectMeta::class)->createQueryBuilder('p');
        $query = $builder->where($builder->expr()->like('p.value', ':project_id'))
            ->setParameter('project_id', '%' . $projectId . '%')
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
     * @param array $data
     * @return array|JsonResponse
     */
    protected function makeJsonResponse(array $data, $status = 200): JsonResponse
    {
        return new JsonResponse(
            $data,
            $status,
            ['Access-Control-Allow-Origin' => '*']
        );
    }

    /**
     * @param $projectId
     * @return array
     */
    protected function activities($projectId): array
    {
        $projects = $this->getProjectsById($projectId);
        if (count($projects) == 0) {
            throw new NotFoundHttpException("Could not find valid project meta data");
        }
        $this->logger->debug("Activities: Found project metas", [$projectId => count($projects)]);

        $activityRepo = $this->entityManager->getRepository(Activity::class);

        $activities = [];
        foreach ($projects as $project) {
            $proj_activities = $activityRepo->findByProject($project);
            if (count($proj_activities)) {
                $activities[$project->getName()] = array_map(array($this, 'activityToArray'), $proj_activities);
            }
            $this->logger->debug(
                "Activities: Found project activities",
                [$project->getName() => count($proj_activities)]
            );
        }

        $builder = $activityRepo->createQueryBuilder('a');
        $query = $builder->where('a.project IS NULL')->getQuery();
        $global_act = $query->getResult();
        if (count($global_act)) {
            $activities["Global"] = array_map(array($this, 'activityToArray'), $global_act);
        }
        $this->logger->debug("Activities: Found global activities", ["Global" => count($global_act)]);

        if (empty($activities)) {
            $this->logger->warning('Activities: Could not find valid activity meta data');
        }

        return $activities;
    }

    /**
     * @param $issueId
     * @return array
     */
    protected function history($issueId): array
    {
        // TODO Make this an inner join rather than code
        $this->logger->debug("History:    Fetching history", ['issueId' => $issueId]);
        $timeSheetMetas = $this->entityManager
            ->getRepository(TimesheetMeta::class)
            ->findBy(
                [
                    'name' => TimesheetFieldSubscriber::ISSUE_ID,
                    "value" => $issueId,
                ]
            );
        $this->logger->debug("History:    Found metas", ['count' => count($timeSheetMetas)]);
        $timeSheets = [];
        foreach ($timeSheetMetas as $timesheetMeta) {
            $timeSheets[] = $timesheetMeta->getEntity();
        }

        return $timeSheets;
    }

    protected function activityToArray(Activity $activity): array
    {
        return ['id' => $activity->getId(), 'name' => $activity->getName()];
    }

    protected function getInitData($uri)
    {
        $this->logger->debug("uri", ["uri" => $uri]);

        // Get domain name
        $hostname = parse_url($uri, PHP_URL_HOST);
        $this->logger->debug("hostname", ["hostname" => $hostname]);

        // Lookup domain name
        $settingsEntity = $this->settingRepo->findByHostname($hostname);
        $this->logger->debug("settings", ["settings" => $settingsEntity]);
        if ($settingsEntity != null) {
            $settings = $this->settingsEntityToArray($settingsEntity);

            $this->logger->debug("settings", ["settings" => $settings]);

            // Get board id
            $matches = [];
            if (!preg_match("/" . $settingsEntity->getProjectRegex() . "/", $uri, $matches)) {
                $this->logger->info(
                    "No Board id matched",
                    ['uri' => $uri, 'projectRegex' => $settingsEntity->getProjectRegex()]
                );
            }
            if (count($matches) === 0) {
                throw new ProjectNotFoundException($settingsEntity);
            }
            $projectId = $this->cleanAZ($matches[0]);
            $this->logger->debug("projectId", ["projectId" => $projectId]);

            // Get issue id
            if (!preg_match("/" . $settingsEntity->getIssueRegex() . "/", $uri, $matches)) {
                $this->logger->info(
                    "No Issue id matched",
                    ['uri' => $uri, 'issueRegex' => $settingsEntity->getIssueRegex()]
                );
            }
            if (count($matches)) {
                $issueId = $this->cleanAZ($matches[0]);
            } else {
                // If there is no issue id then use project id.  This way time can be logged against the project
                $issueId = $projectId;
            }
            $this->logger->debug("issueId", ["issueId" => $issueId]);

            $projects = array_map(
                function ($project) {
                    return $this->projectToArray($project);
                },
                $this->getProjectsById($projectId)
            );
        } else {
            $settings = [];
            $projectId = "";
            $issueId = "";
            $projects = [];
        }

        return [
            'hostname' => $hostname,
            'projectId' => $projectId,
            'issueId' => $issueId,
            'settings' => $settings,
            'projects' => $projects,
            'role' => $this->roles(),
            'env' => $this->getParameter('kernel.environment'),
        ];
    }

    private function cleanAZ($input)
    {
        return preg_replace('/\W/', '', $input);
    }
}
