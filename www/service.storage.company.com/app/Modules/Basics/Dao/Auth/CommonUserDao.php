<?php

namespace App\Modules\Basics\Dao\Auth;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\Auth\CommonUser;
use Illuminate\Database\Eloquent\Model;

class CommonUserDao extends BaseDao
{
    public function __construct(protected CommonUser $commonUser)
    {
    }

    protected function getModel(): Model
    {
        return $this->commonUser;
    }
}
