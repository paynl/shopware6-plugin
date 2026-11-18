/** Matches the order_transaction_capture_refund:update privilege required by the PAY. refund API route. */
export const REFUND_PRIVILEGE = 'order_refund.editor';

export const REFUNDABLE_STATUSES = ['paid', 'paid_partially', 'refunded_partially'];

export function hasRefundPrivilege(acl) {
    return acl.can(REFUND_PRIVILEGE);
}

export function isRefundDisabled(acl, transactionStatusName) {
    return !(hasRefundPrivilege(acl) && REFUNDABLE_STATUSES.includes(transactionStatusName));
}
