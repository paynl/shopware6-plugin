<?php declare(strict_types=1);

namespace PaynlPayment\Tests\Helper;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use PaynlPayment\Shopware6\Helper\PayStatusChange;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Shopware\Core\Framework\Context;
use PaynlPayment\Shopware6\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepositoryInterface;
use PaynlPayment\Shopware6\Repository\StateMachineState\StateMachineStateRepositoryInterface;
use PaynlPayment\Shopware6\Repository\StateMachineTransition\StateMachineTransitionRepositoryInterface;
use PaynlPayment\Shopware6\Service\Order\OrderStatusUpdater;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class PayStatusChangeTest extends TestCase
{
    public function testShouldMapVerifiedAndAuthorizedPaidAndCancelOntoOneTable(): void
    {
        $paidWhileVerified = PayStatusChange::decide(
            StateMachineTransitionActions::ACTION_PAID,
            true,
            false,
            PaynlTransactionStatusesEnum::STATUS_VERIFY
        );
        self::assertNotNull($paidWhileVerified);
        self::assertSame(PayStatusChange::APPROVE, $paidWhileVerified->remoteCall);
        self::assertSame(PaynlTransactionStatusesEnum::STATUS_PAID, $paidWhileVerified->localStateId);

        $cancelWhileVerified = PayStatusChange::decide(
            StateMachineTransitionActions::ACTION_CANCEL,
            true,
            false,
            PaynlTransactionStatusesEnum::STATUS_VERIFY
        );
        self::assertNotNull($cancelWhileVerified);
        self::assertSame(PayStatusChange::DECLINE, $cancelWhileVerified->remoteCall);
        self::assertSame(PaynlTransactionStatusesEnum::STATUS_CANCEL, $cancelWhileVerified->localStateId);

        $paidWhileAuthorized = PayStatusChange::decide(
            StateMachineTransitionActions::ACTION_PAID,
            false,
            true,
            PaynlTransactionStatusesEnum::STATUS_AUTHORIZE
        );
        self::assertNotNull($paidWhileAuthorized);
        self::assertSame(PayStatusChange::CAPTURE, $paidWhileAuthorized->remoteCall);
        self::assertSame(PaynlTransactionStatusesEnum::STATUS_PAID, $paidWhileAuthorized->localStateId);

        $cancelWhileAuthorized = PayStatusChange::decide(
            StateMachineTransitionActions::ACTION_CANCEL,
            false,
            true,
            PaynlTransactionStatusesEnum::STATUS_AUTHORIZE
        );
        self::assertNotNull($cancelWhileAuthorized);
        self::assertSame(PayStatusChange::VOID, $cancelWhileAuthorized->remoteCall);
        self::assertSame(PaynlTransactionStatusesEnum::STATUS_CANCEL, $cancelWhileAuthorized->localStateId);
    }

    public function testShouldWriteTheLocalRowWhenPayIsAlreadyInTheTargetStatus(): void
    {
        $alreadyPaid = PayStatusChange::decide(
            StateMachineTransitionActions::ACTION_PAID,
            false,
            false,
            PaynlTransactionStatusesEnum::STATUS_PAID
        );
        self::assertNotNull($alreadyPaid);
        self::assertNull($alreadyPaid->remoteCall);
        self::assertSame(PaynlTransactionStatusesEnum::STATUS_PAID, $alreadyPaid->localStateId);

        $alreadyCancelled = PayStatusChange::decide(
            StateMachineTransitionActions::ACTION_CANCEL,
            false,
            false,
            PaynlTransactionStatusesEnum::STATUS_CANCEL
        );
        self::assertNotNull($alreadyCancelled);
        self::assertNull($alreadyCancelled->remoteCall);
        self::assertSame(PaynlTransactionStatusesEnum::STATUS_CANCEL, $alreadyCancelled->localStateId);
    }

    public function testShouldIgnoreAnActionThatIsNeitherPaidNorCancel(): void
    {
        self::assertNull(PayStatusChange::decide(
            StateMachineTransitionActions::ACTION_REFUND,
            true,
            true,
            PaynlTransactionStatusesEnum::STATUS_AUTHORIZE
        ));
        self::assertNull(PayStatusChange::localStateId(StateMachineTransitionActions::ACTION_REFUND));
    }

    public function testShouldRethrowWhenThePaynlTransactionUpdateFails(): void
    {
        $repository = $this->createMock(PaynlTransactionsRepositoryInterface::class);
        $repository->expects(self::once())->method('update')->willThrowException(new \RuntimeException('row update failed'));

        $transaction = $this->createMock(PaynlTransactionEntity::class);
        $transaction->method('getId')->willReturn('paynl-row-id');
        $transaction->method('getPaynlTransactionId')->willReturn('PAY-ORDER');

        $payOrder = $this->createMock(\PayNL\Sdk\Model\Pay\PayOrder::class);
        $payOrder->method('getStatusCode')->willReturn(PaynlTransactionStatusesEnum::STATUS_PAID);

        $helper = new ProcessingHelper(
            $this->createMock(Config::class),
            $this->createMock(Api::class),
            $this->createMock(LoggerInterface::class),
            $repository,
            $this->createMock(OrderTransactionRepositoryInterface::class),
            $this->createMock(StateMachineStateRepositoryInterface::class),
            $this->createMock(StateMachineTransitionRepositoryInterface::class),
            $this->createMock(StateMachineRegistry::class),
            $this->createMock(OrderStatusUpdater::class)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('row update failed');
        $helper->applyPayNLStatusChange(
            $transaction,
            StateMachineTransitionActions::ACTION_PAID,
            $payOrder,
            'sales-channel-id',
            $this->createMock(Context::class)
        );
    }
}
