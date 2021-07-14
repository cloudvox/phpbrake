<?php

namespace Airbrake\Tests;

use PHPUnit_Framework_TestCase;

class NotifyTest extends PHPUnit_Framework_TestCase
{
    private $notifier;

    public function setUp()
    {
        $this->notifier = new NotifierMock(array(
            'projectId' => 1,
            'projectKey' => 'api_key',
        ));
        $_SERVER['HTTP_HOST'] = 'airbrake.io';
        $_SERVER['REQUEST_URI'] = '/hello';
        $id = $this->notifier->notify(new \Exception('hello'));
        $this->assertEquals($id, '12345');
    }

    public function testPostsToURL()
    {
        $this->assertEquals(
            $this->notifier->url,
            'https://static.airbrake.io/api/v3/projects/1/notices?key=api_key'
        );
    }

    public function testPostsError()
    {
        $notice = $this->notifier->notice;
        $error = $notice['errors'][0];
        $this->assertEquals($error['type'], 'Exception');
        $this->assertEquals($error['message'], 'hello');
    }

    public function testPostsBacktrace()
    {
        $backtrace = $this->notifier->notice['errors'][0]['backtrace'];
        // Note: The following assertion is specific to PHPUnit 4.8.35
        $wanted = array(array(
            'file' => dirname(dirname(__FILE__)) . '/vendor/phpunit/phpunit/src/Framework/TestCase.php',
            'line' => 764,
            'function' => 'Airbrake\Tests\NotifyTest->setUp',
        ));
        for ($i = 0; $i < count($wanted); $i++) {
            $this->assertEquals($wanted[$i], $backtrace[$i]);
        }
    }

    public function testPostsURL()
    {
        $this->assertEquals(
            $this->notifier->notice['context']['url'],
            'http://airbrake.io/hello'
        );
    }
}

class FilterReturnsNullTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->notifier = new NotifierMock(array(
            'projectId' => 1,
            'projectKey' => 'api_key',
        ));
        $this->notifier->addFilter(function() {
            return null;
        });
        $this->notifier->notify(new \Exception('hello'));
    }

    public function testNoticeIsIgnored()
    {
        $this->assertNull($this->notifier->notice);
    }
}

class FilterReturnsFalseTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->notifier = new NotifierMock(array(
            'projectId' => 1,
            'projectKey' => 'api_key',
        ));
        $this->notifier->addFilter(function() {
            return false;
        });
        $this->notifier->notify(new \Exception('hello'));
    }

    public function testNoticeIsIgnored()
    {
        $this->assertNull($this->notifier->notice);
    }
}

class ModificationTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->notifier = new NotifierMock(array(
            'projectId' => 1,
            'projectKey' => 'api_key',
        ));
        $this->notifier->addFilter(function() {
            $notice['context']['environment'] = 'production';
            unset($notice['environment']);
            return $notice;
        });
        $this->notifier->notify(new \Exception('hello'));
    }

    public function testNoticeIsModified()
    {
        $notice = $this->notifier->notice;
        $this->assertEquals($notice['context']['environment'], 'production');
    }

    public function testEnvironmentIsUnset()
    {
        $notice = $this->notifier->notice;
        $this->assertFalse(isset($notice['environment']));
    }
}
