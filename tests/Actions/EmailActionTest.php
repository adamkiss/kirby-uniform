<?php

namespace Uniform\Tests\Actions;

use Exception;
use Uniform\Form;
use Uniform\Tests\TestCase;
use Uniform\Actions\EmailAction;
use Uniform\Exceptions\PerformerException;

class EmailActionTest extends TestCase
{
    protected $form;
    public function setUp()
    {
        $this->form = new Form;
    }

    public function testSenderOptionRequired()
    {
        $action = new EmailActionStub($this->form, ['to' => 'mail']);
        $this->setExpectedException(Exception::class);
        $action->perform();
    }

    public function testToOptionRequired()
    {
        $action = new EmailActionStub($this->form, ['sender' => 'mail']);
        $this->setExpectedException(Exception::class);
        $action->perform();
    }

    public function testPerform()
    {
        $this->form->data('_from', 'joe@user.com');
        $action = new EmailActionStub($this->form, [
            'to' => 'jane@user.com',
            'sender' => 'info@user.com',
        ]);
        $action->perform();
        $expected = [
            'service' => 'mail',
            'options' => [],
            'to' => 'jane@user.com',
            'from' => 'info@user.com',
            'replyTo' => 'joe@user.com',
            'subject' => '',
            'body' => '',
        ];
        $this->assertEquals($expected, $action->params);
    }

    public function testFail()
    {
        $action = new EmailActionStub($this->form, [
            'to' => 'jane@user.com',
            'sender' => 'info@user.com',
        ]);
        $action->shouldFail = true;
        $this->setExpectedException(PerformerException::class);
        $action->perform();
    }

    public function testReplyTo()
    {
        $action = new EmailActionStub($this->form, [
            'to' => 'jane@user.com',
            'sender' => 'info@user.com',
            'replyTo' => 'joe@user.com',
        ]);
        $action->perform();
        $this->assertEquals('joe@user.com', $action->params['replyTo']);
    }

    public function testService()
    {
         $action = new EmailActionStub($this->form, [
            'to' => 'jane@user.com',
            'sender' => 'info@user.com',
            'service' => 'aws',
            'service-options' => ['someoptions'],
        ]);
        $action->perform();
        $this->assertEquals('aws', $action->params['service']);
        $this->assertEquals(['someoptions'], $action->params['options']);
    }

    public function testSubjectTemplate()
    {
        $this->form->data('_from', "joe@user.com\n\n");
        $this->form->data('data', ['somedata']);
        $action = new EmailActionStub($this->form, [
            'to' => 'jane@user.com',
            'sender' => 'info@user.com',
            'subject' => 'Message from {_from} with {data}',
        ]);
        $action->perform();
        $this->assertEquals('Message from joe@user.com with {data}', $action->params['subject']);
    }

    public function testBody()
    {
        $this->form->data('_from', 'joe@user.com');
        $this->form->data('message', 'hello');
        $this->form->data('data', ['some', 'data']);
        $action = new EmailActionStub($this->form, [
            'to' => 'jane@user.com',
            'sender' => 'info@user.com',
        ]);
        $action->perform();
        $expect = "Message: hello\n\nData: some, data\n\n";
        $this->assertEquals($expect, $action->params['body']);
    }

    public function testBodySnippet()
    {
        $action = new EmailActionStub($this->form, [
            'to' => 'jane@user.com',
            'sender' => 'info@user.com',
            'snippet' => 'my snippet',
        ]);
        $action->perform();
        $this->assertEquals('my snippet', $action->params['body']);
    }

    public function testReceiveCopy()
    {
        $this->form->data('_from', 'joe@user.com');
        $action = new EmailActionStub($this->form, [
            'to' => 'jane@user.com',
            'sender' => 'info@user.com',
            'receive-copy' => true,
        ]);
        $action->perform();
        $this->assertEquals(1, $action->calls);
        $this->form->data('_receive_copy', '1');
        $action->perform();
        $this->assertEquals(3, $action->calls);
        $this->assertEquals('joe@user.com', $action->params['to']);
    }
}

class EmailActionStub extends EmailAction
{
    public $calls = 0;
    protected function sendEmail($params)
    {
        $this->calls++;
        $this->params = $params;
        return !isset($this->shouldFail);
    }

    protected function getSnippet($name, $data)
    {
        if (!array_key_exists('data', $data) || !array_key_exists('options', $data)) {
            throw new Exception;
        }

        return $name;
    }
}
