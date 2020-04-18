<?php
namespace KimaiPlugin\TrelloBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\TrelloBundle\Repository\TrelloRepository;
use App\Entity\Project;
use App\Entity\ProjectMeta;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 *
 * @Security("is_granted('view_own_timesheet')")
 * @Route(path="/trello/api/")
 */
class TimesheetController extends AbstractController
{

    /**
     * @var TrelloRepository
     */
    protected $repository;
    /**
     * @param TrelloRepository $repository
     */
    public function __construct(TrelloRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Returns a list of settings.
     *
     * @Route(path="/settings", name="neontribe_ext_settigs")
     * @Security("is_granted('create_own_timesheet')")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function settingsAction(Request $request)
    {
        $settings = $this->repository->getConfig();

        $response = new JsonResponse($this->repository::toArray($settings));
        return $response;
    }

    /**
     * Show and update a list of settings.
     *
     * @Route(path="/config", name="neontribe_ext_config")
     * @Security("is_granted('create_own_timesheet')")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function configAction(Request $request)
    {
        $settings = $this->repository->getConfig();

        $form = $this->createFormBuilder($settings)
            ->add('durationOnly', CheckboxType::class, ['required' => false])
            ->add('showTags', CheckboxType::class, ['required' => false])
            ->add('showFixedRate', CheckboxType::class, ['required' => false])
            ->add('showHourlyRate', CheckboxType::class, ['required' => false])
            ->add('save', SubmitType::class, ['label' => 'Save'])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $settings = $form->getData();
            $this->repository->saveConfig($settings);
            return $this->redirectToRoute('neontribe_ext_config');
        }

        return $this->render('@Trello/settings.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Returns a list of projects.
     *
     * @Route(path="/projects", name="neontribe_ext_projects", methods="GET")
     * @Security("is_granted('create_own_timesheet')")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function projectAction(Request $request)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $projectRepo = $entityManager->getRepository(Project::class);
        $projects = $projectRepo->findAll();

        $_customers = [];
        $_projects = [];

        foreach ($projects as $project) {
            $customer = $project->getCustomer();
            $customerName = $customer->getName();
            if (! array_key_exists($customerName, $_customers)) {
                $_customers[$customerName] = $customer;
                $_projects[$customerName] = [];
            }
            $_projects[$customerName][] = $project;
            if ($project->getId() == 1) dump($project);
        }

        return $this->render('@Trello/projects.html.twig', [
            'customers' => $_customers,
            'projects' => $_projects,
            'path' => $url = $this->generateUrl(
                'neontribe_ext_project_update',
                 [
                    'project' => 'PROJECT_ID',
                    'extid' => 'EXT_ID',
                     ]
                 ),
        ]);
    }

    /**
     * Returns a list of projects.
     *
     * , methods="POST")
     * 
     * @Route(path="/project/{project}/update", name="neontribe_ext_project_update")
     * @Security("is_granted('create_own_timesheet')")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function projectUpdateAction(Request $request, Project $project)
    {
        $value = $request->query->get("extid");
        if ($value != null) {
            $externalId = (new ProjectMeta())->setName('externalID')->setValue($value);
            $project->setMetaField($externalId);
            $this->getDoctrine()->getManager()->persist($project);
            $this->getDoctrine()->getManager()->flush();
        }
        return $this->projectAction($request);
    }
}