<?php

namespace App\Observers;

use App\Jobs\GenerateEmployeeQrCode;
use App\Models\Employee;

class EmployeeObserver
{
    public function created(Employee $employee): void
    {
        GenerateEmployeeQrCode::dispatch($employee)->afterResponse();
    }
}
