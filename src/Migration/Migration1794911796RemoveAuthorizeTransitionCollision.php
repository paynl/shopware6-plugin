<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1794911796RemoveAuthorizeTransitionCollision extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794911796;
    }

    public function update(Connection $connection): void
    {
        // Core's `authorize` action already leads open/in_progress -> `authorized`; our duplicate
        // `authorize` -> Pay. `authorize` transitions made core pick one at random (GitHub #464).
        $connection->executeStatement(
            'DELETE smt FROM `state_machine_transition` smt
             INNER JOIN `state_machine` sm ON sm.`id` = smt.`state_machine_id`
             INNER JOIN `state_machine_state` to_state ON to_state.`id` = smt.`to_state_id`
             WHERE sm.`technical_name` = :machine
               AND smt.`action_name` = :action
               AND to_state.`technical_name` = :paynlState',
            [
                'machine' => 'order_transaction.state',
                'action' => 'authorize',
                'paynlState' => 'authorize',
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
