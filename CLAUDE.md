# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 概要

投稿を一覧・編集・削除できる小さな Laravel 10 アプリ（チュートリアル教材）。Tutorial 13〜15 でこのアプリ 1 本を題材に設計・実装・仕組み化を進める。Tutorial 13 は設計書づくり、Tutorial 14 以降でこのアプリに機能を足していく。

## コマンド

すべて Laravel Sail（Docker）経由で実行する。ホストの `php` / `composer` は前提にしない。

```bash
./vendor/bin/sail up -d                        # 起動（http://localhost）
./vendor/bin/sail artisan migrate --seed       # テーブル作成＋練習用データ
./vendor/bin/sail artisan migrate:fresh --seed # DB を作り直す
./vendor/bin/sail artisan test                 # 全テスト
./vendor/bin/sail artisan test --filter=テスト名 # 1 件だけ（クラス名・メソッド名で絞る）
./vendor/bin/sail artisan test tests/Feature/ExampleTest.php
./vendor/bin/sail pint                         # コード整形（Laravel Pint）
./vendor/bin/sail down                         # 停止
```

- テストは `phpunit.xml` で `DB_DATABASE=testing` を使う（Sail の MySQL コンテナ初回起動時に `testing` DB が自動作成される）。
- 初回の `migrate` で「Connection refused」が出たら MySQL の起動待ち。少し待って再実行する。
- 練習用アカウント（シーダー）: `usera@example.com` / `userb@example.com`、パスワードはどちらも `password`。各自 2 件ずつ投稿あり（user A が `/posts/1`・`/posts/2`、user B が `/posts/3`・`/posts/4`）。

## アーキテクチャ

- **認証は Laravel Fortify**。ログイン・登録・ログアウトのルートは Fortify が登録するので `routes/web.php` には無い。ビューの紐づけは `app/Providers/FortifyServiceProvider.php`（`auth.login` / `auth.register`）、登録処理は `app/Actions/Fortify/CreateNewUser.php`。ログイン後の遷移先は `config/fortify.php` の `home`（`/posts`）。
- **投稿機能**は `PostController`（index / edit / update / destroy）だけ。新規作成・詳細表示のルートはまだ無い。ルートはすべて `auth` ミドルウェアのグループ内。
- **認可は `PostPolicy`**（`update` / `delete` とも「投稿者本人のみ」）。`AuthServiceProvider::$policies` は空で、Policy はモデル名からの自動検出に頼っている。コントローラ側で `$this->authorize()` を呼び、他人の投稿は 403。ビュー側でも編集・削除ボタンを本人にだけ出している。
- **データ**: `users` 1—* `posts` *—1 `categories`。`posts.user_id` はユーザー削除でカスケード削除、`category_id` は制約のみ。バリデーションはコントローラ内の `$request->validate()`（FormRequest は未使用）。
- **ビュー**は `resources/views/` の Blade。共通レイアウトは無く、各ビューが独立した HTML でスタイルをインライン `<style>` に持つ。Vite 用のアセットは実質使っていない。
- テストは `tests/` にひな形の ExampleTest しかない。

## リポジトリ内のフォルダの扱い

- `docs/` — 学習者が Tutorial 13 で作った設計書（章ごとに `docs/13-N/`）。Markdown と draw.io（`.drawio` と書き出した `.png`）。ファイル名の接頭辞が題材を表す: `post-` はこの投稿アプリ、`cafe-` はカフェのモバイルオーダー演習、`expense-` は経費精算アプリ演習、`register-` は会員登録のテスト設計。機能を足すときは `post-` の設計書（API・権限・ER・テスト観点など）が参考になる。
- `answers/` — Tutorial 13 の解答例。**アプリのコードとは無関係**で、機能追加の際に読む必要はない。編集もしない。
