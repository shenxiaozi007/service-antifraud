<?php

namespace App\Http\Controllers\Management\Proxy;

use App\Kernel\Base\BaseController;
use App\Modules\Management\Business\AdminBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends BaseController
{
    /**
     * @param Request $request 当前 HTTP 请求实例
     * @param AdminBusiness $business 管理端业务服务
     */
    public function __construct(protected Request $request, protected AdminBusiness $business)
    {
    }

    /**
     * 管理端用户列表。
     *
     * @return JsonResponse
     */
    public function users(): JsonResponse
    {
        return $this->revert($this->business->users($this->request->all()));
    }

    /**
     * 管理端分析记录列表。
     *
     * @return JsonResponse
     */
    public function records(): JsonResponse
    {
        return $this->revert($this->business->records($this->request->all()));
    }

    /**
     * 管理端分析记录详情。
     *
     * @param int $recordId 分析记录 ID
     * @return JsonResponse
     */
    public function recordDetail(int $recordId): JsonResponse
    {
        return $this->revert($this->business->recordDetail($recordId));
    }

    /**
     * 管理端文件列表。
     *
     * @return JsonResponse
     */
    public function files(): JsonResponse
    {
        return $this->revert($this->business->files($this->request->all()));
    }

    /**
     * 管理端积分流水列表。
     *
     * @return JsonResponse
     */
    public function pointTransactions(): JsonResponse
    {
        return $this->revert($this->business->pointTransactions($this->request->all()));
    }

    /**
     * 重试失败的分析记录。
     *
     * @param int $recordId 分析记录 ID
     * @return JsonResponse
     */
    public function retry(int $recordId): JsonResponse
    {
        return $this->revert($this->business->retry($recordId));
    }
}
