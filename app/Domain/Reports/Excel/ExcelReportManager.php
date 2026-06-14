<?php

namespace App\Domain\Reports\Excel;

use App\Domain\Reports\Enums\ExcelReportType;
use App\Jobs\GenerateDowntimeExcelJob;
use App\Jobs\GenerateInventoryExcelJob;
use App\Jobs\GenerateMaintenancePlansExcelJob;
use App\Jobs\GenerateReliabilityExcelJob;
use App\Jobs\GenerateWorkOrdersExcelJob;

class ExcelReportManager
{
    public function dispatch(ExcelReportType $type, string $tenantId, string $requestedBy): void
    {
        match ($type) {
            ExcelReportType::Inventory => GenerateInventoryExcelJob::dispatch($tenantId, $requestedBy),
            ExcelReportType::Reliability => GenerateReliabilityExcelJob::dispatch($tenantId, $requestedBy),
            ExcelReportType::WorkOrders => GenerateWorkOrdersExcelJob::dispatch($tenantId, $requestedBy),
            ExcelReportType::DowntimeEvents => GenerateDowntimeExcelJob::dispatch($tenantId, $requestedBy),
            ExcelReportType::MaintenancePlans => GenerateMaintenancePlansExcelJob::dispatch($tenantId, $requestedBy),
        };
    }
}
