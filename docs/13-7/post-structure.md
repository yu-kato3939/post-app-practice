## アプリの構造設計

| まとまり       | 実物の場所                                  | 置くもの                                                        |
| :------------- | :------------------------------------------ | :-------------------------------------------------------------- |
| ルーティング   | routes/web.php                              | どのURLがどのコントローラーの何を呼ぶのか。ログインは必要なのか |
| コントローラー | app/Http/Controllers/Postontroller.php      | データをどう扱い、どの画面に返すのか                            |
| 認可           | app/Polices/PostPoliy.php                   | 誰が投稿を編集、削除して良いのか                                |
| モデル         | app/Models/User.php・Post.php・Category.php | データの読み書き、テーブルのつながりについて                    |
| ビュー         | resources/views/posts.index.blade.php       | 画面に並べるもの                                                |
| データベース   | users・category・postsテーブル              | 覚えておくデータの中身                                          |
