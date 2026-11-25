<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => '雑記']);
        $this->user = User::factory()->create(['name' => 'はるか']);
    }

    private function store(array $overrides = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->user)
            ->from(route('posts.index'))
            ->post(route('posts.store'), array_merge([
                'title' => '新しいタイトル',
                'category_id' => $this->category->id,
                'content' => '新しい本文',
            ], $overrides));
    }

    public function test_ログインするとタイムラインにポストを書く場所がある(): void
    {
        $this->actingAs($this->user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('<form class="composer" action="'.route('posts.store').'" method="POST">', false)
            ->assertSee('name="title"', false)
            ->assertSee('name="category_id"', false)
            ->assertSee('name="content"', false);
    }

    public function test_ポストが0件でも書く場所がある(): void
    {
        // route('posts.store') は route('posts.index') と同じ URL なので、URL ではなくフォーム自体を確かめる
        $this->actingAs($this->user)
            ->get(route('posts.index'))
            ->assertSee('まだポストがありません。')
            ->assertSee('<form class="composer"', false)
            ->assertSee('name="content"', false);
    }

    public function test_送るとタイムラインの先頭にそのポストが増える(): void
    {
        $other = User::factory()->create();
        $old = Post::create([
            'user_id' => $other->id,
            'category_id' => $this->category->id,
            'title' => '古いポスト',
            'content' => '古い本文',
        ]);

        $this->store()->assertRedirect(route('posts.index'));

        $this->assertDatabaseCount('posts', 2);

        $this->actingAs($this->user)
            ->get(route('posts.index'))
            ->assertSeeInOrder(['新しいタイトル', '古いポスト']);
    }

    public function test_同じ時刻でも後から書いたポストが先頭に出る(): void
    {
        $this->travelTo(now()->startOfSecond());

        $this->store(['title' => '一つ目'])->assertRedirect(route('posts.index'));
        $this->store(['title' => '二つ目'])->assertRedirect(route('posts.index'));

        $this->actingAs($this->user)
            ->get(route('posts.index'))
            ->assertSeeInOrder(['二つ目', '一つ目']);
    }

    public function test_本文が空だとエラーが表示されポストは増えない(): void
    {
        $this->store(['content' => ''])
            ->assertRedirect(route('posts.index'))
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('posts', 0);

        // リダイレクト先のタイムラインにエラーメッセージが出る
        $this->followingRedirects()
            ->actingAs($this->user)
            ->from(route('posts.index'))
            ->post(route('posts.store'), [
                'title' => '新しいタイトル',
                'category_id' => $this->category->id,
                'content' => '',
            ])
            ->assertSee('本文を入力してください。');
    }

    public function test_本文が141字だとエラーになり保存されない(): void
    {
        $this->store(['content' => str_repeat('あ', 141)])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_本文が140字ちょうどなら保存できる(): void
    {
        $this->store(['content' => str_repeat('あ', 140)])
            ->assertRedirect(route('posts.index'));

        $this->assertDatabaseCount('posts', 1);
    }

    public function test_タイトルが空だとエラーになり保存されない(): void
    {
        $this->store(['title' => ''])->assertSessionHasErrors('title');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_存在しないトピックだとエラーになり保存されない(): void
    {
        $this->store(['category_id' => 9999])->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_投稿者はログイン中の本人になり_user_idを送っても変えられない(): void
    {
        $other = User::factory()->create();

        $this->store(['user_id' => $other->id]);

        $this->assertDatabaseHas('posts', [
            'title' => '新しいタイトル',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_増えたポストには自分の名前と編集・削除の操作が出る(): void
    {
        $this->store();

        $post = Post::firstOrFail();

        $this->actingAs($this->user)
            ->get(route('posts.index'))
            ->assertSee('はるか')
            ->assertSee(route('posts.edit', $post), false)
            ->assertSee('>削除</button>', false);
    }

    public function test_他人のポストには編集・削除の操作が出ない(): void
    {
        $other = User::factory()->create();
        $this->store(as: $other);

        $post = Post::firstOrFail();

        $this->actingAs($this->user)
            ->get(route('posts.index'))
            ->assertDontSee(route('posts.edit', $post), false)
            ->assertDontSee('>削除</button>', false);

        $this->actingAs($this->user)
            ->get(route('posts.edit', $post))
            ->assertForbidden();
    }

    public function test_ログインしていないと書けずログイン画面に飛ばされる(): void
    {
        $this->post(route('posts.store'), [
            'title' => '新しいタイトル',
            'category_id' => $this->category->id,
            'content' => '新しい本文',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('posts', 0);

        $this->get(route('posts.index'))->assertRedirect(route('login'));
    }
}
