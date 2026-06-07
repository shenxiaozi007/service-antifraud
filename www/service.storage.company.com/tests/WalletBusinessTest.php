<?php

namespace Tests;

use App\Modules\Basics\Dao\Wallet\ProjectWalletDao;
use App\Modules\Basics\Dao\Wallet\WalletTransactionDao;
use App\Modules\Basics\Model\Wallet\ProjectWallet;
use App\Modules\Service\Business\Wallet\WalletBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletBusinessTest extends TestCase
{
    public function test_wallet_freeze_confirm_release_keep_balances_consistent(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $wallet = new InMemoryWallet(1, 'antifraud', 300, 0);
        $transactions = new InMemoryWalletTransactions();
        $business = new WalletBusiness(
            new InMemoryProjectWalletDao($wallet),
            $transactions
        );

        $frozen = $business->freeze([
            'user_id' => 1,
            'project_code' => 'antifraud',
            'amount' => 100,
            'related_no' => 'analysis_1',
        ]);
        $this->assertSame(200, $frozen['balance']);
        $this->assertSame(100, $frozen['frozen_balance']);

        $confirmed = $business->confirm([
            'user_id' => 1,
            'project_code' => 'antifraud',
            'amount' => 100,
            'related_no' => 'analysis_1',
        ]);
        $this->assertSame(200, $confirmed['balance']);
        $this->assertSame(0, $confirmed['frozen_balance']);

        $business->freeze([
            'user_id' => 1,
            'project_code' => 'antifraud',
            'amount' => 80,
            'related_no' => 'analysis_2',
        ]);
        $released = $business->release([
            'user_id' => 1,
            'project_code' => 'antifraud',
            'amount' => 80,
            'related_no' => 'analysis_2',
        ]);
        $this->assertSame(200, $released['balance']);
        $this->assertSame(0, $released['frozen_balance']);
        $this->assertCount(4, $transactions->items);
    }

    public function test_wallet_release_rejects_amount_greater_than_frozen_balance(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $wallet = new InMemoryWallet(1, 'antifraud', 200, 20);
        $transactions = new InMemoryWalletTransactions();
        $business = new WalletBusiness(
            new InMemoryProjectWalletDao($wallet),
            $transactions
        );

        $this->expectException(ValidationException::class);

        try {
            $business->release([
                'user_id' => 1,
                'project_code' => 'antifraud',
                'amount' => 80,
                'related_no' => 'analysis_mismatch',
            ]);
        } finally {
            $this->assertSame(200, $wallet->balance);
            $this->assertSame(20, $wallet->frozen_balance);
            $this->assertCount(0, $transactions->items);
        }
    }

    public function test_wallet_transactions_by_user_returns_common_wallet_ledger(): void
    {
        $transactions = new InMemoryWalletTransactions();
        $transactions->items[] = [
            'user_id' => 1,
            'project_code' => 'antifraud',
            'transaction_no' => 'wt_1',
            'related_no' => 'pay_1',
            'amount' => 100,
            'frozen_amount' => 0,
            'balance_after' => 100,
            'frozen_after' => 0,
            'type' => 'recharge',
            'status' => 'completed',
            'remark' => '微信支付充值',
            'created_at' => null,
        ];
        $business = new WalletBusiness(
            new InMemoryProjectWalletDao(new InMemoryWallet(1, 'antifraud', 100, 0)),
            $transactions
        );

        $result = $business->transactionsByUser([
            'user_id' => 1,
            'project_code' => 'antifraud',
            'page_size' => 10,
        ]);

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['items'][0]['user_id']);
        $this->assertSame('antifraud', $result['items'][0]['project_code']);
        $this->assertSame('pay_1', $result['items'][0]['related_no']);
    }

    public function test_wallet_reward_adds_balance_once_by_related_no_and_type(): void
    {
        DB::shouldReceive('transaction')->andReturnUsing(fn (callable $callback) => $callback());

        $wallet = new InMemoryWallet(1, 'antifraud', 0, 0);
        $transactions = new InMemoryWalletTransactions();
        $business = new WalletBusiness(
            new InMemoryProjectWalletDao($wallet),
            $transactions
        );

        $rewarded = $business->reward([
            'user_id' => 1,
            'project_code' => 'antifraud',
            'amount' => 500,
            'related_no' => 'new_user_antifraud_1',
            'type' => 'gift',
            'remark' => '新用户注册赠送',
        ]);
        $duplicate = $business->reward([
            'user_id' => 1,
            'project_code' => 'antifraud',
            'amount' => 500,
            'related_no' => 'new_user_antifraud_1',
            'type' => 'gift',
        ]);

        $this->assertSame(500, $rewarded['balance']);
        $this->assertSame(500, $duplicate['balance']);
        $this->assertCount(1, $transactions->items);
        $this->assertSame('gift', $transactions->items[0]['type']);
    }
}

class InMemoryProjectWalletDao extends ProjectWalletDao
{
    public function __construct(private InMemoryWallet $wallet)
    {
    }

    public function findWallet(int $userId, string $projectCode): ?ProjectWallet
    {
        return $this->wallet->matches($userId, $projectCode) ? $this->wallet : null;
    }

    public function lockWallet(int $userId, string $projectCode): ?ProjectWallet
    {
        return $this->findWallet($userId, $projectCode);
    }

    public function store(array $params, array $extra = []): Model
    {
        $data = array_merge($params, $extra);
        $this->wallet = new InMemoryWallet($data['user_id'], $data['project_code'], $data['balance'], $data['frozen_balance']);

        return $this->wallet;
    }
}

class InMemoryWalletTransactions extends WalletTransactionDao
{
    public array $items = [];

    public function __construct()
    {
    }

    public function existsRelated(string $relatedNo, string $type): bool
    {
        foreach ($this->items as $item) {
            if ($item['related_no'] === $relatedNo && $item['type'] === $type) {
                return true;
            }
        }

        return false;
    }

    public function store(array $params, array $extra = []): Model
    {
        $data = array_merge($params, $extra);
        $this->items[] = $data;

        return new WalletTransactionMemoryModel($data);
    }

    public function page(int $userId, string $projectCode, int $pageSize = 20)
    {
        return new InMemoryWalletPaginator(array_values(array_filter(
            $this->items,
            fn ($item) => (int) $item['user_id'] === $userId && $item['project_code'] === $projectCode
        )), $pageSize);
    }
}

class InMemoryWalletPaginator
{
    public function __construct(private array $items, private int $pageSize)
    {
    }

    public function items(): array
    {
        return array_map(fn ($item) => new WalletTransactionMemoryModel($item), $this->items);
    }

    public function total(): int
    {
        return count($this->items);
    }

    public function currentPage(): int
    {
        return 1;
    }

    public function perPage(): int
    {
        return $this->pageSize;
    }
}

class WalletTransactionMemoryModel extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->exists = true;
    }
}

class InMemoryWallet extends ProjectWallet
{
    public $timestamps = false;
    public bool $saved = false;

    public function __construct(int $userId = 0, string $projectCode = '', int $balance = 0, int $frozenBalance = 0)
    {
        parent::__construct();
        $this->user_id = $userId;
        $this->project_code = $projectCode;
        $this->balance = $balance;
        $this->frozen_balance = $frozenBalance;
        $this->exists = true;
    }

    public function matches(int $userId, string $projectCode): bool
    {
        return (int) $this->user_id === $userId && $this->project_code === $projectCode;
    }

    public function save(array $options = []): bool
    {
        $this->saved = true;

        return true;
    }
}
