<?php

namespace KimaiPlugin\ChromePluginBundle\tests;


use App\Entity\User;
use App\Tests\KernelTestTrait;
use FOS\RestBundle\Tests\Functional\app\AppKernel;
use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use KimaiPlugin\ChromePluginBundle\Service\ChromeService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ChromeControllerTest
 * @package KimaiPlugin\ChromePluginBundle\Tests
 */
class ServiceTest extends KernelTestCase
{
    use KernelTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();
    }


    public function testParseUriForIds() {
        /**
         * @var ChromeService
         */
        $chromeService = self::$container->get(ChromeService::class);

        $this->expectException(FileNotFoundException::class);
        $chromeService->parseUriForIds("http://foo.com/bar");
    }

    public function testGithHub() {
        $settingsEntity = new SettingEntity();
        $settingsEntity->setHostname("github.com");
        $settingsEntity->setRegex1("(?<=github.com\/)([a-zA-Z-]+)");
        $settingsEntity->setRegex2("\d+$");

        /**
         * @var ChromeService
         */
        $chromeService = self::$container->get(ChromeService::class);
        $response = $chromeService->getBoardAndCardId($settingsEntity, ControllerTest::TEST_URI_GITHUB);

        $this->assertEquals("tobybatch", $response['board_id']);
        $this->assertEquals("9", $response['card_id']);
    }

    public function testNextCloudDeck() {
        $settingsEntity = new SettingEntity();
        $settingsEntity->setHostname("some.next.cloud");
        $settingsEntity->setRegex1("[0-9]+");
        $settingsEntity->setRegex2("");

        /**
         * @var ChromeService
         */
        $chromeService = self::$container->get(ChromeService::class);
        $response = $chromeService->getBoardAndCardId($settingsEntity, ControllerTest::TEST_URI_NEXTCLOUD);

        $this->assertEquals("21", $response['board_id']);
        $this->assertEquals("543", $response['card_id']);
    }
}