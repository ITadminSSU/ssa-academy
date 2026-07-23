<?php

namespace App\Http\Requests\Concerns;

use Carbon\Carbon;

trait NormalizesLaunchAt
{
    protected function normalizeLaunchAtInput(): void
    {
        $launchAt = $this->input('launch_at');

        if (!$launchAt) {
            $this->merge(['launch_at' => null]);

            return;
        }

        $this->merge([
            'launch_at' => Carbon::parse($launchAt, config('app.timezone'))->format('Y-m-d H:i:s'),
        ]);
    }
}
