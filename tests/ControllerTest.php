<?php

namespace KimaiPlugin\ChromePluginBundle\tests;


use App\Entity\User;
use KimaiPlugin\ChromePluginBundle\Controller\ChromeController;
use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ChromeControllerTest
 * @package KimaiPlugin\ChromePluginBundle\Tests
 */
class ControllerTest extends ControllerBase
{
    public const TEST_URI_GITHUB = "https://github.com/tobybatch/ChromeKimaiBridge/issues/9";
    public const TEST_URI_NEXTCLOUD = "https://some.next.cloud/index.php/apps/deck/#/board/21/card/543";
    public const TEST_URI_FOO = "https://foo.com/some/weird/issue/9/onboard/banana";

    protected function setUp():void {
        parent::setUp();
    }

    public function testPublicAccess()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $this->assertAccessIsGranted($client, '/chrome/status');
        $this->assertAccessIsGranted($client, '/chrome/uri?uri=' . self::TEST_URI_GITHUB);
        $this->assertAccessIsGranted($client, '/chrome/popup/123/456');
    }

    public function testGatedAccess()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $client->request("GET", "/chrome/settings");
        /** @var RedirectResponse $response */
        $response = $client->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertEquals(403, $response->getStatusCode());

        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted($client, '/chrome/settings');
    }

    public function testStatus()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $client->request("GET", "/chrome/status");
        /** @var RedirectResponse $response */
        $response = $client->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertEquals(2, count($data));
        self::assertArrayHasKey("name", $data);
        self::assertArrayHasKey("version", $data);
        self::assertEquals("Kimai chrome plugin", $data['name']);
    }

    public function testSettings()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $client->request("GET", "/chrome/settings");
        $response = $client->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertEquals(200, $response->getStatusCode());
    }

    public function testNoHosts()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $container = $client->getContainer();
        $filesystem = new Filesystem();
        $filesystem->remove($container->getParameter("kimai.data_dir") .
            '/chromeSetting/' .
            $container->getParameter('kernel.environment'));

        $client->request("GET", "/chrome/settings");
        $response = $client->getResponse();

        self::assertInstanceOf(Response::class, $response);
        self::assertEquals(200, $response->getStatusCode());
    }

    public function testRemoveHost()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $client->request("GET", "/chrome/settings?hostname=github.com");
        $response = $client->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertEquals(302, $response->getStatusCode());
    }

    public function testPopupWithIds()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $client->request("GET", "/chrome/popup/no-board/no-card");
        $response = $client->getResponse();
        self::assertEquals(302, $response->getStatusCode());

        $client->request("GET", "/chrome/popup/" . self::EXT_PROJECT_ID . "/" . self::EXT_CARD_IDS[0]);
        $response = $client->getResponse();
        self::assertEquals(200, $response->getStatusCode());

        $client->request("GET", "/chrome/popup/" . self::EXT_PROJECT_ID);
        $response = $client->getResponse();
        self::assertEquals(200, $response->getStatusCode());
    }

    public function testPopupWithUri()
    {
        // Most of this is tested in the public access test, here we dig into the corners
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);
        $this->assertAccessIsGranted($client, '/chrome/uri?uri=' . self::TEST_URI_FOO);
    }

    public function testProcessEdit() {
        $setting = ChromeController::processEditForm([
            "hostname" => "unique.host.name",
            "regex1" => "first regex",
            "regex2" => "second regex"
        ]);
        self::assertEquals("unique.host.name", $setting->getHostname());
        self::assertEquals("first regex", $setting->getRegex1());
        self::assertEquals("second regex", $setting->getRegex2());
    }

    public function testProjectAssoc()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $this->assertAccessIsGranted(
            $client,
            "/chrome/project/" . self::EXT_PROJECT_ID . "/" . self::EXT_CARD_IDS[0]
        );
        $response = $client->getResponse();
        self::assertEquals(200, $response->getStatusCode());
    }

    public function testSettingsTest() {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $this->assertAccessIsGranted(
            $client,
            "/chrome/settings/test"
        );
        $response = $client->getResponse();
        self::assertEquals(200, $response->getStatusCode());

        $this->assertAccessIsGranted(
            $client,
            "/chrome/settings/test?hostname=github.com"
        );
        $response = $client->getResponse();
        self::assertEquals(200, $response->getStatusCode());
    }
}