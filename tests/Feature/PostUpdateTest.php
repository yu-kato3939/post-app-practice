<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => '雑記']);
        $this->user = User::factory()->create();
        $this->post = Post::create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'title' => '元のタイトル',
            'content' => '元の本文',
        ]);
    }

    private function update(string $content, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->user)
            ->from(route('posts.edit', $this->post))
            ->put(route('posts.update', $this->post), [
                'title' => '新しいタイトル',
                'category_id' => $this->post->category_id,
                'content' => $content,
            ]);
    }

    public function test_本文が140字ちょうどなら更新できる(): void
    {
        $content = str_repeat('あ', 140);

        $this->update($content)->assertRedirect(route('posts.index'));

        $this->assertSame($content, $this->post->fresh()->content);
    }

    public function test_本文が141字だとエラーになり保存されない(): void
    {
        $this->update(str_repeat('あ', 141))
            ->assertRedirect(route('posts.edit', $this->post))
            ->assertSessionHasErrors('content');

        $this->assertSame('元の本文', $this->post->fresh()->content);
    }

    public function test_上限超過のエラー文言は日本語で出る(): void
    {
        $this->update(str_repeat('あ', 141))
            ->assertSessionHasErrors(['content' => '本文は140文字以内で入力してください。']);
    }

    public function test_本文が空のときのエラー文言も日本語で出る(): void
    {
        $this->update('')
            ->assertSessionHasErrors(['content' => '本文を入力してください。']);
    }

    public function test_改行を含む140字は_crl_fで送られても更新できる(): void
    {
        // 画面上は 70 + 改行1 + 69 = 140 字。ブラウザは改行を CRLF（2 文字）で送る。
        $content = str_repeat('あ', 70)."\r\n".str_repeat('い', 69);

        $this->update($content)->assertRedirect(route('posts.index'));

        $this->assertSame(
            str_repeat('あ', 70)."\n".str_repeat('い', 69),
            $this->post->fresh()->content
        );
    }

    public function test_他人の投稿は更新できない(): void
    {
        $other = User::factory()->create();

        $this->update('短い本文', $other)->assertForbidden();

        $this->assertSame('元の本文', $this->post->fresh()->content);
    }
}
