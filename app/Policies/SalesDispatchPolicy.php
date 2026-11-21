<?php

namespace App\Policies;

use App\Models\SalesDispatch;
use App\Models\User;

/**
 * Ver {@see CommercialPolicy}. La auxiliar de la Directora ve los despachos y lleva sus
 * facturas, pero no los crea, no los edita completos y no los borra.
 */
class SalesDispatchPolicy extends CommercialPolicy
{
    public function viewAny(User $user): bool
    {
        return parent::viewAny($user) || $user->checkPermissionTo('commercial-invoices.manage');
    }

    /** Cambiar número, fecha, valor y estado de la factura. */
    public function manageInvoice(User $user, SalesDispatch $dispatch): bool
    {
        return $user->is_super_admin
            || $user->checkPermissionTo('commercial.manage')
            || $user->checkPermissionTo('commercial-invoices.manage');
    }
}
