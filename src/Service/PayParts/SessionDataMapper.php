<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Exceptions\PayPartsApiException;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Response\CreateSessionResponse;

class SessionDataMapper
{
    /** @throws PayPartsApiException */
    public function mapCreateSession(array $data): CreateSessionResponse
    {
        if (empty($data['sessionId']) || empty($data['sessionToken'])) {
            throw PayPartsApiException::sessionCreateFailed(
                'Missing sessionId or sessionToken in response'
            );
        }

        return new CreateSessionResponse(
            $data['sessionId'],
            $data['sessionToken']
        );
    }
}