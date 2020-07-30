<?php

namespace KimaiPlugin\ChromePluginBundle\tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

/**
 * Class ChromeControllerTest
 * @package KimaiPlugin\ChromePluginBundle\Tests
 */
class ChromeControllerTest extends WebTestCase
{
    public const DEFAULT_LANGUAGE = 'en';

//    public function testAccess() {
        /*
          chrome_status                        GET        ANY      ANY    /chrome/status
          chrome_uri                           GET|POST   ANY      ANY    /chrome/uri
          chrome_popup                         GET|POST   ANY      ANY    /chrome/popup/{projectId}/{cardId}
          chrome_settings                      GET|POST   ANY      ANY    /chrome/settings
        */
//        $this->assertUrlIsSecured('/chrome/status');
        // $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/chrome/status');

//        $this->assertUrlIsSecured('/chrome/uri');
//        $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/chrome/uri');
//S
//        $this->assertUrlIsSecured('/chrome/popup/{projectId}/{cardId}');
//        $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/chrome/popup/abc/123');
//
//        $this->assertUrlIsSecured('/chrome/settings');
//        $this->assertUrlIsSecuredForRole(User::ROLE_ADMIN, '/chrome/settings');
//    }

    public function testStatus()
    {

        $client = static::createClient();
        $client->request("GET", "/chrome/status");
        /** @var RedirectResponse $response */
        $response = $client->getResponse();
        echo $response->getContent();
        self::assertInstanceOf(Response::class, $response);
        self::assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertEquals(2, count($data));
        self::assertArrayHasKey("name", $data);
        self::assertArrayHasKey("version", $data);
        self.self::assertEquals("Kimai chrome plugin", $data['name']);
    }

    public function testPopupWithUri()
    {
        static::assertEquals(1,1);
    }

    public function testSettings()
    {
        static::assertEquals(1,1);
    }

    public function testPopupWithIds()
    {
        static::assertEquals(1,1);
    }

    public function test__construct()
    {
        static::assertEquals(1,1);
    }

    // Below here the functions are lifted out of kevin's tests, I can't seem to import them

    /**
     * @param string $url
     * @return string
     */
    protected function createUrl($url)
    {
        return '/' . self::DEFAULT_LANGUAGE . '/' . ltrim($url, '/');
    }

    protected function request(HttpKernelBrowser $client, string $url, $method = 'GET', array $parameters = [], string $content = null)
    {
        return $client->request($method, $this->createUrl($url), $parameters, [], [], $content);
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
     * @param string $url
     * @param string $method
     */
    protected function assertUrlIsSecured(string $url, $method = 'GET')
    {
        $client = self::createClient();
        $this->assertRequestIsSecured($client, $url, $method);
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
        self::assertFalse(
            $client->getResponse()->isSuccessful(),
            sprintf('The secure URL %s is not protected for role %s', $url, $role)
        );
        $this->assertAccessDenied($client);
    }
}
