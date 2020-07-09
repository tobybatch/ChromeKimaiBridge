<?php

namespace KimaiPlugin\ChromePluginBundle\Tests;

use App\Configuration\LanguageFormattings;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Twig\Extensions;
use App\Utils\LocaleSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Util\IntlTestHelper;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtensionTest extends TestCase
{

    public function testGetFilters()
    {
        $twigExtension = new TwigExtension();
        $filters = $twigExtension->getFilters();
        $this->assertEquals(1, count($filters));
        $this->assertEquals("hoursAndMinutes", $filters[0]->getName());
    }

    function testHoursAndMinutes() {
        $twigExtension = new TwigExtension();
        $this->assertEquals('0m', $twigExtension->hoursAndMinutes(0));
        $this->assertEquals('55m', $twigExtension->hoursAndMinutes(55));
        $this->assertEquals('1h', $twigExtension->hoursAndMinutes(60));
        $this->assertEquals('1h 10m', $twigExtension->hoursAndMinutes(70));
        $this->assertEquals('6h', $twigExtension->hoursAndMinutes(360));
        $this->assertEquals('6h 30m', $twigExtension->hoursAndMinutes(390));
    }
}
