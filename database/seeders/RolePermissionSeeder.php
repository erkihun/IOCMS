<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds system roles, permissions and role→permission mappings (prompt §22).
 * These are reference data (not secrets). Permission codes follow resource.action.
 */
final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // IAM / governance
            'users.manage', 'roles.assign', 'audit.view', 'approvals.view', 'approvals.decide',
            // ADM
            'settings.view', 'settings.update', 'settings.approve', 'feature-flags.manage',
            'templates.manage', 'operations.maintenance',
            // Catalog / product / pricing
            'catalog.manage', 'products.create', 'products.approve', 'products.publish',
            'prices.manage', 'promotions.manage', 'search.merchandise',
            // Commerce
            'orders.view', 'orders.cancel', 'payments.reconcile', 'refunds.initiate', 'refunds.approve',
            // Fulfillment
            'inventory.pick_pack', 'inventory.adjust', 'inventory.adjust.approve',
            'shipments.dispatch', 'shipments.deliver', 'returns.approve', 'recall.manage',
            // Engagement / reporting
            'reviews.moderate', 'support.manage', 'notifications.manage', 'content.manage',
            'marketing.manage', 'reports.view', 'exports.restricted', 'imports.commit',
            // Marketplace (gated)
            'sellers.review', 'sellers.approve', 'commission.manage', 'payouts.approve', 'disputes.decide',
        ];

        $permIds = [];
        foreach ($permissions as $code) {
            [$resource, $action] = array_pad(explode('.', $code, 2), 2, 'manage');
            $permIds[$code] = Permission::query()->updateOrCreate(
                ['code' => $code],
                ['resource' => $resource, 'action' => $action, 'status' => 'active'],
            )->id;
        }

        $roles = [
            'super_admin' => ['scope' => 'global', 'perms' => $permissions],
            'catalog_manager' => ['scope' => 'global', 'perms' => ['catalog.manage', 'products.create', 'products.approve', 'products.publish', 'search.merchandise']],
            'pricing_marketing_manager' => ['scope' => 'global', 'perms' => ['prices.manage', 'promotions.manage', 'marketing.manage']],
            'warehouse_operator' => ['scope' => 'warehouse', 'perms' => ['inventory.pick_pack', 'inventory.adjust']],
            'fulfillment_supervisor' => ['scope' => 'warehouse', 'perms' => ['orders.view', 'shipments.dispatch', 'inventory.adjust.approve']],
            'delivery_agent' => ['scope' => 'delivery_zone', 'perms' => ['shipments.deliver']],
            'finance_officer' => ['scope' => 'global', 'perms' => ['payments.reconcile', 'refunds.initiate', 'reports.view', 'commission.manage']],
            'refund_approver' => ['scope' => 'global', 'perms' => ['refunds.approve']],
            'payout_approver' => ['scope' => 'global', 'perms' => ['payouts.approve']],
            'support_agent' => ['scope' => 'global', 'perms' => ['support.manage', 'orders.view', 'reviews.moderate']],
            'auditor' => ['scope' => 'global', 'perms' => ['audit.view', 'reports.view']],
            'security_compliance_officer' => ['scope' => 'global', 'perms' => ['audit.view', 'approvals.view', 'approvals.decide', 'users.manage']],
            'seller_admin' => ['scope' => 'seller', 'perms' => ['products.create', 'orders.view', 'inventory.pick_pack']],
        ];

        foreach ($roles as $code => $def) {
            $role = Role::query()->updateOrCreate(
                ['code' => $code],
                ['name' => str($code)->headline()->value(), 'scope_type' => $def['scope'], 'status' => 'active', 'system_owned' => true],
            );
            $role->permissions()->sync(array_values(array_intersect_key($permIds, array_flip($def['perms']))));
        }
    }
}
