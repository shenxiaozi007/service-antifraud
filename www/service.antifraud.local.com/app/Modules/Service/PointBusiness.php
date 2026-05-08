<?php

namespace App\Modules\Service;

use App\Kernel\Base\BaseBusiness;
use App\Modules\Basics\Dao\PointTransactionDao;
use Illuminate\Http\Request;

class PointBusiness extends BaseBusiness
{
    public function __construct(
        protected UserBusiness $userBusiness,
        protected PointTransactionDao $pointTransactionDao
    ) {
    }

    public function transactions(Request $request): array
    {
        $user = $this->userBusiness->currentUser($request);
        $data = $this->validate($request->all(), [
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);
        $page = $this->pointTransactionDao->userPage($user->id, (int) ($data['page_size'] ?? 20));

        return [
            'items' => collect($page->items())->map(fn ($item) => [
                'id' => $item->id,
                'amount' => $item->amount,
                'balance_after' => $item->balance_after,
                'type' => $item->type,
                'status' => $item->status,
                'remark' => $item->remark,
                'created_at' => $this->datetimeString($item->created_at),
            ])->values(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
        ];
    }
}
