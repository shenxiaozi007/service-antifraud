<?php

namespace App\Modules\Basics\Dao;

use App\Kernel\Base\BaseDao;
use App\Modules\Basics\Model\FileAsset;
class FileAssetDao extends BaseDao
{
    /**
     * @param FileAsset $model 文件模型
     */
    public function __construct(FileAsset $model)
    {
        parent::__construct($model);
    }

    /**
     * 查询用户自己的文件。
     *
     * @param int $fileId 文件 ID
     * @param int $userId 用户 ID
     * @return FileAsset|null
     */
    public function findUserFile(int $fileId, int $userId): ?FileAsset
    {
        return $this->newBuilder()
            ->where('id', $fileId)
            ->userIdQuery($userId)
            ->first();
    }

    /**
     * 将已上传文件绑定到分析记录。
     *
     * @param array $fileIds 文件 ID 列表
     * @param int $recordId 分析记录 ID
     * @param int $userId 用户 ID
     * @return void
     */
    public function bindRecord(array $fileIds, int $recordId, int $userId): void
    {
        $this->newBuilder()
            ->whereIn('id', $fileIds)
            ->userIdQuery($userId)
            ->update(['record_id' => $recordId]);
    }

    /**
     * 根据公共文件服务编号查询用户文件。
     *
     * @param string $storageFileId 公共文件服务文件 ID
     * @param int $userId 用户 ID
     * @return FileAsset|null
     */
    public function findByStorageFileId(string $storageFileId, int $userId): ?FileAsset
    {
        return $this->newBuilder()
            ->storageFileIdQuery($storageFileId)
            ->userIdQuery($userId)
            ->first();
    }

    /**
     * 管理端文件分页列表。
     *
     * @param array $filters 查询条件：file_type、user_id、page_size
     * @param int $pageSize 每页条数
     * @return LengthAwarePaginator
     */
    public function page(array $filters, int $pageSize = 20)
    {
        return $this->getPageList(array_merge($filters, [
            'page_size' => $pageSize,
            'sort_by_id_desc' => true,
        ]));
    }
}
