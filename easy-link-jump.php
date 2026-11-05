<?php
/**
 * Plugin Name: Easy Link Jump
 * Description: a タグに data-p="リファラ" を設定すると、ホバー時には href のURLが表示され、クリック時にはリファラに対応する広告リンクURL(ASP経由)へ遷移します。
 * Version: 5.2.1
 * Author: -
 * License: GPL-2.0-or-later
 * Update URI: https://github.com/terasakisd/easy-link-jump/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 自動アップデート(GitHubリリース連携 / Plugin Update Checker 同梱)
 * リポジトリ terasakisd/easy-link-jump に新しいリリース(vX.Y.Z タグ)を作ると、
 * このプラグインを入れた全サイトのプラグイン一覧に「更新があります」が表示される。
 * ---------------------------------------------------------------------- */
if ( file_exists( __DIR__ . '/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
	YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/terasakisd/easy-link-jump/',
		__FILE__,
		'easy-link-jump'
	);
}

class Easy_Link_Jump {

	const OPTION       = 'elj_links';
	const OPTION_BASE  = 'elj_base_url';
	const OPTION_ALIAS = 'elj_aliases';
	const VERSION      = '5.2.1';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_filter( 'the_content', array( $this, 'replace_href_aliases' ) );
	}

	/**
	 * フロント側:差し替え用スクリプトと登録データを読み込む
	 */
	public function enqueue_front() {
		$links = get_option( self::OPTION, array() );
		$base  = get_option( self::OPTION_BASE, '' );
		$rows  = array();
		foreach ( (array) $links as $link ) {
			if ( ! isset( $link['key'], $link['url'] ) || '' === $link['key'] || '' === $link['url'] ) {
				continue;
			}
			$rows[] = array(
				'key' => $link['key'],
				'url' => $this->compose_url( $link['url'], $link['key'], $base ),
			);
		}
		if ( empty( $rows ) ) {
			return;
		}

		wp_enqueue_script(
			'easy-link-jump',
			plugins_url( 'easy-link-jump.js', __FILE__ ),
			array(),
			self::VERSION,
			true
		);
		wp_add_inline_script(
			'easy-link-jump',
			'window.eljConfig = ' . wp_json_encode( array( 'links' => $rows ) ) . ';',
			'before'
		);
	}

	/**
	 * 広告リンクの最終URLを組み立てる。
	 * - http(s) から始まらない入力は「共通URL+入力値」として扱う
	 * - 「?」を含まない場合はリファラをクエリとして自動付与する
	 *   (例: 共通URL https://example.com/ad/ + acom262.html + リファラ xxx
	 *     → https://example.com/ad/acom262.html?xxx)
	 */
	public function compose_url( $url, $key, $base ) {
		if ( ! preg_match( '#^https?://#i', $url ) && '' !== $base ) {
			$url = rtrim( $base, '/' ) . '/' . ltrim( $url, '/' );
		}
		if ( false === strpos( $url, '?' ) && '' !== $key ) {
			$url .= '?' . $key;
		}
		return $url;
	}

	/**
	 * href="アコム" のような別名を、登録済みの表示URL(ダミーURL)へ出力時に変換する
	 */
	public function replace_href_aliases( $content ) {
		$aliases = get_option( self::OPTION_ALIAS, array() );
		foreach ( (array) $aliases as $row ) {
			if ( ! isset( $row['alias'], $row['url'] ) || '' === $row['alias'] || '' === $row['url'] ) {
				continue;
			}
			$url     = esc_url( $row['url'] );
			$content = str_replace(
				array( 'href="' . $row['alias'] . '"', "href='" . $row['alias'] . "'" ),
				'href="' . $url . '"',
				$content
			);
		}
		return $content;
	}

	/**
	 * 投稿編集画面:エディタ下のメタボックス(priority: high で上寄せ)
	 */
	public function add_meta_box() {
		foreach ( array( 'post', 'page' ) as $post_type ) {
			add_meta_box(
				'easy-link-jump',
				'メタモンリンク(Easy Link Jump)',
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	public function render_meta_box() {
		$this->render_form();
	}

	/**
	 * 設定 → メタモンリンク(投稿画面を開かなくても対応表を編集できる)
	 */
	public function add_settings_page() {
		add_options_page(
			'メタモンリンク',
			'メタモンリンク',
			'manage_options',
			'easy-link-jump',
			array( $this, 'render_settings_page' )
		);
	}

	public function render_settings_page() {
		if ( isset( $_POST['elj_present'] ) ) {
			check_admin_referer( 'elj_save', 'elj_nonce' );
			if ( current_user_can( 'manage_options' ) ) {
				$this->save_options();
				echo '<div class="notice notice-success is-dismissible"><p>保存しました。</p></div>';
			}
		}
		?>
		<div class="wrap">
			<h1>メタモンリンク(Easy Link Jump)</h1>
			<form method="post">
				<?php $this->render_form(); ?>
				<?php submit_button( '変更を保存' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * メタボックス/設定ページ共通のフォーム
	 */
	private function render_form() {
		$links = get_option( self::OPTION, array() );
		if ( empty( $links ) ) {
			$links = array( array( 'key' => '', 'url' => '' ) );
		}
		$aliases = get_option( self::OPTION_ALIAS, array() );
		if ( empty( $aliases ) ) {
			$aliases = array( array( 'alias' => '', 'url' => '' ) );
		}
		$base = get_option( self::OPTION_BASE, '' );
		wp_nonce_field( 'elj_save', 'elj_nonce' );
		?>
		<input type="hidden" name="elj_present" value="1">
		<p>
			aタグに <code>data-p="リファラ"</code> を設定すると、ホバー時にはhrefに指定したURLが表示されますが、クリック時にはリファラに対応する広告リンクURL(ASP経由)へ遷移します。<br>
			・href:ホバー時に表示させるURL(ダミーURL)を設定<br>
			・data-p:遷移先の広告リンクに対応するリファラを設定<br>
			例: <code>&lt;a href="https://www.acom.co.jp/" data-p="cardloan_osusume_btn" rel="noopener noreferrer nofollow" target="_blank"&gt;申し込みはこちら&lt;/a&gt;</code>
		</p>

		<h4 style="margin-bottom:4px;">共通URL(広告リンクのベースURL)</h4>
		<p style="margin-top:0;">
			<input type="text"
				name="<?php echo esc_attr( self::OPTION_BASE ); ?>"
				value="<?php echo esc_attr( $base ); ?>"
				style="width:100%;max-width:480px;"
				placeholder="例: https://www.smc-g.co.jp/html/ad/">
		</p>
		<h4 style="margin-bottom:4px;">リンク対応表(リファラ → 広告リンク)</h4>
		<?php
		$this->render_table(
			'elj-table-links',
			self::OPTION,
			array_values( $links ),
			array(
				array( 'name' => 'key', 'label' => 'リファラ', 'placeholder' => '例: cardloan_osusume_btn', 'width' => '38%' ),
				array( 'name' => 'url', 'label' => '広告リンク(ファイル名 または URL全体)', 'placeholder' => '例: acom262.html', 'width' => '' ),
			)
		);
		?>

		<h4 style="margin-bottom:4px;">ダミーURL変換(hrefの別名)</h4>
		<p class="description" style="margin-top:0;">
			記事内で <code>href="アコム"</code> のように別名で書くと、ページの表示時に登録した表示URLへ自動で変換されます。<br>
			例: 別名「アコム」/ 表示URL「https://www.acom.co.jp/」 → <code>href="アコム"</code> が <code>href="https://www.acom.co.jp/"</code> になります。
		</p>
		<?php
		$this->render_table(
			'elj-table-aliases',
			self::OPTION_ALIAS,
			array_values( $aliases ),
			array(
				array( 'name' => 'alias', 'label' => '別名', 'placeholder' => '例: アコム', 'width' => '38%' ),
				array( 'name' => 'url', 'label' => '表示URL(ダミーURL)', 'placeholder' => '例: https://www.acom.co.jp/', 'width' => '' ),
			)
		);
		?>
		<script>
		(function () {
			if (window._eljAdminInit) { return; }
			window._eljAdminInit = true;

			document.addEventListener('click', function (e) {
				var t = e.target;
				if (!t || !t.classList) { return; }

				if (t.classList.contains('elj-remove')) {
					t.closest('tr').remove();
					return;
				}
				if (t.classList.contains('elj-add')) {
					var table  = document.getElementById(t.getAttribute('data-target'));
					var tbody  = table.querySelector('tbody');
					var option = table.getAttribute('data-option');
					var fields = JSON.parse(table.getAttribute('data-fields'));
					var i      = parseInt(table.getAttribute('data-counter'), 10);
					table.setAttribute('data-counter', i + 1);

					var tr   = document.createElement('tr');
					var html = '';
					fields.forEach(function (f) {
						html += '<td><input type="text" name="' + option + '[' + i + '][' + f.name + ']" style="width:100%" placeholder="' + f.placeholder + '"></td>';
					});
					html += '<td><button type="button" class="button elj-remove">削除</button></td>';
					tr.innerHTML = html;
					tbody.appendChild(tr);
				}
			});
		})();
		</script>
		<?php
	}

	private function render_table( $id, $option, $rows, $fields ) {
		?>
		<table class="widefat striped elj-table"
			id="<?php echo esc_attr( $id ); ?>"
			data-option="<?php echo esc_attr( $option ); ?>"
			data-fields="<?php echo esc_attr( wp_json_encode( $fields ) ); ?>"
			data-counter="<?php echo count( $rows ); ?>">
			<thead>
				<tr>
					<?php foreach ( $fields as $field ) : ?>
					<th <?php echo $field['width'] ? 'style="width:' . esc_attr( $field['width'] ) . ';"' : ''; ?>>
						<?php echo esc_html( $field['label'] ); ?>
					</th>
					<?php endforeach; ?>
					<th style="width: 70px;"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $i => $row ) : ?>
				<tr>
					<?php foreach ( $fields as $field ) : ?>
					<td>
						<input type="text"
							name="<?php echo esc_attr( $option ); ?>[<?php echo (int) $i; ?>][<?php echo esc_attr( $field['name'] ); ?>]"
							value="<?php echo esc_attr( isset( $row[ $field['name'] ] ) ? $row[ $field['name'] ] : '' ); ?>"
							style="width: 100%;"
							placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>">
					</td>
					<?php endforeach; ?>
					<td>
						<button type="button" class="button elj-remove">削除</button>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button elj-add" data-target="<?php echo esc_attr( $id ); ?>">行を追加</button>
		</p>
		<?php
	}

	/**
	 * 記事の保存時に対応表も保存する(データはサイト共通)
	 */
	public function save_meta_box( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['elj_nonce'] ) || ! wp_verify_nonce( $_POST['elj_nonce'], 'elj_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['elj_present'] ) ) {
			return;
		}
		$this->save_options();
	}

	private function save_options() {
		$links = isset( $_POST[ self::OPTION ] ) ? wp_unslash( $_POST[ self::OPTION ] ) : array();
		update_option( self::OPTION, $this->sanitize_links( $links ) );

		$base = isset( $_POST[ self::OPTION_BASE ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ self::OPTION_BASE ] ) ) ) : '';
		update_option( self::OPTION_BASE, $base );

		$aliases = isset( $_POST[ self::OPTION_ALIAS ] ) ? wp_unslash( $_POST[ self::OPTION_ALIAS ] ) : array();
		update_option( self::OPTION_ALIAS, $this->sanitize_aliases( $aliases ) );
	}

	public function sanitize_links( $input ) {
		$out  = array();
		$seen = array();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = isset( $row['key'] ) ? sanitize_text_field( trim( $row['key'] ) ) : '';
			// 共通URLと組み合わせる相対指定(acom262.html など)も許可するため
			// esc_url_raw ではなく sanitize_text_field を使う
			$url = isset( $row['url'] ) ? sanitize_text_field( trim( $row['url'] ) ) : '';
			// 同じリファラの複数登録はOK(ランダム振り分け用)。key+url が完全に同じ行だけ除外
			$pair = $key . ' ' . $url;
			if ( '' === $key || '' === $url || isset( $seen[ $pair ] ) ) {
				continue;
			}
			$seen[ $pair ] = true;
			$out[]         = array(
				'key' => $key,
				'url' => $url,
			);
		}
		return $out;
	}

	public function sanitize_aliases( $input ) {
		$out  = array();
		$seen = array();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$alias = isset( $row['alias'] ) ? sanitize_text_field( trim( $row['alias'] ) ) : '';
			$url   = isset( $row['url'] ) ? esc_url_raw( trim( $row['url'] ) ) : '';
			if ( '' === $alias || '' === $url || isset( $seen[ $alias ] ) ) {
				continue;
			}
			$seen[ $alias ] = true;
			$out[]          = array(
				'alias' => $alias,
				'url'   => $url,
			);
		}
		return $out;
	}
}

new Easy_Link_Jump();
