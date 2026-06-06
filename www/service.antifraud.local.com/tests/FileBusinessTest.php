<?php

namespace Tests;

use App\Modules\Basics\Dao\FileAssetDao;
use App\Modules\Basics\Model\FileAsset;
use App\Modules\Basics\Model\User;
use App\Modules\Service\FileBusiness;
use App\Modules\Service\UserBusiness;
use Illuminate\Http\Request;

class FileBusinessTest extends TestCase
{
    public function test_upload_token_points_legacy_clients_to_common_file_service_without_creating_local_asset(): void
    {
        config([
            'common_service.base_url' => 'https://file.hxcbox.cn/service/api/v1',
            'common_service.project_code' => 'antifraud',
        ]);

        $dao = new InMemoryFileAssetDaoForUploadToken();
        $business = new FileBusiness(new InMemoryUserBusinessForFile(), $dao);

        $result = $business->uploadToken(new Request([
            'file_type' => 'image',
            'mime_type' => 'image/jpeg',
            'file_size' => 204800,
        ]));

        $this->assertSame('https://file.hxcbox.cn/service/api/v1/file/upload', $result['upload_url']);
        $this->assertSame('multipart', $result['upload_method']);
        $this->assertSame('/api/v1/files/register', $result['register_url']);
        $this->assertSame('antifraud', $result['owner_project']);
        $this->assertSame(0, $dao->createCount);
    }
}

class InMemoryUserBusinessForFile extends UserBusiness
{
    public function __construct()
    {
    }

    public function currentUser(Request $request)
    {
        $user = new User();
        $user->id = 10001;
        $user->global_user_id = 20001;

        return $user;
    }
}

class InMemoryFileAssetDaoForUploadToken extends FileAssetDao
{
    public int $createCount = 0;

    public function __construct()
    {
    }

    public function create(array $data): FileAsset
    {
        $this->createCount += 1;

        return new FileAsset($data);
    }
}
