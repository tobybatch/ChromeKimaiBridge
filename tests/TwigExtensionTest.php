<?php
declare(strict_types=1);

namespace KimaiPlugin\ChromePluginBundle\tests;

use KimaiPlugin\ChromePluginBundle\Twig\TwigExtension;
use PHPUnit\Framework\TestCase;

class TwigExtensionTest extends TestCase
{

    public function testGetFilters()
    {
        $twig_extension = new TwigExtension();
        $filters = $twig_extension->getFilters();
        static::assertEquals(1, count($filters));
        static::assertEquals("hoursAndMinutes", $filters[0]->getName());
    }

    public function testHandMs() {
        $twig_extension = new TwigExtension();
        static::assertEquals('00:00', $twig_extension->hoursAndMinutes(0));
        static::assertEquals('00:55', $twig_extension->hoursAndMinutes(55));
        static::assertEquals('01:00', $twig_extension->hoursAndMinutes(60));
        static::assertEquals('01:10', $twig_extension->hoursAndMinutes(70));
        static::assertEquals('06:00', $twig_extension->hoursAndMinutes(360));
        static::assertEquals('06:30', $twig_extension->hoursAndMinutes(390));
    }
}
