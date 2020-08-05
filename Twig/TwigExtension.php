<?php
declare(strict_types=1);

namespace KimaiPlugin\ChromePluginBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TwigExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('hoursAndMinutes', [$this, 'hoursAndMinutes']),
        ];
    }

    /**
     * @param $mins
     * @return string
     */
    public function hoursAndMinutes($mins)
    {
        $hours = (int)(floor($mins / 60));
        $minutes = (int)($mins - ($hours * 60));

        return sprintf("%02d:%02d", $hours, $minutes);
    }
}