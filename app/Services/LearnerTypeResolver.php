<?php

namespace App\Services;

use App\Enums\LearnerUserType;

class LearnerTypeResolver
{
    public function resolveFromEmail(string $email): LearnerUserType
    {
        $domain = strtolower(strrchr($email, '@') ?: '');

        if ($domain === '') {
            return LearnerUserType::EXTERNAL;
        }

        $domain = ltrim($domain, '@');
        $employeeDomains = config('learner.employee_email_domains', []);

        foreach ($employeeDomains as $employeeDomain) {
            if ($domain === $employeeDomain) {
                return LearnerUserType::EMPLOYEE;
            }
        }

        return LearnerUserType::EXTERNAL;
    }
}
