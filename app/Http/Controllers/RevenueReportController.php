<?php

namespace App\Http\Controllers;

use App\Services\BuildRevenueReport;
use Common\Core\BaseController;

class RevenueReportController extends BaseController
{
    public function __invoke(BuildRevenueReport $service)
    {
        return $this->success($service->execute(request()->all()));
    }
}
