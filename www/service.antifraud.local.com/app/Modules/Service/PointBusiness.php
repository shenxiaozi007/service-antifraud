<?php

namespace App\Modules\Service;

use App\Kernel\Base\BaseBusiness;
use App\Libraries\CommonService\CommonServiceClient;
use Illuminate\Http\Request;

class PointBusiness extends BaseBusiness
{
    public function __construct(
        protected UserBusiness $userBusiness,
        protected CommonServiceClient $commonServiceClient
    ) {
    }

    public function transactions(Request $request): array
    {
        $user = $this->userBusiness->currentUser($request);
        $data = $this->validate($request->all(), [
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);
        return $this->commonServiceClient->transactions($this->bearerToken($request), [
            'page_size' => (int) ($data['page_size'] ?? 20),
        ]);
    }
}
