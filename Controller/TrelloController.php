<?php

namespace KimaiPlugin\TrelloBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\TrelloBundle\Configuration\TrelloConfiguration;
use KimaiPlugin\TrelloBundle\Entity\TrelloEntity;
use KimaiPlugin\TrelloBundle\Form\TrelloType;
use KimaiPlugin\TrelloBundle\Repository\TrelloRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/trello/powerup")
 */
final class TrelloController extends AbstractController
{

    /**
     * @Route(path="", name="trello", methods={"GET"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        return $this->render('@Trello/index.html.twig');
    }

    /**
     * @Route(path="/login", name="trello_login", methods={"GET"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function loginAction(Request $request)
    {
        return $this->render('@Trello/login.html.twig');
    }
}
