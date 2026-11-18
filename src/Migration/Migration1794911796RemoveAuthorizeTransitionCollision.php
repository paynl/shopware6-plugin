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
        // Only the two transitions Pay. created are removed, and only if another transition with the
        // same action and source state really exists (the `clash` join), so foreign ones are kept.
        $connection->executeStatement(
            'DELETE smt FROM `state_machine_transition` smt
             INNER JOIN `state_machine` sm ON sm.`id` = smt.`state_machine_id`
             INNER JOIN `state_machine_state` from_state ON from_state.`id` = smt.`from_state_id`
             INNER JOIN `state_machine_state` to_state ON to_state.`id` = smt.`to_state_id`
             INNER JOIN `state_machine_transition` clash ON clash.`state_machine_id` = smt.`state_machine_id`
               AND clash.`from_state_id` = smt.`from_state_id`
               AND clash.`action_name` = smt.`action_name`
               AND clash.`to_state_id` <> smt.`to_state_id`
             WHERE sm.`technical_name` = :machine
               AND smt.`action_name` = :action
               AND to_state.`technical_name` = :paynlState
               AND from_state.`technical_name` IN (\'open\', \'in_progress\')',
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
