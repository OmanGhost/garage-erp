<?php

/*
 * Standard parts checklist shown when a vehicle is bought for dismantling. Each part has a stable key: it is
 * stored on the extracted part (dismantled_parts.template_key) so the factory part number (OEM) used for one
 * make / model is suggested automatically the next time the same vehicle is dismantled.
 */
return [
    'groups' => [
        'engine' => ['ar' => 'المحرك ونظام الدفع', 'en' => 'Engine & drivetrain', 'icon' => 'bi-gear-wide-connected', 'parts' => [
            'engine' => ['محرك كامل', 'Engine (complete)'],
            'cylinder_head' => ['رأس السلندر', 'Cylinder head'],
            'gearbox' => ['جير (ناقل الحركة)', 'Gearbox / transmission'],
            'turbo' => ['تيربو', 'Turbocharger'],
            'alternator' => ['دينمو', 'Alternator'],
            'starter' => ['سلف', 'Starter motor'],
            'ac_compressor' => ['كمبروسر مكيف', 'A/C compressor'],
            'radiator' => ['رديتر', 'Radiator'],
            'radiator_fan' => ['مروحة رديتر', 'Radiator fan'],
            'ecu' => ['كمبيوتر المحرك', 'Engine ECU'],
            'fuel_pump' => ['طرمبة بنزين', 'Fuel pump'],
            'injectors' => ['بخاخات', 'Injectors (set)'],
            'throttle_body' => ['بوابة هواء (ثروتل)', 'Throttle body'],
            'drive_shaft' => ['عكس (درايف شافت)', 'Drive shaft / axle'],
            'differential' => ['دفرنس', 'Differential'],
        ]],
        'body' => ['ar' => 'الهيكل الخارجي', 'en' => 'Body panels', 'icon' => 'bi-car-front', 'parts' => [
            'front_bumper' => ['صدام أمامي', 'Front bumper'],
            'rear_bumper' => ['صدام خلفي', 'Rear bumper'],
            'bonnet' => ['كبوت', 'Bonnet (hood)'],
            'tailgate' => ['شنطة / باب خلفي', 'Boot lid / tailgate'],
            'door_fl' => ['باب أمامي يسار', 'Front left door'],
            'door_fr' => ['باب أمامي يمين', 'Front right door'],
            'door_rl' => ['باب خلفي يسار', 'Rear left door'],
            'door_rr' => ['باب خلفي يمين', 'Rear right door'],
            'fender_l' => ['رفرف أمامي يسار', 'Front left fender'],
            'fender_r' => ['رفرف أمامي يمين', 'Front right fender'],
            'mirror_l' => ['مراية جانبية يسار', 'Left side mirror'],
            'mirror_r' => ['مراية جانبية يمين', 'Right side mirror'],
            'grille' => ['شبك أمامي', 'Front grille'],
        ]],
        'lights' => ['ar' => 'الإضاءة والزجاج', 'en' => 'Lights & glass', 'icon' => 'bi-lightbulb', 'parts' => [
            'headlight_l' => ['شمعة أمامية يسار', 'Left headlight'],
            'headlight_r' => ['شمعة أمامية يمين', 'Right headlight'],
            'taillight_l' => ['شمعة خلفية يسار', 'Left tail light'],
            'taillight_r' => ['شمعة خلفية يمين', 'Right tail light'],
            'fog_lights' => ['كشافات ضباب', 'Fog lights (pair)'],
            'windscreen' => ['زجاج أمامي', 'Windscreen'],
            'rear_glass' => ['زجاج خلفي', 'Rear glass'],
        ]],
        'interior' => ['ar' => 'الداخلية', 'en' => 'Interior', 'icon' => 'bi-person-workspace', 'parts' => [
            'dashboard' => ['طبلون', 'Dashboard'],
            'cluster' => ['عداد السرعة (كلستر)', 'Instrument cluster'],
            'steering_wheel' => ['سكان (مقود)', 'Steering wheel'],
            'airbag_module' => ['كمبيوتر الإيرباق', 'Airbag module'],
            'front_seats' => ['مقاعد أمامية', 'Front seats'],
            'rear_seats' => ['مقاعد خلفية', 'Rear seats'],
            'head_unit' => ['شاشة / مسجل', 'Radio / infotainment'],
            'ac_panel' => ['لوحة تحكم المكيف', 'A/C control panel'],
        ]],
        'chassis' => ['ar' => 'التعليق والفرامل والعجلات', 'en' => 'Suspension, brakes & wheels', 'icon' => 'bi-circle', 'parts' => [
            'shocks_front' => ['مساعدات أمامية', 'Front shock absorbers'],
            'shocks_rear' => ['مساعدات خلفية', 'Rear shock absorbers'],
            'calipers' => ['كاليبرات فرامل', 'Brake calipers'],
            'abs_unit' => ['وحدة ABS', 'ABS unit'],
            'steering_rack' => ['علبة دركسون', 'Steering rack'],
            'control_arms' => ['مقصات', 'Control arms'],
            'wheels' => ['جنوط (طقم)', 'Alloy wheels (set)'],
        ]],
        'electrical' => ['ar' => 'الكهرباء', 'en' => 'Electrical', 'icon' => 'bi-lightning-charge', 'parts' => [
            'battery' => ['بطارية', 'Battery'],
            'wiring_harness' => ['ضفيرة أسلاك', 'Wiring harness'],
            'fuse_box' => ['علبة فيوزات', 'Fuse box'],
            'window_motors' => ['مواتير نوافذ', 'Window motors'],
            'bcm' => ['كمبيوتر الهيكل (BCM)', 'Body control module'],
        ]],
    ],
];
