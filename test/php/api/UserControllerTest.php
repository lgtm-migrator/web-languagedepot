<?php

use Api\UserController;
use Symfony\Component\HttpFoundation\Request;

//use PHPUnit_Framework_TestCase;

TestEnvironment::ensureDatabaseConfigured();

class UserControllerTest extends PHPUnit_Framework_TestCase
{
    public function testUsernameIsAvailable_UsernameDoesntExist_True() {
        $nonexistentUsername = 'ran4domuser6543';
        $controller = new UserController();

        $result = $controller->usernameIsAvailable($nonexistentUsername);
        $result = json_decode($result);

        $this->assertTrue($result == true);
    }

    public function testUsernameIsAvailable_UsernameExist_False() {
        $existingUsername = 'test';
        $controller = new UserController();

        $result = $controller->usernameIsAvailable($existingUsername);
        $result = json_decode($result);

        $this->assertTrue($result == false);
    }

    public function testUsernameIsAvailable_LowerUsernameExist_False() {
        $existingUsername = 'tEst';
        $controller = new UserController();

        $result = $controller->usernameIsAvailable($existingUsername);
        $result = json_decode($result);

        $this->assertTrue($result == false);
    }

    public function testGetProjectAccess_UnknownUser_UserUnknown()
    {
        $controller = new UserController();
        $unknownUsername = 'notauser';
        $request = new Request(array(),
            array('password' => 'bogus_password'),
            array(), array(), array(), array(), array());

        $response = $controller->getProjectsAccess($unknownUsername, $request);
        $result = $response->getContent();
        $result = json_decode($result);

        $this->assertEquals('Unknown user', $result->error);
    }

    public function testGetProjectAccess_InvalidPassword_BadPassword()
    {
        $controller = new UserController();
        $existingUsername = 'test';
        $request = new Request(array(),
            array('password' => 'bogus_password'),
            array(), array(), array(), array(), array());

        $response = $controller->getProjectsAccess($existingUsername, $request);
        $result = $response->getContent();
        $result = json_decode($result);

        $this->assertEquals('Bad password', $result->error);
    }

    public function testGetProjectAccess_ValidUser_Ok()
    {
        $controller = new UserController();
        $existingUsername = 'test';
        $request = new Request(array(),
            array('password' => 'tset23'),
            array(), array(), array(), array(), array());

        $response = $controller->getProjectsAccess($existingUsername, $request);
        $result = $response->getContent();
        $result = json_decode($result);

        $expected0 = new \stdclass;
        $expected0->identifier = 'test-ld-dictionary';
        $expected0->name = 'LD Test Dictionary';
        $expected0->repository = 'http://public.languagedepot.org';
        $expected0->role = 'contributor';
        $expected1 = new \stdclass;
        $expected1->identifier = 'test-ld-flex';
        $expected1->name = 'LD API Test Flex';
        $expected1->repository = 'http://public.languagedepot.org';
        $expected1->role = 'manager';
        $expected2 = new \stdclass;
        $expected2->identifier = 'test-ld-demo';
        $expected2->name = 'LD API Test Demo';
        $expected2->repository = 'http://public.languagedepot.org';
        $expected2->role = 'unknown';

        $expected = array($expected0, $expected1, $expected2);
        $this->assertEquals($expected, $result);
    }

    public function testGetProjectAccess_RoleIsManager_Ok()
    {
        $controller = new UserController();
        $existingUsername = 'test';
        $request = new Request(array(),
            array('password' => 'tset23',
                  'role' => 'manager'),
            array(), array(), array(), array(), array());

        $response = $controller->getProjectsAccess($existingUsername, $request);
        $result = $response->getContent();
        $result = json_decode($result);

        $expected0 = new \stdclass;
        $expected0->identifier = 'test-ld-flex';
        $expected0->name = 'LD API Test Flex';
        $expected0->repository = 'http://public.languagedepot.org';
        $expected0->role = 'manager';
        $expected = array($expected0);
        $this->assertEquals($expected, $result);
    }

