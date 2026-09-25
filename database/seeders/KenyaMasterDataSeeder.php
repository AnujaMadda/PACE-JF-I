<?php

namespace Database\Seeders;

use App\Domain\Core\Models\Entity;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Enums\GlAccountType;
use App\Domain\MasterData\Models\BoardPaper;
use App\Domain\MasterData\Models\BudgetCode;
use App\Domain\MasterData\Models\CapexCategory;
use App\Domain\MasterData\Models\CostCentre;
use App\Domain\MasterData\Models\Currency;
use App\Domain\MasterData\Models\Department;
use App\Domain\MasterData\Models\ExchangeRate;
use App\Domain\MasterData\Models\GlAccount;
use App\Domain\MasterData\Models\InternalOrder;
use App\Domain\MasterData\Models\PaymentTerm;
use App\Domain\MasterData\Models\ProfitCentre;
use App\Domain\MasterData\Models\Vendor;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * SAMPLE master data for Kenya so the system can be explored locally.
 * Codes, vendors and rates are illustrative, not JF&I's real data.
 * Local and test environments only.
 */
class KenyaMasterDataSeeder extends Seeder
{
    public function run(CurrentEntity $currentEntity): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample master data must not be loaded in production; import the real data instead.');
        }

        $kenya = Entity::query()->where('code', 'KE')->firstOrFail();

        $currentEntity->run($kenya, function (Entity $kenya): void {
            $user = fn (string $local) => User::query()->where('email', "{$local}@jfi.lk")->value('id');

            foreach (['USD', 'EUR', 'GBP', 'AED', 'CHF', 'CNY', 'INR', 'LKR', 'ZAR'] as $code) {
                Currency::query()->findOrFail($code)->entities()->syncWithoutDetaching([$kenya->getKey()]);
            }

            $departments = [
                'PROD' => ['Production', 'ke.approver'],
                'ENG' => ['Engineering', 'ke.approver'],
                'FIN' => ['Finance', 'ke.budget'],
                'PROC' => ['Procurement', 'ke.coordinator'],
                'IT' => ['Information Technology', 'ke.admin'],
                'HR' => ['Human Resources', null],
                'LOG' => ['Logistics & Warehousing', null],
                'QA' => ['Quality Assurance', null],
            ];
            foreach ($departments as $code => [$name, $head]) {
                Department::query()->updateOrCreate(['code' => $code], ['name' => $name, 'head_user_id' => $head ? $user($head) : null, 'is_active' => true]);
            }
            $dept = fn (string $code) => Department::query()->where('code', $code)->value('id');

            $costCentres = [
                'KE-CC-100' => ['Extrusion', 'PROD'], 'KE-CC-110' => ['Printing', 'PROD'], 'KE-CC-120' => ['Lamination & Slitting', 'PROD'],
                'KE-CC-200' => ['Maintenance', 'ENG'], 'KE-CC-210' => ['Utilities', 'ENG'], 'KE-CC-300' => ['Finance & Accounts', 'FIN'],
                'KE-CC-400' => ['Procurement', 'PROC'], 'KE-CC-500' => ['IT', 'IT'], 'KE-CC-600' => ['Warehouse', 'LOG'], 'KE-CC-700' => ['Quality Lab', 'QA'],
            ];
            foreach ($costCentres as $code => [$name, $d]) {
                CostCentre::query()->updateOrCreate(['code' => $code], ['name' => $name, 'department_id' => $dept($d), 'owner_user_id' => $user('ke.approver'), 'effective_from' => '2026-04-01', 'is_active' => true]);
            }

            foreach (['KE-PC-10' => 'Flexible Packaging', 'KE-PC-20' => 'Labels', 'KE-PC-30' => 'Rigid Packaging'] as $code => $name) {
                ProfitCentre::query()->updateOrCreate(['code' => $code], ['name' => $name, 'effective_from' => '2026-04-01', 'is_active' => true]);
            }

            $gl = [
                '150000' => ['Plant & Machinery', GlAccountType::Capex], '151000' => ['Buildings & Civil Works', GlAccountType::Capex],
                '152000' => ['Motor Vehicles', GlAccountType::Capex], '153000' => ['Computer Equipment', GlAccountType::Capex],
                '154000' => ['Furniture, Fixtures & Office Equipment', GlAccountType::Capex], '155000' => ['Tools & Moulds', GlAccountType::Capex],
                '159000' => ['Capital Work in Progress', GlAccountType::Capex],
                '610000' => ['Repairs & Maintenance', GlAccountType::Opex], '620000' => ['Consumables', GlAccountType::Opex],
                '630000' => ['Travel', GlAccountType::Opex], '640000' => ['Courier & Freight', GlAccountType::Opex],
            ];
            foreach ($gl as $code => [$name, $type]) {
                GlAccount::query()->updateOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true]);
            }
            $glId = fn (string $code) => GlAccount::query()->where('code', $code)->value('id');

            foreach (['CPX-PM' => ['Capex — Plant & Machinery', '150000'], 'CPX-BLD' => ['Capex — Buildings', '151000'], 'CPX-MV' => ['Capex — Vehicles', '152000'],
                'CPX-IT' => ['Capex — IT Equipment', '153000'], 'CPX-FF' => ['Capex — Furniture & Office', '154000'], 'CPX-TM' => ['Capex — Tools & Moulds', '155000']] as $code => [$name, $g]) {
                BudgetCode::query()->updateOrCreate(['code' => $code], ['name' => $name, 'gl_account_id' => $glId($g), 'is_active' => true]);
            }

            foreach (['PLANT' => ['Plant & Machinery', null], 'BLDG' => ['Buildings & Civil Works', null], 'VEH' => ['Motor Vehicles', null],
                'IT' => ['IT Equipment', null], 'FURN' => ['Furniture & Office Equipment', 2], 'TOOLS' => ['Tools & Moulds', null]] as $code => [$name, $min]) {
                CapexCategory::query()->updateOrCreate(['code' => $code], ['name' => $name, 'minimum_quotations' => $min, 'is_active' => true]);
            }

            InternalOrder::query()->updateOrCreate(['code' => 'IO-2027-001'], ['name' => 'Extrusion line 2 project', 'cost_centre_id' => CostCentre::query()->where('code', 'KE-CC-100')->value('id'), 'effective_from' => '2026-04-01', 'is_active' => true]);
            InternalOrder::query()->updateOrCreate(['code' => 'IO-2027-002'], ['name' => 'Solar rooftop installation', 'cost_centre_id' => CostCentre::query()->where('code', 'KE-CC-210')->value('id'), 'effective_from' => '2026-04-01', 'is_active' => true]);

            foreach (['IMMEDIATE' => ['Immediate', 0], 'NET15' => ['Net 15 days', 15], 'NET30' => ['Net 30 days', 30], 'NET60' => ['Net 60 days', 60], 'ADV50' => ['50% advance, balance on delivery', 0]] as $code => [$name, $days]) {
                PaymentTerm::query()->updateOrCreate(['code' => $code], ['name' => $name, 'days' => $days, 'is_active' => true]);
            }
            $term = fn (string $code) => PaymentTerm::query()->where('code', $code)->value('id');

            $vendors = [
                'V-00001' => ['Sample Industrial Supplies Ltd', 'KES', 'NET30', 'Equity Bank Kenya', '0240291234567'],
                'V-00002' => ['Sample Machinery (EA) Ltd', 'USD', 'ADV50', 'KCB Bank Kenya', '1188776655'],
                'V-00003' => ['Sample Printing Solutions GmbH', 'EUR', 'NET60', 'Deutsche Bank', 'DE89370400440532013000'],
                'V-00004' => ['Sample IT Distributors Kenya', 'KES', 'NET30', 'NCBA Bank Kenya', '7788990011'],
                'V-00005' => ['Sample Builders & Contractors', 'KES', 'NET15', 'Co-operative Bank of Kenya', '01129988776655'],
            ];
            foreach ($vendors as $code => [$name, $currency, $t, $bank, $account]) {
                Vendor::query()->updateOrCreate(['code' => $code], [
                    'name' => $name, 'currency_code' => $currency, 'payment_term_id' => $term($t), 'email' => 'accounts@'.str($name)->slug().'.example',
                    'bank_name' => $bank, 'bank_account_name' => $name, 'bank_account_number' => $account, 'is_active' => true,
                ]);
            }

            BoardPaper::query()->updateOrCreate(['code' => 'BP-2026-07'], [
                'name' => 'Second extrusion line (sample)', 'paper_date' => '2026-07-15', 'approved_amount' => '45000000.00', 'currency_code' => 'KES', 'is_active' => true,
            ]);

            // Sample rates only; maintain real rates in Admin → Exchange rates.
            foreach (['USD' => '129.250000', 'EUR' => '140.800000', 'GBP' => '165.400000', 'AED' => '35.190000', 'CHF' => '150.600000', 'CNY' => '18.050000', 'INR' => '1.470000', 'LKR' => '0.430000', 'ZAR' => '7.240000'] as $from => $rate) {
                ExchangeRate::query()->updateOrCreate(['from_currency' => $from, 'to_currency' => 'KES', 'effective_from' => '2026-09-01'], ['rate' => $rate, 'source' => 'Sample data', 'is_active' => true]);
            }
        });
    }
}
