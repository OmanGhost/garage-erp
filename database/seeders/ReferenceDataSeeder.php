<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Plan;
use App\Modules\Vehicles\Models\VehicleMake;
use Illuminate\Database\Seeder;

/**
 * Global reference data: SaaS plans and vehicle makes/models common in Oman.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $all = array_keys(config('erp.modules'));
        $plans = [
            ['code' => 'starter', 'name' => 'Starter', 'name_ar' => 'الأساسية', 'price_monthly' => 25, 'price_yearly' => 250, 'trial_days' => 14, 'max_users' => 5, 'max_branches' => 1, 'max_warehouses' => 2, 'max_storage_mb' => 2048,
                'modules' => ['core', 'crm', 'vehicles', 'workshop', 'inventory', 'sales', 'accounting', 'reports'], 'sort' => 1],
            ['code' => 'professional', 'name' => 'Professional', 'name_ar' => 'الاحترافية', 'price_monthly' => 60, 'price_yearly' => 600, 'trial_days' => 14, 'max_users' => 20, 'max_branches' => 3, 'max_warehouses' => 10, 'max_storage_mb' => 10240,
                'modules' => ['core', 'crm', 'vehicles', 'workshop', 'inventory', 'purchasing', 'sales', 'accounting', 'reports', 'hr', 'warranty', 'appointments', 'portal', 'insurance'], 'sort' => 2],
            ['code' => 'enterprise', 'name' => 'Enterprise', 'name_ar' => 'المؤسسات', 'price_monthly' => 150, 'price_yearly' => 1500, 'trial_days' => 30, 'max_users' => null, 'max_branches' => null, 'max_warehouses' => null, 'max_storage_mb' => null,
                'modules' => $all, 'sort' => 3],
        ];
        foreach ($plans as $p) {
            Plan::query()->updateOrCreate(['code' => $p['code']], $p + ['is_active' => true]);
        }

        $makes = [
            'Toyota|تويوتا|JP' => ['Land Cruiser', 'Land Cruiser Prado', 'Hilux', 'Camry', 'Corolla', 'Yaris', 'Fortuner', 'RAV4', 'Hiace', 'Coaster', 'Rush', 'Avalon', 'FJ Cruiser', 'Sequoia'],
            'Nissan|نيسان|JP' => ['Patrol', 'Sunny', 'Altima', 'Maxima', 'X-Trail', 'Navara', 'Urvan', 'Pathfinder', 'Kicks', 'Xterra'],
            'Mitsubishi|ميتسوبيشي|JP' => ['Pajero', 'L200', 'Lancer', 'Attrage', 'ASX', 'Outlander', 'Canter', 'Fuso'],
            'Lexus|لكزس|JP' => ['LX', 'GX', 'LS', 'ES', 'RX', 'IS', 'NX'],
            'Honda|هوندا|JP' => ['Civic', 'Accord', 'City', 'CR-V', 'Pilot', 'HR-V'],
            'Mazda|مازدا|JP' => ['Mazda3', 'Mazda6', 'CX-3', 'CX-5', 'CX-9', 'BT-50'],
            'Isuzu|ايسوزو|JP' => ['D-Max', 'MU-X', 'NPR', 'NQR', 'FVR'],
            'Suzuki|سوزوكي|JP' => ['Swift', 'Dzire', 'Ciaz', 'Jimny', 'Vitara', 'Ertiga', 'Carry'],
            'Hyundai|هيونداي|KR' => ['Accent', 'Elantra', 'Sonata', 'Tucson', 'Santa Fe', 'Creta', 'Palisade', 'H-1', 'H100'],
            'Kia|كيا|KR' => ['Picanto', 'Rio', 'Pegas', 'Cerato', 'K5', 'Sportage', 'Sorento', 'Seltos', 'Carnival'],
            'Ford|فورد|US' => ['Ranger', 'F-150', 'Explorer', 'Expedition', 'Edge', 'Mustang', 'Transit'],
            'Chevrolet|شيفروليه|US' => ['Tahoe', 'Suburban', 'Silverado', 'Malibu', 'Captiva', 'Traverse', 'Spark'],
            'GMC|جي إم سي|US' => ['Yukon', 'Sierra', 'Acadia', 'Terrain'],
            'Dodge|دودج|US' => ['Charger', 'Durango', 'Ram'],
            'Jeep|جيب|US' => ['Wrangler', 'Grand Cherokee', 'Cherokee', 'Compass'],
            'Mercedes-Benz|مرسيدس بنز|DE' => ['C-Class', 'E-Class', 'S-Class', 'G-Class', 'GLE', 'GLS', 'Sprinter', 'Actros'],
            'BMW|بي إم دبليو|DE' => ['3 Series', '5 Series', '7 Series', 'X3', 'X5', 'X7'],
            'Audi|أودي|DE' => ['A4', 'A6', 'A8', 'Q5', 'Q7'],
            'Volkswagen|فولكس فاجن|DE' => ['Golf', 'Passat', 'Jetta', 'Tiguan', 'Touareg', 'Teramont'],
            'Land Rover|لاند روفر|GB' => ['Range Rover', 'Range Rover Sport', 'Defender', 'Discovery', 'Evoque'],
            'MG|إم جي|CN' => ['MG5', 'MG6', 'ZS', 'RX5', 'HS', 'GT'],
            'Changan|شانجان|CN' => ['Alsvin', 'Eado', 'CS35', 'CS75', 'CS85', 'UNI-K'],
            'Geely|جيلي|CN' => ['Emgrand', 'Coolray', 'Tugella', 'Monjaro', 'Okavango'],
            'Haval|هافال|CN' => ['H6', 'Jolion', 'H9', 'Dargo'],
            'Chery|شيري|CN' => ['Tiggo 4', 'Tiggo 7', 'Tiggo 8', 'Arrizo 6'],
            'Tesla|تسلا|US' => ['Model 3', 'Model Y', 'Model S', 'Model X'],
            'BYD|بي واي دي|CN' => ['Atto 3', 'Han', 'Seal', 'Song Plus'],
            'Peugeot|بيجو|FR' => ['301', '2008', '3008', '5008', 'Partner'],
            'Renault|رينو|FR' => ['Symbol', 'Duster', 'Koleos', 'Megane'],
            'Volvo|فولفو|SE' => ['XC40', 'XC60', 'XC90', 'FH', 'FM'],
            'Scania|سكانيا|SE' => ['R-Series', 'G-Series', 'P-Series'],
            'MAN|مان|DE' => ['TGS', 'TGX', 'TGM'],
            'Hino|هينو|JP' => ['300 Series', '500 Series', '700 Series'],
            'Caterpillar|كاتربيلر|US' => ['Wheel Loader', 'Excavator', 'Grader', 'Backhoe'],
            'Komatsu|كوماتسو|JP' => ['Excavator', 'Wheel Loader', 'Bulldozer'],
            'JCB|جي سي بي|GB' => ['3CX', '4CX', 'Telehandler'],
            'Yamaha|ياماها|JP' => ['YZF-R', 'MT', 'XMAX'],
            'Harley-Davidson|هارلي ديفيدسون|US' => ['Sportster', 'Softail', 'Touring'],
        ];
        foreach ($makes as $key => $models) {
            [$name, $ar, $country] = explode('|', $key);
            $make = VehicleMake::query()->updateOrCreate(['name' => $name], ['name_ar' => $ar, 'country' => $country]);
            foreach ($models as $m) {
                $type = match (true) {
                    in_array($name, ['Caterpillar', 'Komatsu', 'JCB'], true) => 'heavy_equipment',
                    in_array($name, ['Yamaha', 'Harley-Davidson'], true) => 'motorcycle',
                    in_array($name, ['Scania', 'MAN', 'Hino', 'Volvo'], true) && ! str_starts_with($m, 'XC') => 'truck',
                    in_array($m, ['Hilux', 'L200', 'Navara', 'Ranger', 'D-Max', 'Silverado', 'Sierra', 'F-150', 'BT-50', 'Ram'], true) => 'pickup',
                    in_array($m, ['Hiace', 'Urvan', 'H-1', 'Transit', 'Sprinter', 'Carnival'], true) => 'van',
                    in_array($m, ['Coaster'], true) => 'bus',
                    in_array($m, ['Canter', 'Fuso', 'NPR', 'NQR', 'FVR', 'H100', 'Carry', 'Actros'], true) => 'commercial',
                    default => 'car',
                };
                $make->models()->updateOrCreate(['name' => $m], ['vehicle_type' => $type]);
            }
        }
    }
}
