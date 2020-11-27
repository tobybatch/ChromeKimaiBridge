<?php

namespace KimaiPlugin\ChromePluginBundle\Tests;

use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use KimaiPlugin\ChromePluginBundle\Repository\SettingRepo;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Class SettingsRepoTest
 * @package KimaiPlugin\ChromePluginBundle\Tests
 */
class SettingsRepoTest extends WebTestCase
{
    private SettingRepo $settingRepo;

    public function testConstructor()
    {
        $storage = $this->settingRepo->getStorage();
        $this->settingRepo->removeAll();
        rmdir($storage);
        $this->createSettingsRepo();
        self::assertFileExists($storage . "/" . "github.com.json");
    }

    public function testRemove()
    {
        $setting = new SettingEntity();
        $setting->setHostname("github.com");
        $this->settingRepo->remove($setting);
        $storage = $this->settingRepo->getStorage();
        self::assertFileNotExists($storage . "/" . "github.com.json");
    }

    public function testGetPredefined() {
        $names = ["GitHub", "NextCloudDeck"];

        foreach ($names as $name) {
            $function = "getPredefined" . $name;
            $setting = $this->settingRepo->$function();
            $this->assertNotNull($setting->getHostname());
            $this->assertNotNull($setting->getProjectRegex());
            $this->assertNotNull($setting->getIssueRegex());
        }
    }

    protected function setUp(): void
    {
        $this->createSettingsRepo();
    }

    protected function createSettingsRepo()
    {
        // TODO This should be a kernel test case
        $client = static::createClient();
        $this->settingRepo = new SettingRepo(
            $client->getContainer(),
            $client->getContainer()->getParameter('kimai.data_dir')
        );
    }

    protected function tearDown(): void
    {
        $this->settingRepo->removeAll();
    }
}
