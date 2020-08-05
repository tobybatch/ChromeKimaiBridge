<?php

namespace KimaiPlugin\ChromePluginBundle\tests;

use App\DataFixtures\UserFixtures;
use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\ProjectMeta;
use App\Entity\Timesheet;
use App\Entity\TimesheetMeta;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use DOMElement;
use Gedmo\Tree\RepositoryInterface;
use KimaiPlugin\ChromePluginBundle\Entity\SettingEntity;
use KimaiPlugin\ChromePluginBundle\EventSubscriber\ProjectFieldSubscriber;
use KimaiPlugin\ChromePluginBundle\EventSubscriber\TimesheetFieldSubscriber;
use KimaiPlugin\ChromePluginBundle\Repository\SettingRepo;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

/**
 * ControllerBaseTest adds some useful functions for writing integration tests.
 */
abstract class ControllerBase extends WebTestCase
{
    // use KernelTestTrait;

    protected EntityManagerInterface $entityManager;
    protected RepositoryInterface $projectRepo;

    public const EXT_PROJECT_ID = 'board_id_123';
    public const EXT_CARD_IDS = [
        'Card_1',
        'Card_2'
    ];
    /**
     * @var SettingRepo
     */
    protected SettingRepo $chromeRepo;
    protected string $storage;

    protected function setUp(): void {
        parent::setUp();
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $project_repo = $this->entityManager
            ->getRepository(Project::class)
        ;
        $activity_repo = $this->entityManager
            ->getRepository(Activity::class)
        ;
        $user_repo = $this->entityManager
            ->getRepository(User::class)
        ;
        $user_entity = $user_repo->find(1);

        $projects = $project_repo->findAll();
        $project = $projects[0];
        $project_meta = (new ProjectMeta())->setName(ProjectFieldSubscriber::META_NAME)->setValue(self::EXT_PROJECT_ID);
        $project->setMetaField($project_meta);
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        $activities = array_merge(
            $activity_repo->findBy(['project' => null]),
            $activity_repo->findBy(['project' => $project])
        );

        for ($i = 0; $i < 15; ++$i) {
            $timesheet = new TimeSheet();
            $timesheet->setProject($project);
            $timesheet->setActivity($activities[($i^2) % count($activities)]);
            $timesheet->setUser($user_entity);
            $timesheet->setBegin(new \DateTime());
            $timesheet->setEnd(new \DateTime());

            $timesheet_meta = (new TimesheetMeta())->setName(TimesheetFieldSubscriber::META_NAME);
            $timesheet_meta->setValue(self::EXT_CARD_IDS[$i % count(self::EXT_CARD_IDS)]);

            $timesheet->setMetaField($timesheet_meta);
            $this->entityManager->persist($timesheet);
            $this->entityManager->flush();
        }

        $client = static::createClient();
        $container = $client->getContainer();
        $data_dir = $container->getParameter("kimai.data_dir");
        $kernel_env = $container->getParameter('kernel.environment');
        $filesystem = new Filesystem();
        $this->storage = $data_dir . '/chromeSetting/' . $kernel_env;
        $filesystem->remove($this->storage);
        $logger = new Logger($kernel_env);

        $this->chromeRepo = new SettingRepo($container, $logger, $data_dir);
        $setting = new SettingEntity();
        $setting->setHostname("foo.com");
        $setting->setRegex1("some_regex");
        $this->chromeRepo->save($setting);
    }

    /**
     * @param HttpKernelBrowser $client
     */
    protected static function assertHasProgressbar(HttpKernelBrowser $client)
    {
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('<div class="progress-bar progress-bar-', $content);
        self::assertStringContainsString('" role="progressbar" aria-valuenow="', $content);
        self::assertStringContainsString('" aria-valuemin="0" aria-valuemax="100" style="width: ', $content);
    }

    /**
     * @param string $url
     * @param string $method
     */
    protected function assertUrlIsSecured(string $url, $method = 'GET')
    {
        $client = self::createClient();
        $this->assertRequestIsSecured($client, $url, $method);
    }

    /**
     * @param HttpKernelBrowser $client
     * @param string $url
     * @param string $method
     */
    protected function assertRequestIsSecured(HttpKernelBrowser $client, string $url, ?string $method = 'GET')
    {
        $this->request($client, $url, $method);

        /** @var RedirectResponse $response */
        $response = $client->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);

        self::assertTrue(
            $response->isRedirect(),
            sprintf('The secure URL %s is not protected.', $url)
        );

