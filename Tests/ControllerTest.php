<?php

namespace KimaiPlugin\ChromePluginBundle\Tests;

use App\DataFixtures\UserFixtures;
use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Timesheet;
use Doctrine\ORM\EntityManagerInterface;
use KimaiPlugin\ChromePluginBundle\Controller\ChromeController;
use KimaiPlugin\ChromePluginBundle\Controller\StatusController;
use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use KimaiPlugin\ChromePluginBundle\EventSubscriber\ProjectFieldSubscriber;
use KimaiPlugin\ChromePluginBundle\Repository\SettingRepo;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles functional tests of the underlying controller base
 *
 * Class BaseControllerTest
 * @package KimaiPlugin\ChromePluginBundle\Tests
 */
class ControllerTest extends WebTestCase
{

    const API_MOUNT = "/api/chrome/json";
    const PROJECT_NAMES = ["dummyProject1", "dummyProject2",];

    private array $dummyHost = [
        "hostname" => "dummy.host.com",
        "projectRegex" => "(?<=dummy.host.com\/)([a-zA-Z-]+)",
        "issueRegex" => "[0-9]$",
    ];

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    /**
     * @var SettingRepo
     */
    private SettingRepo $settingRepo;

    public function testStatus()
    {
        foreach (self::PROJECT_NAMES as $projectName) {
            $this->client->request('GET', '/chrome/public/status');
            $content = $this->checkStatusAndGetContent($this->client->getResponse());

            $this->assertIsArray($content);
            $this->assertArrayHasKey("role", $content);
            $this->assertEquals(StatusController::VERSION, $content['version']);
            $this->assertEquals(StatusController::NAME, $content['name']);
        }
    }

    public function testDeleteProject()
    {
        $this->client->request(
            'DELETE',
            self::API_MOUNT . '/project/dummy.host.com'
        );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        $settingFile =
            $this->client->getContainer()->getParameter('kimai.data_dir') .
            "/chromeSetting/test/dummy.host.com.json";
        self::assertFileNotExists($settingFile);
    }

    public function testGetActivities()
    {
        foreach (self::PROJECT_NAMES as $projectName) {
            $this->client->request(
                'GET',
                self::API_MOUNT . '/activities?boardId=' . $projectName
            );
            $content = $this->checkStatusAndGetContent($this->client->getResponse());

            $this->assertIsArray($content);
            $this->assertArrayHasKey("Global", $content);
        }
    }

