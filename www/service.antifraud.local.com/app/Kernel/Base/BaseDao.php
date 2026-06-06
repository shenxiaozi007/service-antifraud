<?php

namespace App\Kernel\Base;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Dao 基类。
 *
 * Dao 只处理查询、分页和持久化，不承载业务判断。列表查询通过
 * 参数名约定调用 Model scope，例如 category => scopeCategoryQuery，
 * category_like => scopeCategoryLikeQuery。这样业务层只需要准备
 * 已校验过的参数，具体 SQL 条件由模型表达。
 */
abstract class BaseDao
{
    /**
     * 查询字段默认值。
     */
    protected array $selectColumns = ['*'];

    /**
     * 构建查询条件时忽略的请求控制字段。
     */
    protected array $buildFieldConditionIgnore = [
        'page',
        'page_size',
        'per_page',
        'order_by',
    ];

    /**
     * @param Model $model 当前 Dao 负责的模型实例
     */
    public function __construct(protected Model $model)
    {
    }

    /**
     * 获取当前 Dao 绑定的模型实例。
     *
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * 创建模型查询构造器。
     *
     * @return Builder
     */
    public function newBuilder(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * 兼容旧 Dao 写法，旧代码中仍有 $this->query() 调用。
     *
     * @return Builder
     */
    protected function query(): Builder
    {
        return $this->newBuilder();
    }

    /**
     * 获取 select 字段，未传入时使用默认字段。
     *
     * @param array $columns 调用方指定的查询字段
     * @return array
     */
    public function getSelectColumns(array $columns = []): array
    {
        return $columns ?: $this->selectColumns;
    }

    /**
     * 获取模型表名，给 Rule::exists 等校验规则复用，避免写死表名。
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->getModel()->getTable();
    }

    /**
     * 按主键查询单条数据。
     *
     * @param int $id 主键 ID
     * @param array $columns 查询字段
     * @param array $relations 预加载关联
     * @return Model|null
     */
    public function find(int $id, array $columns = [], array $relations = []): ?Model
    {
        return $this->newBuilder()
            ->select($this->getSelectColumns($columns))
            ->with($relations)
            ->find($id);
    }

    /**
     * 新增数据。
     *
     * @param array $data 已校验和过滤过的写入数据
     * @return Model
     */
    public function create(array $data): Model
    {
        return $this->newBuilder()->create($data);
    }

    /**
     * 新增数据。命名对齐 wg-manage-service 的 CRUD 写法。
     *
     * @param array $params 已校验和过滤过的写入数据
     * @return Model
     */
    public function store(array $params): Model
    {
        return $this->create($params);
    }

    /**
     * 更新模型并返回更新后的当前实例。
     *
     * @param Model $model 待更新模型
     * @param array $params 已校验和过滤过的更新数据
     * @return Model
     */
    public function updateModel(Model $model, array $params): Model
    {
        $model->fill($params);
        $model->save();

        return $model;
    }

    /**
     * 构建通用查询。
     *
     * @param array $params 已由 Business 校验和过滤过的查询参数
     * @param array $columns 需要 select 的字段
     * @param array $relations 需要预加载的关联
     * @return Builder
     */
    public function doQuery(array $params = [], array $columns = [], array $relations = []): Builder
    {
        $query = $this->newBuilder()
            ->select($this->getSelectColumns($columns))
            ->with($relations);

        $params = $this->beforeBuildFiled($query, $params);

        foreach ($params as $field => $value) {
            if ($this->shouldIgnoreCondition($field, $value)) {
                continue;
            }

            $this->buildFieldCondition($query, (string) $field, $value);
        }

        return $query;
    }

    /**
     * 构建查询前整理组合参数。
     *
     * 子类可在这里把业务筛选转换成更清晰的 Model scope 参数。
     *
     * @param Builder $query 查询构造器
     * @param array $params 查询参数
     * @return array
     */
    public function beforeBuildFiled(Builder $query, array $params): array
    {
        return $params;
    }

    /**
     * 获取不分页列表。
     *
     * @param array $params 查询参数
     * @param array $columns 查询字段
     * @param array $relations 预加载关联
     * @return Collection
     */
    public function getList(array $params = [], array $columns = [], array $relations = []): Collection
    {
        return $this->doQuery($params, $columns, $relations)->get();
    }

    /**
     * 获取分页列表。
     *
     * @param array $params 查询参数，支持 page_size/per_page
     * @param array $columns 查询字段
     * @param array $relations 预加载关联
     * @return LengthAwarePaginator
     */
    public function getPageList(array $params = [], array $columns = [], array $relations = []): LengthAwarePaginator
    {
        $pageSize = (int) ($params['page_size'] ?? $params['per_page'] ?? 20);
        $pageSize = max(1, min($pageSize, 100));

        return $this->doQuery($params, $columns, $relations)->paginate($pageSize);
    }

    /**
     * 空字符串、null 和控制字段不进入 scope 查询。
     *
     * 注意数字 0 是有效筛选值，例如 enabled=0，所以不能用 empty()。
     *
     * @param string $field 参数字段名
     * @param mixed $value 参数值
     * @return bool
     */
    private function shouldIgnoreCondition(string $field, mixed $value): bool
    {
        return in_array($field, $this->buildFieldConditionIgnore, true)
            || $value === null
            || $value === ''
            || $value === [];
    }

    /**
     * 按参数名调用模型 scope。
     *
     * 参数 category 会调用 categoryQuery()，对应 Model 的
     * scopeCategoryQuery；参数 keyword_like 会调用 keywordLikeQuery()。
     *
     * @param Builder $query 查询构造器
     * @param string $field 参数字段名
     * @param mixed $value 参数值
     * @return void
     */
    private function buildFieldCondition(Builder $query, string $field, mixed $value): void
    {
        $scope = Str::camel($field . '_query');
        $scopeMethod = 'scope' . Str::studly($field) . 'Query';

        if (method_exists($this->getModel(), $scopeMethod)) {
            $query->{$scope}($value);
        }
    }
}
