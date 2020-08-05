<?php

namespace KimaiPlugin\ChromePluginBundle\tests;

use _HumbugBoxebb6c51d1e3e\Nette\FileNotFoundException;
use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use KimaiPlugin\ChromePluginBundle\Repository\SettingRepo;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ChromeRepoTest
 * @package KimaiPlugin\ChromePluginBundle\Tests
 */
class RepoTest extends ControllerBase
{
    public function testFindByHost(){
        $setting = $this->chromeRepo->findByHostname("github.com");
        self::assertNotFalse($setting);
        self::assertEquals("github.com", $setting->getHostname());
        self::assertEquals('(?<=^https:\/\/github.com\/)(\w+\/\w+)', $setting->getRegex1());
        self::assertEquals("\d+$", $setting->getRegex2());

        $this->expectException(FileNotFoundException::class);
        $setting = $this->chromeRepo->findByHostname("no.such.host");
        self::assertFileExists($setting);
    }

    public function testFindAll(){
        $settings = $this->chromeRepo->findAll();
        self::assertEquals(2, count($settings));

        $setting0 = $settings[0];
        self::assertEquals("foo.com", $setting0->getHostname());
        self::assertEquals('some_regex', $setting0->getRegex1());
        self::assertEquals("", $setting0->getRegex2());

        $setting1 = $settings[1];
        self::assertEquals("github.com", $setting1->getHostname());
        self::assertEquals('(?<=^https:\/\/github.com\/)(\w+\/\w+)', $setting1->getRegex1());
        self::assertEquals("\d+$", $setting1->getRegex2());
    }

    public function testSave() {
        $setting = new SettingEntity();
        $setting->setHostname("new.hostname.com");
        $setting->setRegex1("new regex");
        $this->chromeRepo->save($setting);
        self::assertFileExists($this->storage . '/new.hostname.com.json');
    }

    public function testSaveAll() {
        $setting0 = new SettingEntity();
        $setting0->setHostname("new0.hostname.com");
        $setting0->setRegex1("new0 regex");

        $setting1 = new SettingEntity();
        $setting1->setHostname("new1.hostname.com");
        $setting1->setRegex1("new1 regex");

        $settings = [$setting0, $setting1];
        $this->chromeRepo->saveAll($settings);

        self::assertFileExists($this->storage . '/new0.hostname.com.json');
        self::assertFileExists($this->storage . '/new1.hostname.com.json');
    }

    public function testRemove() {
        $setting0 = new SettingEntity();
        $setting0->setHostname("github.com");
        self::assertTrue($this->chromeRepo->remove($setting0));
        self::assertFileNotExists($this->storage . '/github.com.json');

        $this->expectException(FileNotFoundException::class);
        self::assertFalse($this->chromeRepo->remove($setting0));
    }

    public function testRemoveAll() {
        $this->chromeRepo->removeAll();

        self::assertFileNotExists($this->storage . '/github.com.json');
        self::assertFileNotExists($this->storage . '/foo.com.json');
    }

    public function removeByHostName() {
        $this->chromeRepo->removeByHost("github.com");
        self::assertFileNotExists($this->storage . '/github.com.json');
    }
}