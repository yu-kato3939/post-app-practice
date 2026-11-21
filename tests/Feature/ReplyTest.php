<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplyTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $other;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => '雑記']);
        $this->author = User::factory()->create(['name' => 'ポスト投稿者']);
        $this->other = User::factory()->create(['name' => '別のユーザー']);
        $this->post = Post::create([
            'user_id' => $this->author->id,
            'category_id' => $category->id,
            'title' => '元のタイトル',
            'content' => '元の本文',
        ]);
    }

    private function makeReply(User $user, string $content, ?Post $post = null, ?Carbon $at = null): Reply
    {
        $reply = new Reply([
            'post_id' => ($post ?? $this->post)->id,
            'user_id' => $user->id,
            'content' => $content,
        ]);

        if ($at) {
            $reply->forceFill(['created_at' => $at, 'updated_at' => $at]);
        }

        $reply->save();

        return $reply;
    }

    private function reply(string $content, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->other)
            ->from(route('posts.show', $this->post))
            ->post(route('replies.store', $this->post), ['content' => $content]);
    }

    // ---- 表示 ----

    public function test_詳細ページにそのポストのリプライだけが新しい順で表示される(): void
    {
        $anotherPost = Post::create([
            'user_id' => $this->author->id,
            'category_id' => $this->post->category_id,
            'title' => '別のポスト',
            'content' => '別の本文',
        ]);

        $this->makeReply($this->other, '一番古いリプライ', null, Carbon::parse('2024-01-01 10:00:00'));
        $this->makeReply($this->author, '二番目のリプライ', null, Carbon::parse('2024-01-01 11:00:00'));
        $this->makeReply($this->other, '一番新しいリプライ', null, Carbon::parse('2024-01-01 12:00:00'));
        $this->makeReply($this->other, '別ポストへのリプライ', $anotherPost);

        $this->actingAs($this->author)
            ->get(route('posts.show', $this->post))
            ->assertOk()
            ->assertSee('元のタイトル')
            ->assertSee('元の本文')
            ->assertSeeInOrder(['一番新しいリプライ', '二番目のリプライ', '一番古いリプライ'])
            ->assertDontSee('別ポストへのリプライ');
    }

    public function test_リプライが0件のポストでは空状態のメッセージが出る(): void
    {
        $this->actingAs($this->author)
            ->get(route('posts.show', $this->post))
            ->assertOk()
            ->assertSee('まだリプライがありません。');
    }

    public function test_自分のリプライにだけ削除ボタンが出る(): void
    {
        $mine = $this->makeReply($this->author, '自分のリプライ');
        $theirs = $this->makeReply($this->other, '他人のリプライ');

        $this->actingAs($this->author)
            ->get(route('posts.show', $this->post))
            ->assertSee(route('replies.destroy', $mine))
            ->assertDontSee(route('replies.destroy', $theirs));
    }

    public function test_タイムラインの各ポストに返信リンクが出る(): void
    {
        $this->actingAs($this->other)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee(route('posts.show', $this->post))
            ->assertSee('返信');
    }

    public function test_タイムラインの編集と削除は投稿者本人にだけ出るまま(): void
    {
        $this->actingAs($this->other)
            ->get(route('posts.index'))
            ->assertDontSee(route('posts.edit', $this->post))
            ->assertDontSee('削除');

        // 削除は posts.show と同じ URL（DELETE /posts/{post}）なので、ボタンの文言で確かめる
        $this->actingAs($this->author)
            ->get(route('posts.index'))
            ->assertSee(route('posts.edit', $this->post))
            ->assertSee('削除');
    }

    // ---- 送信 ----

    public function test_本文ありで送信するとリプライが増えて一覧に出る(): void
    {
        $this->reply('はじめまして')->assertRedirect(route('posts.show', $this->post));

        $this->assertDatabaseHas('replies', [
            'post_id' => $this->post->id,
            'user_id' => $this->other->id,
            'content' => 'はじめまして',
        ]);

        $this->actingAs($this->other)
            ->get(route('posts.show', $this->post))
            ->assertSee('はじめまして');
    }

    public function test_本文が空だとエラーが表示されリプライは増えない(): void
    {
        $this->reply('')
            ->assertRedirect(route('posts.show', $this->post))
            ->assertSessionHasErrors(['content' => '本文を入力してください。']);

        $this->assertDatabaseCount('replies', 0);

        $this->followingRedirects()
            ->actingAs($this->other)
            ->from(route('posts.show', $this->post))
            ->post(route('replies.store', $this->post), ['content' => ''])
            ->assertSee('本文を入力してください。');
    }

    public function test_本文が140字ちょうどなら送信できる(): void
    {
        $content = str_repeat('あ', 140);

        $this->reply($content)->assertRedirect(route('posts.show', $this->post));

        $this->assertDatabaseHas('replies', ['content' => $content]);
    }

    public function test_本文が141字だとエラーになりリプライは増えない(): void
    {
        $this->reply(str_repeat('あ', 141))
            ->assertRedirect(route('posts.show', $this->post))
            ->assertSessionHasErrors(['content' => '本文は140文字以内で入力してください。']);

        $this->assertDatabaseCount('replies', 0);
    }

    public function test_改行を含む140字は_crl_fで送られても送信できる(): void
    {
        // 画面上は 70 + 改行1 + 69 = 140 字。ブラウザは改行を CRLF（2 文字）で送る。
        $content = str_repeat('あ', 70)."\r\n".str_repeat('い', 69);

        $this->reply($content)->assertRedirect(route('posts.show', $this->post));

        $this->assertDatabaseHas('replies', [
            'content' => str_repeat('あ', 70)."\n".str_repeat('い', 69),
        ]);
    }

    // ---- 削除・認可 ----

    public function test_自分のリプライを削除すると消える(): void
    {
        $reply = $this->makeReply($this->other, '消したいリプライ');

        $this->actingAs($this->other)
            ->delete(route('replies.destroy', $reply))
            ->assertRedirect(route('posts.show', $this->post));

        $this->assertDatabaseMissing('replies', ['id' => $reply->id]);
    }

    public function test_他人のリプライは削除できない(): void
    {
        $reply = $this->makeReply($this->other, '他人のリプライ');
        $third = User::factory()->create();

        $this->actingAs($third)
            ->delete(route('replies.destroy', $reply))
            ->assertForbidden();

        $this->assertDatabaseHas('replies', ['id' => $reply->id]);
    }

    public function test_ポスト投稿者でも他人のリプライは削除できない(): void
    {
        $reply = $this->makeReply($this->other, '他人のリプライ');

        $this->actingAs($this->author)
            ->delete(route('replies.destroy', $reply))
            ->assertForbidden();

        $this->assertDatabaseHas('replies', ['id' => $reply->id]);
    }

    public function test_未ログインでは詳細_送信_削除のいずれもログイン画面へ飛ぶ(): void
    {
        $reply = $this->makeReply($this->other, 'リプライ');

        $this->get(route('posts.show', $this->post))->assertRedirect('/login');
        $this->post(route('replies.store', $this->post), ['content' => 'こんにちは'])->assertRedirect('/login');
        $this->delete(route('replies.destroy', $reply))->assertRedirect('/login');

        $this->assertDatabaseCount('replies', 1);
    }

    public function test_存在しないポストやリプライは404(): void
    {
        $this->actingAs($this->author)->get('/posts/9999')->assertNotFound();
        $this->actingAs($this->author)->post('/posts/9999/replies', ['content' => 'x'])->assertNotFound();
        $this->actingAs($this->author)->delete('/replies/9999')->assertNotFound();
    }

    // ---- 既存の動きが変わっていないこと ----

    public function test_リプライ付きのポストを削除してもエラーにならずリプライも消える(): void
    {
        $this->makeReply($this->other, 'リプライ1');
        $this->makeReply($this->author, 'リプライ2');

        $this->actingAs($this->author)
            ->delete(route('posts.destroy', $this->post))
            ->assertRedirect(route('posts.index'));

        $this->assertDatabaseMissing('posts', ['id' => $this->post->id]);
        $this->assertDatabaseCount('replies', 0);
    }

    public function test_ユーザーを削除するとそのユーザーのリプライも消える(): void
    {
        $this->makeReply($this->other, '消えるリプライ');
        $kept = $this->makeReply($this->author, '残るリプライ');

        $this->other->delete();

        $this->assertDatabaseCount('replies', 1);
        $this->assertDatabaseHas('replies', ['id' => $kept->id]);
    }
}