    public function testGetActivitiesBoardNotFound()
    {
        $this->client->request(
            'GET',
            self::API_MOUNT . '/activities?boardId=no-such-board'
        );
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    public function testGetProjects()
    {
        $this->client->request(
            'GET',
            self::API_MOUNT . '/projects'
        );
        $content = $this->checkStatusAndGetContent($this->client->getResponse());
        $this->assertIsArray($content);

        $project = $content[0];
        $this->assertArrayHasKey("id", $project);
        $this->assertArrayHasKey("name", $project);
        $this->assertArrayHasKey("customer", $project);
    }

    public function testGetInitNoUri()
    {
        $this->client->request(
            'GET',
            self::API_MOUNT . '/init'
        );

        $response = $this->client->getResponse();
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testGetInit()
    {
        $this->client->request(
            'GET',
            self::API_MOUNT . '/init?uri=http://dummy.host.com/testptoject/issue/123'
        );
        $content = $this->checkStatusAndGetContent($this->client->getResponse());
        $this->assertIsArray($content);

        $this->assertEquals("dummy.host.com", $content['hostname']);
        $this->assertEquals("testptoject", $content['projectId']);
        $this->assertEquals("3", $content['issueId']);

        $this->assertIsArray($content['settings']);
        $this->assertEquals("dummy.host.com", $content['settings']['hostname']);
        $this->assertEquals("(?<=dummy.host.com\/)([a-zA-Z-]+)", $content['settings']['projectRegex']);
        $this->assertEquals("[0-9]$", $content['settings']['issueRegex']);

        $this->assertIsArray($content['projects']);
        $this->assertCount(0, $content['projects']); // No seed data will match dummy.host.com

        $this->assertIsArray($content['role']);
        $this->asserttrue(in_array("ROLE_SUPER_ADMIN", $content['role']));

        // No board
        $this->client->request(
            'GET',
            self::API_MOUNT . '/init?uri=http://dummy.host.com/'
        );
        $this->assertEquals(422, $this->client->getResponse()->getStatusCode());

        // No Issue
        $this->client->request(
            'GET',
            self::API_MOUNT . '/init?uri=http://dummy.host.com/testptoject/'
        );
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());

        // No host
        $this->client->request(
            'GET',
            self::API_MOUNT . '/init?uri=http://no.host/'
        );
        $content = $this->checkStatusAndGetContent($this->client->getResponse());
        $this->assertIsArray($content);
        $this->assertEquals("no.host", $content['hostname']);
        $this->assertEquals("", $content['projectId']);
        $this->assertEquals("", $content['issueId']);

        $this->assertIsArray($content['settings']);
        $this->assertCount(0, $content['settings']);
        $this->assertIsArray($content['projects']);
        $this->assertCount(0, $content['projects']);

        $this->assertIsArray($content['role']);
        $this->assertCount(0, $content['role']);
    }

    public function testGetRegisteredHosts()
    {
        $this->client->request(
            'GET',
            self::API_MOUNT . '/registeredHosts'
        );
        $content = $this->checkStatusAndGetContent($this->client->getResponse());
        $this->assertCount(1, $content);
        $this->assertArrayHasKey("hostname", $content[0]);
        $this->assertArrayHasKey("projectRegex", $content[0]);
        $this->assertArrayHasKey("issueRegex", $content[0]);
    }

    public function testSetProject()
    {
        $project = $this->entityManager->getRepository(Project::class)->findAll()[0];
        $this->assertNull($project->getMetaField(ProjectFieldSubscriber::PROJECT_ID));
        $this->client->request(
            'POST',
            self::API_MOUNT . '/project/' . $project->getId() . "?projectId=new-board-name"
        );
        $this->checkStatusAndGetContent($this->client->getResponse());
    }


    public function testPostLogTime()
    {
        $activity = $this->entityManager->getRepository(Activity::class)->findAll()[0];
        $project = $this->entityManager->getRepository(Project::class)->findAll()[0];
        $payload = [
            'activity' => $activity->getId(),
            'project' => $project->getId(),
            'duration' => rand(15, 180),
            'date' => '2020-11-27',
            'description' => "Sed ut perspiciatis unde omnis iste natus error sit voluptatem",
            'link' => 'https://some.host/com/some/path',
        ];
        $this->client->request(
            'POST',
            self::API_MOUNT . '/logtime',
            [],
            [],
            [],
            json_encode($payload)
        );
        $this->checkStatusAndGetContent($this->client->getResponse());

        $timesheet = $this->entityManager->getRepository(Timesheet::class)->findOneBy(
            [
                'description' => "Sed ut perspiciatis unde omnis iste natus error sit voluptatem"
            ]
        );
        $this->assertNotNull($timesheet);
    }

    public function testPostSaveHostSetting()
    {
        $payload = [
            'oldHostName' => $this->dummyHost['hostname'],
            'hostname' => 'test.host.name',
            'projectRegex' => 'projectRegex' . rand(100, 999),
            'issueRegex' => 'issueRegex' . rand(100, 999),
        ];
        $this->client->request('POST', self::API_MOUNT . '/project', [], [], [], json_encode($payload));
        $this->checkStatusAndGetContent($this->client->getResponse());
        $settings = $this->settingRepo->findByHostname($payload['hostname']);
        $this->assertEquals($payload['hostname'], $settings->getHostname());
        $this->assertEquals($payload['projectRegex'], $settings->getProjectRegex());
        $this->assertEquals($payload['issueRegex'], $settings->getIssueRegex());
    }

    public function testGetHistory()
    {
        foreach (self::PROJECT_NAMES as $projectName) {
            for ($i = 1; $i <= 3; $i++) {
                $this->client->request(
                    'GET',
                    self::API_MOUNT . '/history/' . $projectName . ":" . $i
                );
                $content = $this->checkStatusAndGetContent($this->client->getResponse());
                $this->assertIsArray($content);

                $this->assertArrayHasKey("id", $content[0]);
                $this->assertArrayHasKey("duration", $content[0]);
                $this->assertArrayHasKey("user", $content[0]);
                $this->assertArrayHasKey("activity", $content[0]);
                $this->assertArrayHasKey("description", $content[0]);
                $this->assertArrayHasKey("date", $content[0]);
            }
        }
    }

    public function testFunctionalActivities()
    {
        $class = new ReflectionClass('KimaiPlugin\ChromePluginBundle\Controller\ChromeController');
        $method = $class->getMethod("activities");
        $method->setAccessible(true);

        $logger = $this->createMock(LoggerInterface::class);
        $this->entityManager = self::$container->get("doctrine")->getManager();
        $controller = new ChromeController($this->entityManager, $this->settingRepo, $logger);

        $this->expectException(NotFoundHttpException::class);
        $method->invokeArgs($controller, array("123"));
    }

    protected function setUp(): void
    {
        // TODO This should be a kernel test case
        $client = static::createClient();
        $this->settingRepo = new SettingRepo(
            $client->getContainer(),
            $client->getContainer()->getParameter('kimai.data_dir')
        );

        $this->client = self::createClient(
            [],
            [
                'HTTP_X_AUTH_USER' => UserFixtures::USERNAME_SUPER_ADMIN,
                'HTTP_X_AUTH_TOKEN' => UserFixtures::DEFAULT_API_TOKEN,
            ]
        );
        $this->settingRepo->save($this->getDummyHost());

        self::bootKernel();
        $this->entityManager = self::$container->get("doctrine")->getManager();
        $fixtures = new DataFixtures();
        $fixtures->load($this->entityManager);
        $this->entityManager->flush();

        // Create a host file for http://dummy.host.com
        $this->settingRepo = new SettingRepo(self::$container, self::$container->getParameter("kimai.data_dir"));
        $this->settingRepo->save($this->getDummyHost());
    }

    protected function tearDown(): void
    {
        $this->settingRepo->removeAll();
    }

    private function checkStatusAndGetContent(Response $response, int $status = 200): array
    {
        $response = $this->client->getResponse();
        $this->assertEquals($status, $response->getStatusCode());
        return json_decode($response->getContent(), true);
    }

    private function getDummyHost(): SettingEntity
    {
        $dummySettingsEntity = new SettingEntity();
        $dummySettingsEntity->setHostname($this->dummyHost['hostname']);
        $dummySettingsEntity->setProjectRegex($this->dummyHost['projectRegex']);
        $dummySettingsEntity->setIssueRegex($this->dummyHost['issueRegex']);

        return $dummySettingsEntity;
    }
}
