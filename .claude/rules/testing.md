# テストの決まり

`tests/Feature/` の `PostStoreTest` / `PostUpdateTest` / `ReplyTest` に揃える。

## 書く・回す

- 機能の追加・修正にはテストも書く。置き場は `tests/Feature/`。
- 書いたら `./vendor/bin/sail test` で全テストを回し、落ちたら通るまで直す。
- `tests/Feature/ExampleTest.php` と `tests/Unit/ExampleTest.php` はひな形。ここには足さない。機能ごとに `XxxTest.php` を新しく作る。
- テストは `phpunit.xml` の `DB_DATABASE=testing` を使う。開発用 DB には触れない。

## 構成

- クラスは `Tests\TestCase` を継承し、`use RefreshDatabase;` を付ける。
- 前提データは `setUp()` で作る。カテゴリは `Category::create(['name' => '雑記'])`、ユーザーは `User::factory()->create()`、ポストは `Post::create([...])`。シーダーには頼らない。
- 何度も使うリクエストは `private function store()` / `update()` / `reply()` のようなヘルパーにする。ヘルパーは `actingAs` → `from(...)` → リクエストまでをまとめ、`array $overrides` や `?User $as` で一部だけ差し替えられるようにする。`from()` を付けるのは、バリデーション失敗時のリダイレクト先を確かめるため。
- 長いクラスは `// ---- 表示 ----` のような区切りコメントで分ける（`ReplyTest` は 表示 / 送信 / 削除・認可 / 既存の動き）。
- URL は `route('posts.index')` のようにルート名で書く。例外は、存在しない ID の 404 や `/login` への遷移のように、URL そのものを確かめたいとき。

## 命名

- メソッド名は `test_` で始め、日本語で「何をすると何になるか」を書く。`#[Test]` や `@test` は使わない。
- 例: `test_本文が141字だとエラーになり保存されない`。区切りが必要な箇所はアンダースコアで繋ぐ（`test_投稿者はログイン中の本人になり_user_idを送っても変えられない`）。
- 1 つのテストで 1 つの振る舞いを確かめる。

## 何を確かめるか

- **書き込み系**は、レスポンス（`assertRedirect` など）と DB の両方を確かめる。増えた・変わったことは `assertDatabaseHas` / `assertDatabaseCount` / `->fresh()`、増えない・消えることは `assertDatabaseCount` / `assertDatabaseMissing` で見る。失敗系は「DB が変わっていないこと」まで確かめる。
- **入力チェック**は、エラーになるケースと通るケースを境界で対にする（本文 140 字は通り、141 字は落ちる）。`assertSessionHasErrors` には日本語メッセージまで指定する（例: `['content' => '本文を入力してください。']`）。リダイレクト先の画面にメッセージが出ることも 1 件は確かめる。
- **改行を含む本文**は、ブラウザが CRLF で送る前提のケースを 1 件持つ（画面上 140 字なら通り、保存は `\n`）。
- **認可**は、他人 → `assertForbidden()` かつ DB が変わらない、未ログイン → `assertRedirect(route('login'))` かつ DB が変わらない、の 2 つを必ず書く。他人の操作は、ポスト投稿者であっても他人のリプライは消せない、のように関係を変えて確かめる。
- **画面の表示**は `assertSee` / `assertDontSee`。HTML をそのまま探すときは第 2 引数に `false` を渡す。並び順は `assertSeeInOrder` で確かめる。ボタンやリンクが出る・出ないは、本人と他人の両方で見る。
- **時刻に依存する並び**は `travelTo(now()->startOfSecond())` で時刻を固定し、同じ時刻でも順序が決まることを確かめる。
- **既存の動きを壊していない**ことも確かめる。機能を足したら、足した機能が既存の削除・一覧・カスケード削除に影響しないテストを入れる。

## コメント

- コメントは日本語で、「なぜそう確かめるか」だけ書く（例: `route('posts.store')` は `route('posts.index')` と同じ URL なので、フォーム自体を確かめる）。何をしているかの説明は書かない。
