<?php

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

        $time = sprintf("%02d:%02d", $hours, $minutes);
//        $time = "";
//        if ($hours > 0) {
//            $time = $hours . "h";
//        }
//        if ($hours > 0 && $minutes > 0) {
//            $time = $time . " ";
//        }
//        if ($minutes > 0) {
//            $time = $time . $minutes . "m";
//        }
//
//        if ($time == "") {
//            $time = "0m";
//        }
        return $time;
    }
}