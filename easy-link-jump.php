<?php
/**
 * Plugin Name: Easy Link Jump
 * Description: a タグに href="#別名" と data-p="リファラ" を設定すると、ホバー時には別名に対応する表示URL(ダミーURL)が表示され、クリック時には「共通URL+広告ファイル名?リファラ」の広告リンクURL(ASP経由)へ遷移します。
 * Version: 5.3.1
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

	const OPTION_BASE  = 'elj_base_url';
	const OPTION_ALIAS = 'elj_aliases';
	const VERSION      = '5.3.1';

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_filter( 'the_content', array( $this, 'replace_href_aliases' ) );
	}

	/**
	 * フロント側:差し替え用スクリプトと共通URLを読み込む。
	 * 広告ファイル名が1つも登録されていなければ読み込まない
	 */
	public function enqueue_front() {
		$base     = get_option( self::OPTION_BASE, '' );
		$aliases  = get_option( self::OPTION_ALIAS, array() );
		$has_file = false;
		foreach ( (array) $aliases as $row ) {
			if ( ! empty( $row['file'] ) ) {
				$has_file = true;
				break;
			}
		}
		if ( ! $has_file ) {
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
			'window.eljConfig = ' . wp_json_encode( array( 'base' => $base ) ) . ';',
			'before'
		);
	}

	/**
	 * href="#アコム" のような別名を、登録済みの表示URL(ダミーURL)へ出力時に変換する。
	 * 広告ファイル名が登録されている場合は、クリック時の組み立てに使う data-f 属性を自動付与する
	 * (遷移先URL自体はソースに出力されない)。
	 * # なしの href="アコム"、エディタがパーセントエンコードして保存した場合にも対応
	 */
	public function replace_href_aliases( $content ) {
		$aliases = get_option( self::OPTION_ALIAS, array() );
		foreach ( (array) $aliases as $row ) {
			if ( ! isset( $row['alias'], $row['url'] ) || '' === $row['alias'] || '' === $row['url'] ) {
				continue;
			}
			$alias = ltrim( $row['alias'], '#' );
			$url   = esc_url( $row['url'] );
			$file  = isset( $row['file'] ) ? $row['file'] : '';

			$replace = 'href="' . $url . '"';
			if ( '' !== $file ) {
				$replace .= ' data-f="' . esc_attr( $file ) . '"';
			}

			$patterns = array();
			foreach ( array_unique( array( $alias, rawurlencode( $alias ) ) ) as $needle ) {
				foreach ( array( '#', '' ) as $prefix ) {
					$patterns[] = 'href="' . $prefix . $needle . '"';
					$patterns[] = "href='" . $prefix . $needle . "'";
				}
			}
			$content = str_replace( $patterns, $replace, $content );
		}
		return $content;
	}

	/**
	 * 設定 → メタモンリンク(リンク登録はすべての記事で共通)
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
	 * 設定ページのフォーム(データはすべての記事で共通)
	 */
	private function render_form() {
		$aliases = get_option( self::OPTION_ALIAS, array() );
		if ( empty( $aliases ) ) {
			$aliases = array( array( 'alias' => '', 'url' => '', 'file' => '' ) );
		}
		$base = get_option( self::OPTION_BASE, '' );
		wp_nonce_field( 'elj_save', 'elj_nonce' );
		?>
		<input type="hidden" name="elj_present" value="1">
		<p>
			記事にはこう書くだけ:<br>
			<code>&lt;a href="#アコム" data-p="リファラ" rel="noopener noreferrer nofollow" target="_blank"&gt;申し込みはこちら&lt;/a&gt;</code><br>
			・href:下の表の「別名」(# 付き)<br>
			・data-p:計測用リファラ(自由に命名、登録不要)<br>
			表示時は href が表示URLに変わり、クリック時は「共通URL+広告ファイル名?リファラ」へ遷移します。
		</p>

		<h4 style="margin-bottom:4px;">共通URL(広告リンクのベースURL)</h4>
		<p style="margin-top:0;">
			<input type="text"
				name="<?php echo esc_attr( self::OPTION_BASE ); ?>"
				value="<?php echo esc_attr( $base ); ?>"
				style="width:100%;max-width:480px;"
				placeholder="例: https://www.smc-g.co.jp/html/ad/">
		</p>

		<h4 style="margin-bottom:4px;">リンク登録(別名 → 表示URL/広告ファイル名)</h4>
		<p class="description" style="margin-top:0;">
			広告ファイル名はファイル名だけでOK(URL全体を書いた場合はそのまま使われます)。
		</p>
		<?php
		$this->render_table(
			'elj-table-aliases',
			self::OPTION_ALIAS,
			array_values( $aliases ),
			array(
				array( 'name' => 'alias', 'label' => '別名(hrefに書く名前)', 'placeholder' => '例: #アコム', 'width' => '24%' ),
				array( 'name' => 'url', 'label' => '表示URL(ダミーURL)', 'placeholder' => '例: https://www.acom.co.jp/', 'width' => '' ),
				array( 'name' => 'file', 'label' => '広告ファイル名', 'placeholder' => '例: acom.html', 'width' => '24%' ),
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

	private function save_options() {
		$base = isset( $_POST[ self::OPTION_BASE ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ self::OPTION_BASE ] ) ) ) : '';
		update_option( self::OPTION_BASE, $base );

		$aliases = isset( $_POST[ self::OPTION_ALIAS ] ) ? wp_unslash( $_POST[ self::OPTION_ALIAS ] ) : array();
		update_option( self::OPTION_ALIAS, $this->sanitize_aliases( $aliases ) );
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
			// 共通URLと組み合わせる相対指定(acom.html など)も許可するため
			// esc_url_raw ではなく sanitize_text_field を使う
			$file = isset( $row['file'] ) ? sanitize_text_field( trim( $row['file'] ) ) : '';
			// 別名は # の有無を区別しない(「#アコム」も「アコム」も同じ扱い)
			$dedup = ltrim( $alias, '#' );
			if ( '' === $alias || '' === $url || isset( $seen[ $dedup ] ) ) {
				continue;
			}
			$seen[ $dedup ] = true;
			$out[]          = array(
				'alias' => $alias,
				'url'   => $url,
				'file'  => $file,
			);
		}
		return $out;
	}
}

new Easy_Link_Jump();
