## posts（投稿）

| カラム名    | データ型     | 制約                       | 説明                       |
| :---------- | :----------- | :------------------------- | :------------------------- |
| id          | BIGINT       | PRIMARY KEY,AUTO_INCREMENT | 投稿を一件ずつ区別する番号 |
| user_id     | BIGINT       | NOT NULL,FOREIGN KEY       | 投稿者                     |
| category_id | BIGINT       | NOT NULL,FOREIGN KEY       | 投稿のカテゴリ             |
| title       | VARCHAR(255) | NOT NULL                   | 投稿の題名                 |
| content     | TEXT         | NOT NULL                   | 投稿の本文                 |
| create_at   | TIMESTAMP    | -                          | 投稿した日時               |
| update_at   | TIMESTAMP    | -                          | 更新した日時               |