        self::assertStringEndsWith(
            '/login',
            $response->getTargetUrl(),
            sprintf('The secure URL %s does not redirect to the login form.', $url)
        );
    }

    /**
     * @param HttpKernelBrowser $client
     * @param string $url
     * @param string $method
     * @param array $parameters
     * @param string $content
     * @return Crawler
     */
    protected function request(HttpKernelBrowser $client, string $url, $method = 'GET', array $parameters = [], string $content = null)
    {
        return $client->request($method, $this->createUrl($url), $parameters, [], [], $content);
    }

    /**
     * @param string $url
     * @return string
     */
    protected function createUrl($url)
    {
        // This plugin does not need the locale
        // return '/' . self::DEFAULT_LANGUAGE . '/' . ltrim($url, '/');
        return '/' . ltrim($url, '/');
    }

    /**
     * @param string $role
     * @param string $url
     * @param string $method
     */
    protected function assertUrlIsSecuredForRole(string $role, string $url, string $method = 'GET')
    {
        $client = $this->getClientForAuthenticatedUser($role);
        $client->request($method, $this->createUrl($url));
        $response = $client->getResponse();
        self::assertFalse(
            $response->isSuccessful(),
            sprintf('The secure URL %s is not protected for role %s', $url, $role)
        );
        $this->assertAccessDenied($client);
    }

    protected function getClientForAuthenticatedUser(string $role = User::ROLE_USER): HttpKernelBrowser
    {
        switch ($role) {
            case User::ROLE_SUPER_ADMIN:
                $client = self::createClient([], [
                    'PHP_AUTH_USER' => UserFixtures::USERNAME_SUPER_ADMIN,
                    'PHP_AUTH_PW' => UserFixtures::DEFAULT_PASSWORD,
                ]);
                break;

            case User::ROLE_ADMIN:
                $client = self::createClient([], [
                    'PHP_AUTH_USER' => UserFixtures::USERNAME_ADMIN,
                    'PHP_AUTH_PW' => UserFixtures::DEFAULT_PASSWORD,
                ]);
                break;

            case User::ROLE_TEAMLEAD:
                $client = self::createClient([], [
                    'PHP_AUTH_USER' => UserFixtures::USERNAME_TEAMLEAD,
                    'PHP_AUTH_PW' => UserFixtures::DEFAULT_PASSWORD,
                ]);
                break;

            case User::ROLE_USER:
                $client = self::createClient([], [
                    'PHP_AUTH_USER' => UserFixtures::USERNAME_USER,
                    'PHP_AUTH_PW' => UserFixtures::DEFAULT_PASSWORD,
                ]);
                break;

            default:
                $client = null;
                break;
        }

        return $client;
    }

    protected function assertAccessDenied(HttpKernelBrowser $client)
    {
        self::assertFalse(
            $client->getResponse()->isSuccessful(),
            'Access is not denied for URL: ' . $client->getRequest()->getUri()
        );
        self::assertStringContainsString(
            'Symfony\Component\Security\Core\Exception\AccessDeniedException',
            $client->getResponse()->getContent(),
            'Could not find AccessDeniedException in response'
        );
    }

    protected function assertAccessIsGranted(HttpKernelBrowser $client, string $url, string $method = 'GET', array $parameters = [])
    {
        $this->request($client, $url, $method, $parameters);
        $response = $client->getResponse();
        self::assertTrue($response->isSuccessful());
    }

    protected function assertRouteNotFound(HttpKernelBrowser $client)
    {
        self::assertFalse($client->getResponse()->isSuccessful());
        self::assertEquals(404, $client->getResponse()->getStatusCode());
    }

    protected function assertMainContentClass(HttpKernelBrowser $client, string $classname)
    {
        self::assertStringContainsString('<section class="content ' . $classname . '">', $client->getResponse()->getContent());
    }

    /**
     * @param HttpKernelBrowser $client
     */
    protected function assertHasDataTable(HttpKernelBrowser $client)
    {
        self::assertStringContainsString('<table class="table table-striped table-hover dataTable" role="grid" data-reload-event="', $client->getResponse()->getContent());
    }

    /**
     * @param HttpKernelBrowser $client
     * @param string $id
     * @param int $count
     */
    protected function assertDataTableRowCount(HttpKernelBrowser $client, string $id, int $count)
    {
        $node = $client->getCrawler()->filter('section.content div#' . $id . ' table.table-striped tbody tr:not(.summary)');
        self::assertEquals($count, $node->count());
    }

    /**
     * @param HttpKernelBrowser $client
     * @param array $buttons
     */
    protected function assertPageActions(HttpKernelBrowser $client, array $buttons)
    {
        $node = $client->getCrawler()->filter('section.content-header div.breadcrumb div.box-tools div.btn-group a');

        /** @var DOMElement $element */
        foreach ($node->getIterator() as $element) {
            $expectedClass = str_replace('btn btn-default btn-', '', $element->getAttribute('class'));
            self::assertArrayHasKey($expectedClass, $buttons);
            $expectedUrl = $buttons[$expectedClass];
            self::assertEquals($expectedUrl, $element->getAttribute('href'));
        }

        self::assertEquals(count($buttons), $node->count(), 'Invalid amount of page actions');
    }

    /**
     * @param string $role the USER role to use for the request
     * @param string $url the URL of the page displaying the initial form to submit
     * @param string $formSelector a selector to find the form to test
     * @param array $formData values to fill in the form
     * @param array $fieldNames array of form-fields that should fail
     * @param bool $disableValidation whether the form should validate before submitting or not
     */
    protected function assertFormHasValidationError($role, $url, $formSelector, array $formData, array $fieldNames, $disableValidation = true)
    {
        $client = $this->getClientForAuthenticatedUser($role);
        $crawler = $client->request('GET', $this->createUrl($url));
        $form = $crawler->filter($formSelector)->form();
        if ($disableValidation) {
            $form->disableValidation();
        }
        $result = $client->submit($form, $formData);

        $submittedForm = $result->filter($formSelector);
        $validationErrors = $submittedForm->filter('li.text-danger');

        self::assertEquals(
            count($fieldNames),
            count($validationErrors),
            sprintf('Expected %s validation errors, found %s', count($fieldNames), count($validationErrors))
        );

        foreach ($fieldNames as $name) {
            $field = $submittedForm->filter($name);
            self::assertNotNull($field, 'Could not find form field: ' . $name);
            $list = $field->nextAll();
            self::assertNotNull($list, 'Form field has no validation message: ' . $name);

            $validation = $list->filter('li.text-danger');
            if (count($validation) < 1) {
                // decorated form fields with icon have a different html structure, see kimai-theme.html.twig
                /** @var DOMElement $listMsg */
                $listMsg = $field->parents()->getNode(1);
                $classes = $listMsg->getAttribute('class');
                self::assertStringContainsString('has-error', $classes, 'Form field has no validation message: ' . $name);
            }
        }
    }

    /**
     * @param HttpKernelBrowser $client
     */
    protected function assertHasNoEntriesWithFilter(HttpKernelBrowser $client)
    {
        $this->assertCalloutWidgetWithMessage($client, 'No entries were found based on your selected filters.');
    }

    /**
     * @param HttpKernelBrowser $client
     * @param string $message
     */
    protected function assertCalloutWidgetWithMessage(HttpKernelBrowser $client, string $message)
    {
        $node = $client->getCrawler()->filter('div.callout.callout-warning.lead');
        self::assertStringContainsString($message, $node->text(null, true));
    }

    protected function assertHasFlashDeleteSuccess(HttpKernelBrowser $client)
    {
        $this->assertHasFlashSuccess($client, 'Entry was deleted');
    }

    /**
     * @param HttpKernelBrowser $client
     * @param string|null $message
     */
    protected function assertHasFlashSuccess(HttpKernelBrowser $client, string $message = null)
    {
        $node = $client->getCrawler()->filter('div.alert.alert-success.alert-dismissible');
        self::assertGreaterThan(0, $node->count(), 'Could not find flash success message');
        if (null !== $message) {
            self::assertStringContainsString($message, $node->text(null, true));
        }
    }

    protected function assertHasFlashSaveSuccess(HttpKernelBrowser $client)
    {
        $this->assertHasFlashSuccess($client, 'Saved changes');
    }

    /**
     * @param HttpKernelBrowser $client
     * @param string|null $message
     */
    protected function assertHasFlashError(HttpKernelBrowser $client, string $message = null)
    {
        $node = $client->getCrawler()->filter('div.alert.alert-error.alert-dismissible');
        self::assertGreaterThan(0, $node->count(), 'Could not find flash error message');
        if (null !== $message) {
            self::assertStringContainsString($message, $node->text(null, true));
        }
    }

    /**
     * @param HttpKernelBrowser $client
     * @param string $url
     */
    protected function assertIsRedirect(HttpKernelBrowser $client, $url = null)
    {
        self::assertTrue($client->getResponse()->isRedirect());
        if (null === $url) {
            return;
        }

        self::assertTrue($client->getResponse()->headers->has('Location'));
        self::assertStringEndsWith($url, $client->getResponse()->headers->get('Location'));
    }
}
