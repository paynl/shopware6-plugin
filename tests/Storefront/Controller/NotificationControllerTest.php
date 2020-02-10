<?php

namespace PaynlPayment\Tests\Storefront\Controller;

use PaynlPayment\Helper\ProcessingHelper;
use PaynlPayment\Storefront\Controller\NotificationController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class NotificationControllerTest extends TestCase
{
    /**
     * @test
     */
    public function checkNotifyMethod() {
        $notificationController = new NotificationController($this->getProcessingHelperMock());
        $notificationController->notify($this->getRequestMock());

        $this->assertTrue(true);
    }

    private function getRequestMock()
    {
        $requestMock = \Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
            ->andReturn('paid');

        return $requestMock;
    }

    private function getProcessingHelperMock()
    {
        $processingHelperMock = \Mockery::mock(ProcessingHelper::class);
        $processingHelperMock->shouldReceive('processNotify')
            ->andReturn('');

        return $processingHelperMock;
    }
}
