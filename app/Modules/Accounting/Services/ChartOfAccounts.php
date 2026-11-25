<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;

/**
 * Default bilingual chart of accounts for an automotive workshop / parts business.
 * system_key links business events to accounts for automatic posting.
 */
class ChartOfAccounts
{
    /** [code, name_ar, name_en, type, parent_code, system_key, is_group, cash_flow_category] */
    public const TEMPLATE = [
        ['1', 'الأصول', 'Assets', 'asset', null, null, true, null],
        ['11', 'الأصول المتداولة', 'Current Assets', 'asset', '1', null, true, null],
        ['1101', 'الصندوق الرئيسي', 'Main Cash', 'asset', '11', 'cash', false, null],
        ['1102', 'صندوق نقاط البيع', 'POS Cash Drawer', 'asset', '11', 'pos_cash', false, null],
        ['1103', 'البنك', 'Bank Account', 'asset', '11', 'bank', false, null],
        ['1104', 'مدفوعات البطاقات قيد التحصيل', 'Card Payments Clearing', 'asset', '11', 'card_clearing', false, null],
        ['1110', 'ذمم العملاء', 'Accounts Receivable', 'asset', '11', 'ar', false, 'operating'],
        ['1120', 'المخزون', 'Inventory', 'asset', '11', 'inventory', false, 'operating'],
        ['1125', 'أعمال تحت التنفيذ', 'Work in Progress', 'asset', '11', 'wip', false, 'operating'],
        ['1130', 'مركبات للتفكيك', 'Vehicles for Dismantling', 'asset', '11', 'dismantling', false, 'operating'],
        ['1140', 'ضريبة القيمة المضافة - مدخلات', 'VAT Input', 'asset', '11', 'vat_input', false, 'operating'],
        ['1150', 'سلف الموظفين', 'Employee Advances', 'asset', '11', 'employee_advances', false, 'operating'],
        ['1160', 'مصروفات مدفوعة مقدماً', 'Prepaid Expenses', 'asset', '11', 'prepaid', false, 'operating'],
        ['12', 'الأصول الثابتة', 'Fixed Assets', 'asset', '1', null, true, null],
        ['1201', 'المعدات والآلات', 'Machinery & Equipment', 'asset', '12', 'fixed_assets', false, 'investing'],
        ['1202', 'السيارات', 'Motor Vehicles', 'asset', '12', null, false, 'investing'],
        ['1203', 'الأثاث وأجهزة الحاسب', 'Furniture & Computers', 'asset', '12', null, false, 'investing'],
        ['1209', 'مجمع الإهلاك', 'Accumulated Depreciation', 'asset', '12', 'accumulated_depreciation', false, 'investing'],
        ['2', 'الخصوم', 'Liabilities', 'liability', null, null, true, null],
        ['21', 'الخصوم المتداولة', 'Current Liabilities', 'liability', '2', null, true, null],
        ['2101', 'ذمم الموردين', 'Accounts Payable', 'liability', '21', 'ap', false, 'operating'],
        ['2102', 'بضاعة مستلمة غير مفوترة', 'Goods Received Not Invoiced', 'liability', '21', 'grni', false, 'operating'],
        ['2110', 'ضريبة القيمة المضافة - مخرجات', 'VAT Output', 'liability', '21', 'vat_output', false, 'operating'],
        ['2120', 'رواتب مستحقة', 'Salaries Payable', 'liability', '21', 'salaries_payable', false, 'operating'],
        ['2130', 'دفعات مقدمة من العملاء', 'Customer Advances', 'liability', '21', 'customer_advances', false, 'operating'],
        ['2140', 'قروض قصيرة الأجل', 'Short-term Loans', 'liability', '21', null, false, 'financing'],
        ['3', 'حقوق الملكية', 'Equity', 'equity', null, null, true, null],
        ['3101', 'رأس المال', 'Capital', 'equity', '3', 'capital', false, 'financing'],
        ['3201', 'الأرباح المحتجزة', 'Retained Earnings', 'equity', '3', 'retained_earnings', false, 'financing'],
        ['3301', 'أرصدة افتتاحية', 'Opening Balance Equity', 'equity', '3', 'opening_equity', false, 'financing'],
        ['3401', 'جاري الشركاء', 'Owner Drawings', 'equity', '3', null, false, 'financing'],
        ['4', 'الإيرادات', 'Revenue', 'revenue', null, null, true, null],
        ['4101', 'مبيعات قطع الغيار', 'Spare Parts Sales', 'revenue', '4', 'sales_parts', false, 'operating'],
        ['4102', 'إيرادات خدمات الورشة', 'Workshop Service Revenue', 'revenue', '4', 'sales_services', false, 'operating'],
        ['4103', 'إيرادات أخرى', 'Other Revenue', 'revenue', '4', 'other_revenue', false, 'operating'],
        ['4104', 'إيرادات الخردة', 'Scrap Revenue', 'revenue', '4', 'scrap_revenue', false, 'operating'],
        ['4105', 'زيادة الصندوق', 'Cash Over', 'revenue', '4', 'cash_over', false, 'operating'],
        ['4106', 'أرباح تسويات المخزون', 'Inventory Adjustment Gain', 'revenue', '4', 'inventory_gain', false, 'operating'],
        ['4107', 'أرباح بيع أصول', 'Gain on Asset Disposal', 'revenue', '4', 'asset_disposal_gain', false, 'investing'],
        ['5', 'المصروفات', 'Expenses', 'expense', null, null, true, null],
        ['51', 'تكلفة المبيعات', 'Cost of Sales', 'expense', '5', null, true, null],
        ['5101', 'تكلفة قطع الغيار المباعة', 'Cost of Parts Sold', 'expense', '51', 'cogs', false, 'operating'],
        ['5102', 'تكلفة الضمان', 'Warranty Cost', 'expense', '51', 'warranty_expense', false, 'operating'],
        ['5103', 'فروق أسعار الشراء', 'Purchase Price Variance', 'expense', '51', 'price_variance', false, 'operating'],
        ['52', 'المصروفات التشغيلية', 'Operating Expenses', 'expense', '5', null, true, null],
        ['5201', 'الرواتب والأجور', 'Salaries & Wages', 'expense', '52', 'salaries_expense', false, 'operating'],
        ['5202', 'العمولات والحوافز', 'Commissions & Incentives', 'expense', '52', 'commission_expense', false, 'operating'],
        ['5203', 'الإيجار', 'Rent', 'expense', '52', null, false, 'operating'],
        ['5204', 'الكهرباء والمياه', 'Utilities', 'expense', '52', null, false, 'operating'],
        ['5205', 'مصروف الإهلاك', 'Depreciation Expense', 'expense', '52', 'depreciation_expense', false, 'operating'],
        ['5206', 'عجز الصندوق', 'Cash Short', 'expense', '52', 'cash_short', false, 'operating'],
        ['5207', 'خسائر وتلف المخزون', 'Inventory Loss & Damage', 'expense', '52', 'inventory_loss', false, 'operating'],
        ['5208', 'عمولات ومصروفات بنكية', 'Bank Charges', 'expense', '52', 'bank_charges', false, 'operating'],
        ['5209', 'مصروفات عامة وإدارية', 'General & Administrative', 'expense', '52', 'general_expense', false, 'operating'],
        ['5210', 'صيانة المعدات', 'Equipment Maintenance', 'expense', '52', 'equipment_maintenance', false, 'operating'],
        ['5211', 'خسائر بيع أصول', 'Loss on Asset Disposal', 'expense', '52', 'asset_disposal_loss', false, 'investing'],
        ['5212', 'مصروفات الشحن والنقل', 'Freight & Transport', 'expense', '52', null, false, 'operating'],
        ['5213', 'التسويق والإعلان', 'Marketing & Advertising', 'expense', '52', null, false, 'operating'],
    ];

    public static function install(): void
    {
        $byCode = [];
        foreach (self::TEMPLATE as [$code, $ar, $en, $type, $parent, $key, $group, $cf]) {
            $acc = Account::query()->firstOrCreate(['code' => $code], [
                'name_ar' => $ar,
                'name_en' => $en,
                'type' => $type,
                'normal_balance' => in_array($type, ['asset', 'expense'], true) && $key !== 'accumulated_depreciation' ? 'debit' : 'credit',
                'parent_id' => $parent ? ($byCode[$parent] ?? null) : null,
                'system_key' => $key,
                'is_group' => $group,
                'cash_flow_category' => $cf,
            ]);
            $byCode[$code] = $acc->id;
        }
    }
}
