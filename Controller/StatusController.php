<?php

namespace KimaiPlugin\ChromePluginBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/chrome/public/")
 */
class StatusController extends BaseController
{
    const VERSION = "1.0.0";
    const NAME = "Kimai chrome plugin";

    /**
     * @Route(path="status", name="chrome_status", methods={"GET"})
     */
    public function getStatus(): JsonResponse
    {
        return $this->makeJsonResponse(
            [
                'name' => self::NAME,
                'version' => self::VERSION,
                'role' =>$this->roles(),
            ]
        );
    }
}
