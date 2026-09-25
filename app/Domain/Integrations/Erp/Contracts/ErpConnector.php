<?php

namespace App\Domain\Integrations\Erp\Contracts;

use App\Domain\Core\Models\Entity;

/**
 * Boundary to an entity's accounting/ERP system (Kenya: QuickBooks).
 * PACE records PO and payment references itself; a connector can later push
 * or pull them. Methods return null when the connector does nothing.
 */
interface ErpConnector
{
    /**
     * A short name for logs and the admin panel, e.g. "none" or "quickbooks-online".
     */
    public function name(): string;

    /**
     * Look up a vendor in the ERP by PACE vendor code; returns the ERP's id.
     */
    public function findVendor(Entity $entity, string $vendorCode): ?string;

    /**
     * Push an approved payment for posting; returns the ERP reference.
     *
     * @param  array<string, mixed>  $payment
     */
    public function postPayment(Entity $entity, array $payment): ?string;
}