    public function testGetProjectAccess_RoleIsContributor_Ok()
    {
        $controller = new UserController();
        $existingUsername = 'test';
        $request = new Request(array(),
            array('password' => 'tset23',
                'role' => 'contributor'),
            array(), array(), array(), array(), array());

        $response = $controller->getProjectsAccess($existingUsername, $request);
        $result = $response->getContent();
        $result = json_decode($result);

        $expected0 = new \stdclass;
        $expected0->identifier = 'test-ld-dictionary';
        $expected0->name = 'LD Test Dictionary';
        $expected0->repository = 'http://public.languagedepot.org';
        $expected0->role = 'contributor';
        $expected = array($expected0);
        $this->assertEquals($expected, $result);
    }

    public function testGetProjectAccess_RoleIsLanguageDepotProgrammer_Empty()
    {
        $controller = new UserController();
        $existingUsername = 'test';
        $request = new Request(array(),
            array('password' => 'tset23',
                'role' => 'LanguageDepotProgrammer'),
            array(), array(), array(), array(), array());

        $response = $controller->getProjectsAccess($existingUsername, $request);
        $result = $response->getContent();
        $result = json_decode($result);

        // TODO: Add back when getProjectsAccess handles LanguageDepotProgrammer
        // $expected0 = new \stdclass;
        // $expected0->identifier = 'test-ld-demo';
        // $expected0->name = 'LD Test Demo';
        // $expected0->repository = 'http://public.languagedepot.org';
        // $expected0->role = 'LanguageDepotProgrammer';
        // $expected = array($expected0);

        // Currently, UserController:getProjectsAccess only returns manager and contributor roles
        $this->assertEmpty($result);
    }

    public function testGetProjectAccess_AnyRole_NonManagerContributor_Unknown()
    {
        $controller = new UserController();
        $existingUsername = 'test';
        $request = new Request(array(),
            array('password' => 'tset23',
                'role' => 'any'),
            array(), array(), array(), array(), array());

        $response = $controller->getProjectsAccess($existingUsername, $request);
        $result = $response->getContent();
        $result = json_decode($result);

        $expected2 = new \stdclass;
        $expected2->identifier = 'test-ld-demo';
        $expected2->name = 'LD API Test Demo';
        $expected2->repository = 'http://public.languagedepot.org';
        $expected2->role = 'unknown';

        $this->assertEquals($result[2], $expected2);
    }

    public function testCreate_NewUser_Ok() {
        $client = ApiTestEnvironment::client();

        $nonexistentEmail = 'newuser@example.com';

        $response = $client->post(ApiTestEnvironment::url().'/api/users', array(
            'headers' => ApiTestEnvironment::headers(),
            'exceptions' => false,
            'body' => array(
                'plainPassword' => 'password',
                'mail' => $nonexistentEmail)
        ));

        $this->assertEquals('200', $response->getStatusCode());
        $result = $response->getBody();
        $result = json_decode($result);

        $expected0 = new \stdclass;
        $expected0->mail = $nonexistentEmail;
        $expected0->login = $nonexistentEmail;

        $this->assertEquals(array($expected0), $result);
    }

    /**
     * Verifies check of lowercased email address generates error
     * @depends testCreate_NewUser_Ok
     */
    public function testCreate_NewUserAgain_Error()
    {
        $controller = new UserController();
        $existingUsername = 'test';
        $existingMail = 'Newuser@example.com';
        $request = new Request(array(),
            array('mail' => $existingUsername),
            array(), array(), array(), array(), array());

        $response = $controller->create($request);
        $result = $response->getContent();
        $result = json_decode($result);

        $this->assertEquals( 'Login has already been taken', $result->error);

        $request = new Request(array(),
            array('mail' => $existingMail),
            array(), array(), array(), array(), array());

        $response = $controller->create($request);
        $result = $response->getContent();
        $result = json_decode($result);

        $this->assertEquals( 'Email has already been taken', $result->error);
    }

    public function testCreate_InvalidMail_Error()
    {
        $controller = new UserController();
        $invalidMail = 'notanemailaddress';
        $request = new Request(array(),
            array('mail' => $invalidMail),
            array(), array(), array(), array(), array());

        $response = $controller->create($request);
        $result = $response->getContent();
        $result = json_decode($result);

        $this->assertEquals('Invalid email address', $result->error);
    }
}
