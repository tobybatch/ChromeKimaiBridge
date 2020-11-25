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
    /**
     * @Route(path="status", name="chrome_status", methods={"GET"})
     */
    public function getStatus(): JsonResponse
    {
        return $this->makeJsonResponse(
            [
                'name' => "Kimai chrome plugin",
                'version' => "1.0.0",
                'role' =>$this->roles(),
            ]
        );
    }

    /**
     * @Route(path="info", name="chrome_info", methods={"GET"})
     */
    public function getInfo(): Response
    {
        return new Response("<html><body>Foo</body></html>");
    }


}
