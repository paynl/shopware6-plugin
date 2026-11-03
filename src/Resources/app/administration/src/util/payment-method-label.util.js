const SEPARATOR = ' | ';

/**
 * Puts the bank behind the payment method name: "Metjebank | Pay. payments" becomes
 * "Metjebank (ING) | Pay. payments".
 */
export default function formatPaymentMethodLabel(label = '', issuerName = null) {
    if (!issuerName || !label) {
        return label;
    }

    const separatorIndex = label.indexOf(SEPARATOR);
    if (separatorIndex === -1) {
        return `${label} (${issuerName})`;
    }

    return `${label.slice(0, separatorIndex)} (${issuerName})${label.slice(separatorIndex)}`;
}
