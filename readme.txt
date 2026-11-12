=== Easy Link Jump ===
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 5.4.2
License: GPL-2.0-or-later

aタグに href="#別名" と data-p="リファラ" を書くと、表示時には別名に対応する
表示URL(ダミーURL)になり、クリック時には「共通URL+広告ファイル名?リファラ」の
広告リンクURL(ASP経由)へ遷移します。リダイレクトは行いません。

== 使い方 ==

1. 記事にはこう書くだけ:

   <a href="#アコム" data-p="nointerest-cardloan_top_table_img"
      rel="noopener noreferrer nofollow" target="_blank">申し込みはこちら</a>

   ・href:登録した別名を # 付きで書く
   ・data-p:計測用リファラ(自由に命名、登録不要)

2. 「設定 → メタモンリンク」で登録する(すべての記事で共通):

   共通URL: https://www.smc-g.co.jp/html/ad/

   別名      | 表示URL(ダミーURL)        | 広告ファイル名
   #アコム   | https://www.acom.co.jp/   | acom.html

3. 結果:

   表示時:   href="https://www.acom.co.jp/"(ホバーで見えるのはこちら)
   クリック時: https://www.smc-g.co.jp/html/ad/acom.html?nointerest-cardloan_top_table_img へ遷移

== ポイント ==

- リファラ(data-p)は登録不要。記事ごとに自由に書けます。
- 広告ファイル名はファイル名だけでOK。URL全体を書いた場合はそのまま使われます
  (「?」が無い場合のみリファラを自動付与)。
- 別名は # の有無どちらでも書けますが、# 付き(href="#アコム")推奨
  (リンクチェックツールでエラーになりません)。
- 左クリック・中クリック・タップ・Enter すべて対応。
  新しいタブで開くかは a タグの target="_blank" で制御します。
- 広告ファイル名が空の行は「ダミーURL変換だけ」の普通のリンクになります。

== 注意 ==

- ソースに出るのは表示URLと広告ファイル名のみで、広告リンクURL全体は出ません。
- JSが無効な環境では表示URL(ダミーURL)のまま遷移します。
- v5.2.x までの「リンク対応表(リファラ→広告リンク)」方式は廃止しました。
  旧方式で書かれた記事は、href="#別名" 形式への書き換えが必要です。
