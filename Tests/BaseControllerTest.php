<?php

namespace KimaiPlugin\ChromePluginBundle\Tests;

use App\DataFixtures\UserFixtures;
use App\Entity\Project;
use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use KimaiPlugin\ChromePluginBundle\Repository\SettingRepo;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles functional tests of the underlying controller base
 *
 * Class BaseControllerTest
 * @package KimaiPlugin\ChromePluginBundle\Tests
 */
class BaseControllerTest extends WebTestCase
{

    const API_MOUNT = "/api/chrome/json";
    const PROJECT_NAMES = ["dummyProject1", "dummyProject2",];

    private array $dummyHost = [
        "hostname" => "dummy.host.com",
        "projectRegex" => "^\W+",
        "issueRegex" => "[0-9]$",
    ];

    private KernelBrowser $client;
    private $projects;

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

    public function testGetInit()
    {
        $this->client->request(
            'GET',
            self::API_MOUNT . '/init?uri=http://dummy.host.com'
        );
        $content = $this->checkStatusAndGetContent($this->client->getResponse());
        $this->assertIsArray($content);
        echo "\nXX " . print_r($content, true) . "\n";
//[
//    {
//        "hostname": "dummy.host.com",
//        "projectRegex": "^\\W+",
//        "issueRegex": "[0-9]$"
//    }
//]
    }

    public function testGetRegisteredHosts()
    {
        $this->client->request(
            'GET',
            self::API_MOUNT . '/registeredHosts'
        );
        $content = $this->checkStatusAndGetContent($this->client->getResponse());
        $this->assertIsArray($content);

        $host = $content[0];
        $this->assertArrayHasKey("hostname", $host);
        $this->assertArrayHasKey("projectRegex", $host);
        $this->assertArrayHasKey("issueRegex", $host);
    }

//    public function testPostLogTime()
//    {
//    }
//
//    public function testPostSaveHostSetting()
//    {
//    }

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

    protected function setUp(): void
    {
        $this->client = self::createClient(
            [],
            [
                'HTTP_X_AUTH_USER' => UserFixtures::USERNAME_SUPER_ADMIN,
                'HTTP_X_AUTH_TOKEN' => UserFixtures::DEFAULT_API_TOKEN,
            ]
        );
        $this->getSettingsRepo()->save($this->getDummyHost());

        self::bootKernel();
        $entityManager = self::$container->get("doctrine")->getManager();
        $fixtures = new DataFixtures();
        $fixtures->load($entityManager);
        $entityManager->flush();

        $this->projects = $entityManager
            ->getRepository(Project::class)
            ->findAll();
    }

//    public function testSetProject()
//    {
//    }

    protected function tearDown(): void
    {
        $this->getSettingsRepo()->removeAll();
    }

    private function checkStatusAndGetContent(Response $response, int $status = 200): array
    {
        $response = $this->client->getResponse();
        $this->assertEquals($status, $response->getStatusCode());
        return json_decode($response->getContent(), true);
    }

    private function getDummyHost()
    {
        $dummySettingsEntity = new SettingEntity();
        $dummySettingsEntity->setHostname($this->dummyHost['hostname']);
        $dummySettingsEntity->setProjectRegex($this->dummyHost['projectRegex']);
        $dummySettingsEntity->setIssueRegex($this->dummyHost['issueRegex']);

        return $dummySettingsEntity;
    }

    private function getSettingsRepo(): SettingRepo
    {
        $client = static::createClient();

        return new SettingRepo(
            $client->getContainer(),
            new DummyLogger(),
            $client->getContainer()->getParameter('kimai.data_dir')
        );
    }
}
