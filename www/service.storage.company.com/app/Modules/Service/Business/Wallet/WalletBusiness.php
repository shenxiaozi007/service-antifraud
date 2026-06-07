<?php

namespace App\Modules\Service\Business\Wallet;

use App\Modules\Basics\Dao\Wallet\ProjectWalletDao;
use App\Modules\Basics\Dao\Wallet\WalletTransactionDao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletBusiness
{
    public function __construct(
        protected ProjectWalletDao $walletDao,
        protected WalletTransactionDao $transactionDao,
    ) {
    }

    public function balance(int $userId, string $projectCode): array
    {
        $wallet = $this->firstOrCreateWallet($userId, $projectCode);

        return $this->formatWallet($wallet);
    }

    public function transactions(int $userId, string $projectCode, int $pageSize = 20): array
    {
        $page = $this->transactionDao->page($userId, $projectCode, $pageSize);

        return $this->formatTransactionPage($page);
    }

    public function transactionsByUser(array $params): array
    {
        $data = validator($params, [
            'user_id' => 'required|integer|min:1',
            'project_code' => 'required|string|max:64',
            'page_size' => 'nullable|integer|min:1|max:100',
        ])->validate();

        $page = $this->transactionDao->page((int) $data['user_id'], $data['project_code'], (int) ($data['page_size'] ?? 20));

        return $this->formatTransactionPage($page);
    }

    protected function formatTransactionPage($page): array
    {
        return [
            'items' => collect($page->items())->map(fn ($item) => [
                'transaction_no' => $item->transaction_no,
                'user_id' => $item->user_id,
                'project_code' => $item->project_code,
                'related_no' => $item->related_no,
                'amount' => $item->amount,
                'frozen_amount' => $item->frozen_amount,
                'balance_after' => $item->balance_after,
                'frozen_after' => $item->frozen_after,
                'type' => $item->type,
                'status' => $item->status,
                'remark' => $item->remark,
                'created_at' => optional($item->created_at)->toDateTimeString(),
            ])->values(),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
        ];
    }

    public function freeze(array $params): array
    {
        $data = validator($params, [
            'user_id' => 'required|integer|min:1',
            'project_code' => 'required|string|max:64',
            'amount' => 'required|integer|min:1',
            'related_no' => 'required|string|max:64',
            'remark' => 'nullable|string|max:255',
        ])->validate();

        return DB::transaction(function () use ($data) {
            if ($this->transactionDao->existsRelated($data['related_no'], 'freeze')) {
                return $this->formatWallet($this->firstOrCreateWallet($data['user_id'], $data['project_code']));
            }

            $wallet = $this->lockOrCreateWallet($data['user_id'], $data['project_code']);
            if ($wallet->balance < $data['amount']) {
                throw ValidationException::withMessages(['amount' => '点数余额不足']);
            }

            $wallet->balance -= $data['amount'];
            $wallet->frozen_balance += $data['amount'];
            $wallet->save();
            $this->record($wallet, $data['related_no'], -$data['amount'], $data['amount'], 'freeze', $data['remark'] ?? '冻结点数');

            return $this->formatWallet($wallet);
        });
    }

    public function confirm(array $params): array
    {
        $data = validator($params, [
            'user_id' => 'required|integer|min:1',
            'project_code' => 'required|string|max:64',
            'amount' => 'required|integer|min:1',
            'related_no' => 'required|string|max:64',
            'remark' => 'nullable|string|max:255',
        ])->validate();

        return DB::transaction(function () use ($data) {
            if ($this->transactionDao->existsRelated($data['related_no'], 'confirm')) {
                return $this->formatWallet($this->firstOrCreateWallet($data['user_id'], $data['project_code']));
            }

            $wallet = $this->lockOrCreateWallet($data['user_id'], $data['project_code']);
            if ($wallet->frozen_balance < $data['amount']) {
                throw ValidationException::withMessages(['amount' => '冻结点数不足']);
            }

            $wallet->frozen_balance -= $data['amount'];
            $wallet->save();
            $this->record($wallet, $data['related_no'], 0, -$data['amount'], 'confirm', $data['remark'] ?? '确认扣点');

            return $this->formatWallet($wallet);
        });
    }

    public function release(array $params): array
    {
        $data = validator($params, [
            'user_id' => 'required|integer|min:1',
            'project_code' => 'required|string|max:64',
            'amount' => 'required|integer|min:1',
            'related_no' => 'required|string|max:64',
            'remark' => 'nullable|string|max:255',
        ])->validate();

        return DB::transaction(function () use ($data) {
            if ($this->transactionDao->existsRelated($data['related_no'], 'release')) {
                return $this->formatWallet($this->firstOrCreateWallet($data['user_id'], $data['project_code']));
            }

            $wallet = $this->lockOrCreateWallet($data['user_id'], $data['project_code']);
            if ($wallet->frozen_balance < $data['amount']) {
                throw ValidationException::withMessages(['amount' => '冻结点数不足']);
            }

            $wallet->frozen_balance -= $data['amount'];
            $wallet->balance += $data['amount'];
            $wallet->save();
            $this->record($wallet, $data['related_no'], $data['amount'], -$data['amount'], 'release', $data['remark'] ?? '释放冻结点数');

            return $this->formatWallet($wallet);
        });
    }

    public function recharge(int $userId, string $projectCode, int $amount, string $relatedNo, string $remark = ''): array
    {
        return $this->addBalance($userId, $projectCode, $amount, $relatedNo, 'recharge', $remark ?: '充值到账');
    }

    /**
     * 服务端奖励积分入账。
     *
     * @param array $params 奖励参数：user_id/project_code/amount/related_no/type/remark
     * @return array{user_id:int,project_code:string,balance:int,frozen_balance:int}
     */
    public function reward(array $params): array
    {
        $data = validator($params, [
            'user_id' => 'required|integer|min:1',
            'project_code' => 'required|string|max:64',
            'amount' => 'required|integer|min:1',
            'related_no' => 'required|string|max:64',
            'type' => 'nullable|string|max:32',
            'remark' => 'nullable|string|max:255',
        ])->validate();

        return $this->addBalance(
            (int) $data['user_id'],
            $data['project_code'],
            (int) $data['amount'],
            $data['related_no'],
            $data['type'] ?? 'reward',
            $data['remark'] ?? '奖励到账'
        );
    }

    /**
     * 增加用户项目钱包可用积分。
     *
     * @param int $userId 用户 ID
     * @param string $projectCode 项目编码
     * @param int $amount 增加点数
     * @param string $relatedNo 业务幂等编号
     * @param string $type 流水类型
     * @param string $remark 流水备注
     * @return array{user_id:int,project_code:string,balance:int,frozen_balance:int}
     */
    protected function addBalance(int $userId, string $projectCode, int $amount, string $relatedNo, string $type, string $remark): array
    {
        return DB::transaction(function () use ($userId, $projectCode, $amount, $relatedNo, $type, $remark) {
            if ($this->transactionDao->existsRelated($relatedNo, $type)) {
                return $this->formatWallet($this->firstOrCreateWallet($userId, $projectCode));
            }

            $wallet = $this->lockOrCreateWallet($userId, $projectCode);
            $wallet->balance += $amount;
            $wallet->save();
            $this->record($wallet, $relatedNo, $amount, 0, $type, $remark);

            return $this->formatWallet($wallet);
        });
    }

    protected function firstOrCreateWallet(int $userId, string $projectCode)
    {
        return $this->walletDao->findWallet($userId, $projectCode)
            ?: $this->walletDao->store(['user_id' => $userId, 'project_code' => $projectCode, 'balance' => 0, 'frozen_balance' => 0]);
    }

    protected function lockOrCreateWallet(int $userId, string $projectCode)
    {
        $wallet = $this->walletDao->lockWallet($userId, $projectCode);
        if (!$wallet) {
            $this->walletDao->store(['user_id' => $userId, 'project_code' => $projectCode, 'balance' => 0, 'frozen_balance' => 0]);
            $wallet = $this->walletDao->lockWallet($userId, $projectCode);
        }

        return $wallet;
    }

    protected function record($wallet, string $relatedNo, int $amount, int $frozenAmount, string $type, string $remark): void
    {
        $this->transactionDao->store([
            'user_id' => $wallet->user_id,
            'project_code' => $wallet->project_code,
            'transaction_no' => 'wt_'.Str::lower(Str::random(24)),
            'related_no' => $relatedNo,
            'amount' => $amount,
            'frozen_amount' => $frozenAmount,
            'balance_after' => $wallet->balance,
            'frozen_after' => $wallet->frozen_balance,
            'type' => $type,
            'status' => 'completed',
            'remark' => $remark,
        ]);
    }

    protected function formatWallet($wallet): array
    {
        return [
            'user_id' => $wallet->user_id,
            'project_code' => $wallet->project_code,
            'balance' => $wallet->balance,
            'frozen_balance' => $wallet->frozen_balance,
        ];
    }
}
