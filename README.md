# post-app

投稿を一覧・編集・削除できる、小さな Laravel アプリです。Tutorial 13 から 15 まで、このアプリ 1 本を題材に設計・実装・仕組み化を進めます。

## できること

- ユーザー登録・ログイン・ログアウト
- 投稿の一覧表示（投稿者の名前とカテゴリつき）
- 投稿の編集（タイトル・カテゴリ・本文）と削除
- 自分の投稿だけを編集・削除（他人の投稿は編集・削除ボタンが出ず、URL を直接開くと 403 になります）

## フォルダについて

`answers/` は Tutorial 13 の答え合わせ用で、アプリのコードとは関係ありません。設計書の解答例が入っているだけなので、アプリの動きには影響しません。詳しくは `answers/README.md` を見てください。

自分で作る設計書は `docs/` に置きます（Tutorial 13 の中で作ります）。

## 使用技術

| 項目           | 内容                   |
| :------------- | :--------------------- |
| フレームワーク | Laravel 10             |
| 言語           | PHP                    |
| データベース   | MySQL                  |
| 実行環境       | Laravel Sail（Docker） |
| 認証           | Laravel Fortify        |

PHP と MySQL のバージョンは Sail のコンテナが決めます。手元で確かめるときは `./vendor/bin/sail php -v` と `./vendor/bin/sail mysql --version` を実行してください。

## セットアップ

Docker Desktop（または Docker Engine）を起動してから実行してください。Windows の方は WSL（Ubuntu）のターミナルで実行します。

```bash
# パッケージをインストールする
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install

# 環境ファイルを作る
cp .env.example .env

# Sail を起動する
./vendor/bin/sail up -d

# アプリケーションキーを作る
./vendor/bin/sail artisan key:generate

# テーブルを作り、練習用データを入れる
./vendor/bin/sail artisan migrate --seed
```

ブラウザで `http://localhost` を開くと、トップページが表示されます。

初回起動時は `migrate` で「Connection refused」が出ることがあります。MySQL の起動が終わっていないだけなので、少し待ってからもう一度実行してください。

## 練習用のアカウント

`migrate --seed` で、カテゴリ 3 件（お知らせ・技術メモ・雑記）と、次の 2 人、それぞれの投稿 2 件（計 4 件）が入ります。

| メールアドレス      | パスワード | 投稿                   |
| :------------------ | :--------- | :--------------------- |
| `usera@example.com` | `password` | `/posts/1`・`/posts/2` |
| `userb@example.com` | `password` | `/posts/3`・`/posts/4` |

本物の値は入っていません。すべて練習用のダミーです。

## テスト

```bash
./vendor/bin/sail artisan test
```

## 停止

```bash
./vendor/bin/sail down
```

このリポジトリは Tutorial 14 でも使います

## 設計のメモ

リプライ機能を、なぜそう作ったかのメモです。

### 作らないと決めたもの

- リプライへのリプライ（入れ子・スレッド）
- リプライの編集
- リプライ件数のタイムライン表示
- ポスト投稿者による他人のリプライの削除。ポストの持ち主でも、消せるのはリプライ投稿者本人のリプライだけ

### 選ばなかった作り方

- 入力チェックに FormRequest は使わない。既存の `PostController` に倣い、コントローラ内の `$request->validate()` で書く
- `ReplyPolicy` を `AuthServiceProvider::$policies` に登録しない。`PostPolicy` と同じく、モデル名からの自動検出に任せる（`$policies` は空のまま）
- 返信はタイムラインからは送らない。詳細ページ（`GET /posts/{post}`）を新設してそこから送り、タイムラインには「返信」リンクを 1 つ足すだけにする

### 知らずに変えると壊れる決めごと

- `replies.post_id` と `replies.user_id` は削除時カスケード。外すと、リプライ付きのポストやユーザーを削除したときに失敗する
- 本文は 140 文字以内。検証の前に CRLF を LF にそろえて数える。ポストの更新（`PostController::update`）と同じ扱いなので、片方だけ変えると食い違う
- 入力エラーのメッセージ（`本文を入力してください。` / `本文は140文字以内で入力してください。`）はポストと同じ文言にそろえてある
- リプライを消せるのは `$user->id === $reply->user_id` の本人だけ。判定は `PostPolicy` と同じ形で、コントローラの `authorize` とビューの `@can` の両方で使っている。他人のリプライは 403
- リプライの並びは新しい順で、`created_at` の降順に `id` の降順を併用している。`id` を外すと、同じ秒のリプライの順序が不安定になる
- ポストの編集・削除の認可（`PostPolicy`）とタイムラインの編集・削除ボタンの出し分けは、リプライの追加では変えていない。ルートはすべて `auth` ミドルウェアのグループ内に置く

### 設計書のありか

- [`docs/reply.md`](docs/reply.md): リプライ機能の設計書（GitHub の Issue #1 の設計）。実装した時点の記録で、そのあとは更新していない。ER 図の元の Mermaid もここにある
- [`docs/reply-er.png`](docs/reply-er.png): `replies` テーブルの ER 図
- `tests/Feature/ReplyTest.php`: 設計書のテスト観点を確かめるテスト
