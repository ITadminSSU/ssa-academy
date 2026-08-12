<?php

namespace App\Support;

class RegistrationProfileOptions
{
    /**
     * @return list<string>
     */
    public static function estimatingSoftware(): array
    {
        return [
            'Bluebeam Revu',
            'PlanSwift',
            'On-Screen Takeoff (OST)',
            'STACK',
            'Procore',
            'Autodesk Build',
            'Excel',
            'None',
            'Others',
        ];
    }

    /**
     * @return list<string>
     */
    public static function constructionExperience(): array
    {
        return [
            'None',
            'Less than 1 year',
            '1–3 Years',
            '3–5 Years',
            '5–10 Years',
            '10+ Years',
        ];
    }
}
