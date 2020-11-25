<?php

namespace KimaiPlugin\ChromePluginBundle\Tests;

use App\DataFixtures\UserFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OldChromeController extends WebTestCase
{
/*
    public function testGetStatus()
    {
        $client = self::createClient();
        $client->request("GET", "/chrome/status");
        $response = $client->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertCount(2, $data);
        self::assertArrayHasKey("name", $data);
        self::assertArrayHasKey("version", $data);
        self::assertEquals("Kimai chrome plugin", $data['name']);
        self::assertEquals(1, 1);
    }

    public function testGetDataNoLogin()
    {
        $client = self::createClient();
        $client->request("GET", "/chrome/data");
        $response = $client->getResponse();
        self::assertInstanceOf(Response::class, $response);
        // Will redirect to login
        self::assertEquals(302, $response->getStatusCode());
    }

    public function testGetDataNoUri() {
        $client = self::createClient([], [
            'PHP_AUTH_USER' => UserFixtures::USERNAME_USER,
            'PHP_AUTH_PW' => UserFixtures::DEFAULT_PASSWORD,
        ]);
        $client->request("GET", "/chrome/data");
        $response = $client->getResponse();
        self::assertInstanceOf(Response::class, $response);
        // No URI will throw ex
        self::assertEquals(500, $response->getStatusCode());
    }

    public function testGetData() {
        $testUri = "https://github.com/tobybatch/ChromeKimaiBridge/issues/12";

        $client = self::createClient([], [
            'PHP_AUTH_USER' => UserFixtures::USERNAME_USER,
            'PHP_AUTH_PW' => UserFixtures::DEFAULT_PASSWORD,
        ]);
        $client->request("GET", "/chrome/data", [
            'uri' => $testUri
        ]);
        $response = $client->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertEquals($data['domain'],  parse_url($testUri, PHP_URL_HOST));

    }
    */
}
