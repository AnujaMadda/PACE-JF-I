<?php

namespace App\Domain\Integrations\Erp;

use App\Domain\Core\Models\Entity;
use App\Domain\Integrations\Erp\Contracts\ErpConnector;

/**
 * Default: no ERP integration. POs and payments are raised in the ERP by
 * people and their references entered in PACE.
 */
class NullErpConnector implements ErpConnector
{
    public function name(): string
    {
        return 'none';
    }

    public function findVendor(Entity $entity, string $vendorCode): ?string
    {
        return null;
    }

    public function postPayment(Entity $entity, array $payment): ?string
    {
        return null;
    }
}
