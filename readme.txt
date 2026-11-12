=== Easy Link Jump ===
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 5.2.2
License: GPL-2.0-or-later

aタグに data-p="リファラ" を設定すると、ホバー時には href に指定したURLが
表示されますが、クリック時にはリファラに対応する広告リンクURL(ASP経由)へ
遷移します。リダイレクトは行いません。

・href:ホバー時に表示させるURL(ダミーURL)を設定
・data-p:遷移先の広告リンクに対応するリファラを設定

== 仕組み ==

1. 記事内の a タグにダミーURLと data-p 属性(リファラ)を書く:

   <a href="https://www.acom.co.jp/"
      data-p="cardloan_osusume_btn"
      rel="noopener noreferrer nofollow" target="_blank">申し込みはこちら</a>

2. 「設定 → メタモンリンク」(または投稿編集画面のメタボックス)で
   「リファラ」→「広告リンク」の対応を登録する(対応表はサイト共通):

   共通URL:   https://www.smc-g.co.jp/html/ad/
   リファラ:   cardloan_osusume_btn
   広告リンク: acom.html

   → クリック時の遷移先は
      https://www.smc-g.co.jp/html/ad/acom.html?cardloan_osusume_btn
      に自動で組み立てられます(リファラの2度書き不要)。

3. クリックした瞬間にJSが href をリファラに対応するURLへ差し替えて遷移。
   遷移直後に href はダミーへ自動で戻ります。

== 広告リンク欄の書き方 ==

- ファイル名だけ(例: acom.html)
  → 共通URL+ファイル名?リファラ に自動で組み立て
- http(s) から始まるURL全体
  → そのまま使用(「?」が無い場合のみリファラを自動付与)
- 空欄
  → リファラ自体を「ファイル名?リファラ」とみなして
    共通URL+リファラ に組み立て
    (例: リファラ acom.html?cardloan_osusume_btn
      → https://www.smc-g.co.jp/html/ad/acom.html?cardloan_osusume_btn。
    記事の data-p にも同じ値を書く)

== ダミーURL変換(hrefの別名) ==

href="アコム" のように別名で書くと、ページ表示時に登録した表示URLへ
自動変換されます(例: アコム → https://www.acom.co.jp/)。
ホバー時に表示されるダミーURLの書き間違いを防げます。

リンクチェックツールで「URLの形式が不正」とエラーになる場合は、
href="#アコム" のように先頭に # を付けて書いてください
(同じように変換され、チェックにも引っかかりません)。

== ポイント ==

- href のダミーURLは自由(ホバー時・ソース上に見えるのはダミーURLだけ)。
- 同じリファラの行を複数登録すると、クリックごとにランダムで
  振り分けられます(A/Bテスト・ローテーション用)。
- 左クリック・中クリック・タップ・Enter すべて対応。
  新しいタブで開くかは a タグの target="_blank" で制御します。
- 対応表は「設定 → メタモンリンク」からも編集できます
  (投稿画面を開いて最下部までスクロールする必要はありません)。

== 注意 ==

- 対応表(リファラ→URL)はページのインラインJSに含まれるため、
  ソースを解析すればリンク先は分かります(href としては出ません)。
- JSが無効な環境ではダミーURLのまま遷移するため、
  ダミーURLは実在するページ(公式サイトなど)にしておくとより安全です。
