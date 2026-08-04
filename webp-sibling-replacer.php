<?php
/**
 * Plugin Name: WebP 同目录替换器
 * Description: 扫描文章正文和特色图；当同目录存在 WebP 文件时，可一键改用 WebP，并列出可人工删除的原 PNG/JPG 文件。
 * Version: 1.0.4
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Codex
 * License: GPL-2.0-or-later
 * Text Domain: webp-sibling-replacer
 * Update URI: https://github.com/maomomo-eth/webp-sibling-replacer
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
require_once __DIR__ . '/includes/class-wsr-github-updater.php';

WSR_GitHub_Updater::init( __FILE__ );

final class WSR_WebP_Sibling_Replacer {
	const SLUG        = 'webp-sibling-replacer';
	const VERSION     = '1.0.4';
	const SCAN_BATCH  = 5;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'wp_ajax_wsr_scan_batch', array( $this, 'ajax_scan_batch' ) );
	}

	public function add_menu() {
		add_management_page( 'WebP 同目录替换器', 'WebP 同目录替换器', 'edit_others_posts', self::SLUG, array( $this, 'render_page' ) );
	}

	public function render_page() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( '您没有权限使用此工具。' );
		}

		$results = $this->scan_results();
		$items   = $results['replacements'];
		$notice = null;
		if ( isset( $_POST['wsr_replace'] ) ) {
			check_admin_referer( 'wsr_replace_webp' );
			$selected_keys = isset( $_POST['wsr_items'] ) && is_array( $_POST['wsr_items'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['wsr_items'] ) ) : array();
			$selected_items = array_filter(
				$items,
				function ( $item ) use ( $selected_keys ) {
					return in_array( $this->item_key( $item ), $selected_keys, true );
				}
			);
			$notice = empty( $selected_items ) ? '请至少勾选一项后再执行。' : $this->replace_selected( $selected_items, $items, ! empty( $_POST['wsr_delete_originals'] ) );
			delete_transient( $this->scan_cache_key() );
		}
		?>
		<div class="wrap">
			<h1>WebP 同目录替换器</h1>
			<p>扫描已发布和草稿文章/页面的正文图片及特色图。扫描采用后台分批处理，不会阻塞当前页面。</p>
			<p><button type="button" id="wsr-start-scan" class="button button-secondary">开始扫描</button> <span id="wsr-scan-progress"></span></p>
			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<h2>可替换为 WebP 的图片</h2>
			<p id="wsr-replacement-summary">点击“开始扫描”查看最新结果。</p>
			<form method="post">
					<?php wp_nonce_field( 'wsr_replace_webp' ); ?>
					<p><label><input type="checkbox" name="wsr_delete_originals" value="1"> 替换成功后，同时删除所选原图</label></p>
					<p class="description">删除不可恢复。若同一原图还有未勾选的扫描引用，插件会跳过删除以避免造成断图。</p>
					<p><button type="submit" class="button button-primary" name="wsr_replace" value="1">替换所选项目</button></p>
					<table class="widefat striped">
						<thead><tr><th><input type="checkbox" id="wsr-select-all" aria-label="全选"></th><th>文章</th><th>位置</th><th>原图</th><th>将改为</th><th>原文件提示</th></tr></thead>
					<tbody id="wsr-replacements"></tbody>
				</table>
				</form>
			<h2>失效图片</h2>
			<p id="wsr-missing-summary">点击“开始扫描”查看结果。</p>
				<table class="widefat striped">
					<thead><tr><th>文章</th><th>位置</th><th>图片地址/文件</th><th>问题</th></tr></thead>
					<tbody id="wsr-missing"></tbody>
				</table>
			<p class="description">提示：WebP 替换仅处理 WordPress 上传目录中的本地 PNG、JPG、JPEG；失效扫描还会检查外链图片的 HTTP 状态。</p>
			<script>
			(function(){
				var button=document.getElementById('wsr-start-scan'), progress=document.getElementById('wsr-scan-progress'), replacements=document.getElementById('wsr-replacements'), missing=document.getElementById('wsr-missing'), page=1, replacementCount=0, missingCount=0;
				document.getElementById('wsr-select-all').addEventListener('change',function(){document.querySelectorAll('.wsr-item').forEach(function(item){item.checked=this.checked;},this);});
				function scan(){var data=new URLSearchParams({action:'wsr_scan_batch',nonce:'<?php echo esc_js( wp_create_nonce( 'wsr_async_scan' ) ); ?>',page:page});fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:data}).then(function(response){return response.json();}).then(function(response){if(!response.success){throw new Error(response.data||'扫描失败');}replacements.insertAdjacentHTML('beforeend',response.data.replacement_rows);missing.insertAdjacentHTML('beforeend',response.data.missing_rows);replacementCount+=response.data.replacement_count;missingCount+=response.data.missing_count;progress.textContent='已扫描 '+response.data.processed+' / '+response.data.total+' 篇';if(response.data.done){button.disabled=false;button.textContent='重新扫描';document.getElementById('wsr-replacement-summary').textContent='找到 '+replacementCount+' 个可替换引用。';document.getElementById('wsr-missing-summary').textContent=missingCount ? '发现 '+missingCount+' 个失效图片引用。' : '未发现失效的正文图片或特色图。';return;}page++;scan();}).catch(function(error){button.disabled=false;progress.textContent='扫描失败：'+error.message;});}
				button.addEventListener('click',function(){page=1;replacementCount=0;missingCount=0;replacements.innerHTML='';missing.innerHTML='';button.disabled=true;button.textContent='扫描中…';progress.textContent='正在准备扫描…';scan();});
			})();
			</script>
		</div>
		<?php
	}

	private function scan() {
		$items = array();
		foreach ( $this->scannable_posts() as $post ) {
			$items = array_merge( $items, $this->scan_post( $post ) );
		}
		return $items;
	}

	private function scan_post( $post ) {
		$items = array();
		foreach ( $this->find_content_images( $post->post_content ) as $url ) {
			$pair = $this->sibling_webp( $url );
			if ( $pair ) {
				$items[] = array_merge( $pair, array( 'post_id' => $post->ID, 'kind' => 'content' ) );
			}
		}
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		if ( $thumbnail_id ) {
			$original = get_attached_file( $thumbnail_id );
			if ( $original && $this->is_supported_original( $original ) ) {
				$webp = preg_replace( '/\.(png|jpe?g)$/i', '.webp', $original );
				if ( $webp && is_file( $webp ) ) {
					$items[] = array( 'post_id' => $post->ID, 'kind' => 'featured', 'original_url' => wp_get_attachment_url( $thumbnail_id ), 'webp_url' => $this->file_to_upload_url( $webp ), 'original_file' => $original, 'webp_file' => $webp, 'thumbnail_id' => $thumbnail_id );
				}
			}
		}
		return $items;
	}

	private function scannable_posts() {
		return get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'all',
			)
		);
	}

	private function scan_missing() {
		$items = array();
		foreach ( $this->scannable_posts() as $post ) {
			$items = array_merge( $items, $this->scan_missing_post( $post ) );
		}
		return $items;
	}

	private function scan_missing_post( $post ) {
		$items = array();
		foreach ( $this->find_content_images( $post->post_content ) as $url ) {
			$status = $this->missing_image_status( $url );
			if ( $status ) {
				$items[] = array( 'post_id' => $post->ID, 'kind' => 'content', 'source' => $url, 'status' => $status );
			}
		}
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		if ( $thumbnail_id ) {
			$file = get_attached_file( $thumbnail_id );
			if ( ! $file || ! is_file( $file ) ) {
				$items[] = array( 'post_id' => $post->ID, 'kind' => 'featured', 'source' => $file ?: ( wp_get_attachment_url( $thumbnail_id ) ?: '媒体附件 #' . $thumbnail_id ), 'status' => '本地特色图文件不存在（404）' );
			}
		}
		return $items;
	}

	public function ajax_scan_batch() {
		check_ajax_referer( 'wsr_async_scan', 'nonce' );
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( '您没有权限扫描文章。', 403 );
		}
		$page = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		if ( 1 === $page ) {
			delete_transient( $this->scan_cache_key() );
		}
		$query = new WP_Query(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => self::SCAN_BATCH,
				'paged'          => $page,
				'no_found_rows'  => false,
			)
		);
		$replacement_items = array();
		$missing_items     = array();
		foreach ( $query->posts as $post ) {
			$replacement_items = array_merge( $replacement_items, $this->scan_post( $post ) );
			$missing_items     = array_merge( $missing_items, $this->scan_missing_post( $post ) );
		}
		$results = $this->scan_results();
		$results['replacements'] = array_merge( $results['replacements'], $replacement_items );
		$results['missing']      = array_merge( $results['missing'], $missing_items );
		set_transient( $this->scan_cache_key(), $results, HOUR_IN_SECONDS );
		wp_send_json_success(
			array(
				'replacement_rows'  => $this->replacement_rows_html( $replacement_items ),
				'missing_rows'      => $this->missing_rows_html( $missing_items ),
				'replacement_count' => count( $replacement_items ),
				'missing_count'     => count( $missing_items ),
				'processed'         => min( $page * self::SCAN_BATCH, (int) $query->found_posts ),
				'total'             => (int) $query->found_posts,
				'done'              => $page >= (int) $query->max_num_pages,
			)
		);
	}

	private function scan_cache_key() {
		return 'wsr_scan_results_' . get_current_user_id();
	}

	private function scan_results() {
		$results = get_transient( $this->scan_cache_key() );
		if ( ! is_array( $results ) ) {
			return array( 'replacements' => array(), 'missing' => array() );
		}
		$results['replacements'] = isset( $results['replacements'] ) && is_array( $results['replacements'] ) ? $results['replacements'] : array();
		$results['missing']      = isset( $results['missing'] ) && is_array( $results['missing'] ) ? $results['missing'] : array();
		return $results;
	}

	private function replacement_rows_html( $items ) {
		ob_start();
		foreach ( $items as $item ) {
			?>
			<tr>
				<td><input type="checkbox" class="wsr-item" name="wsr_items[]" value="<?php echo esc_attr( $this->item_key( $item ) ); ?>" aria-label="选择此项目"></td>
				<td><a href="<?php echo esc_url( get_edit_post_link( $item['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( $item['post_id'] ) ?: '(无标题)' ); ?></a></td>
				<td><?php echo esc_html( 'featured' === $item['kind'] ? '特色图' : '正文图片' ); ?></td>
				<td><code><?php echo esc_html( $item['original_url'] ); ?></code></td>
				<td><code><?php echo esc_html( $item['webp_url'] ); ?></code></td>
				<td>替换成功后可人工删除：<code><?php echo esc_html( $item['original_file'] ); ?></code></td>
			</tr>
			<?php
		}
		return ob_get_clean();
	}

	private function missing_rows_html( $items ) {
		ob_start();
		foreach ( $items as $item ) {
			?>
			<tr>
				<td><a href="<?php echo esc_url( get_edit_post_link( $item['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( $item['post_id'] ) ?: '(无标题)' ); ?></a></td>
				<td><?php echo esc_html( 'featured' === $item['kind'] ? '特色图' : '正文图片' ); ?></td>
				<td><code><?php echo esc_html( $item['source'] ); ?></code></td>
				<td><?php echo esc_html( $item['status'] ); ?></td>
			</tr>
			<?php
		}
		return ob_get_clean();
	}

	private function find_content_images( $content ) {
		if ( ! class_exists( 'DOMDocument' ) || false === stripos( $content, '<img' ) ) {
			return array();
		}
		$previous = libxml_use_internal_errors( true );
		$dom      = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $content );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$urls = array();
		foreach ( $dom->getElementsByTagName( 'img' ) as $image ) {
			if ( $image->hasAttribute( 'src' ) ) {
				$urls[] = $image->getAttribute( 'src' );
			}
		}
		return array_values( array_unique( array_filter( $urls ) ) );
	}

	private function sibling_webp( $url ) {
		$original = $this->local_upload_file( $url );
		if ( ! $original || ! $this->is_supported_original( $original ) ) {
			return false;
		}
		$webp     = preg_replace( '/\.(png|jpe?g)$/i', '.webp', $original );
		if ( ! $webp || ! is_file( $original ) || ! is_file( $webp ) ) {
			return false;
		}
		$webp_url = preg_replace( '/\.(png|jpe?g)(?=([?#]|$))/i', '.webp', $url );
		return array( 'original_url' => $url, 'webp_url' => $webp_url, 'original_file' => $original, 'webp_file' => $webp );
	}

	private function local_upload_file( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['path'] ) ) {
			return false;
		}
		$upload      = wp_get_upload_dir();
		$upload_host = wp_parse_url( $upload['baseurl'], PHP_URL_HOST );
		if ( ! empty( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( $upload_host ) ) {
			return false;
		}
		$base = wp_parse_url( $upload['baseurl'], PHP_URL_PATH );
		if ( 0 !== strpos( rawurldecode( $parts['path'] ), $base . '/' ) ) {
			return false;
		}
		$relative = ltrim( substr( rawurldecode( $parts['path'] ), strlen( $base ) ), '/' );
		return wp_normalize_path( $upload['basedir'] . '/' . $relative );
	}

	private function missing_image_status( $url ) {
		$local_file = $this->local_upload_file( $url );
		if ( $local_file ) {
			return is_file( $local_file ) ? false : '本地文件不存在（404）';
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return false;
		}
		$key    = 'wsr_image_status_' . md5( $url );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return 'ok' === $cached ? false : $cached;
		}
		$response = wp_safe_remote_head( $url, array( 'timeout' => 5, 'redirection' => 3 ) );
		if ( is_wp_error( $response ) ) {
			$status = '远程请求失败：' . $response->get_error_message();
		} else {
			$code   = (int) wp_remote_retrieve_response_code( $response );
			$status = $code >= 400 ? '远程图片返回 HTTP ' . $code : false;
		}
		set_transient( $key, $status ?: 'ok', 6 * HOUR_IN_SECONDS );
		return $status;
	}

	private function is_supported_original( $file ) {
		return (bool) preg_match( '/\.(png|jpe?g)$/i', (string) $file );
	}

	private function file_to_upload_url( $file ) {
		$upload = wp_get_upload_dir();
		$relative = ltrim( substr( wp_normalize_path( $file ), strlen( wp_normalize_path( $upload['basedir'] ) ) ), '/' );
		return $upload['baseurl'] . '/' . str_replace( ' ', '%20', $relative );
	}

	private function item_key( $item ) {
		return md5( $item['post_id'] . '|' . $item['kind'] . '|' . $item['original_url'] . '|' . $item['webp_file'] );
	}

	private function replace_selected( $items, $all_items, $delete_originals ) {
		$content_updates = array();
		$featured_updates = array();
		$selected_files = array();
		$replaced_files = array();
		foreach ( $items as $item ) {
			$selected_files[ $item['original_file'] ] = isset( $selected_files[ $item['original_file'] ] ) ? $selected_files[ $item['original_file'] ] + 1 : 1;
			if ( 'content' === $item['kind'] ) {
				$content_updates[ $item['post_id'] ][ $item['original_url'] ] = $item['webp_url'];
			} else {
				$featured_updates[ $item['post_id'] ] = $item;
			}
		}
		$changed = 0;
		foreach ( $content_updates as $post_id => $replacements ) {
			$post = get_post( $post_id );
			$new_content = strtr( $post->post_content, $replacements );
			if ( $new_content !== $post->post_content ) {
				$result = wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_content ), true );
				if ( ! is_wp_error( $result ) ) {
					$changed++;
					foreach ( $items as $item ) {
						if ( 'content' === $item['kind'] && (int) $post_id === (int) $item['post_id'] ) {
							$replaced_files[ $item['original_file'] ] = isset( $replaced_files[ $item['original_file'] ] ) ? $replaced_files[ $item['original_file'] ] + 1 : 1;
						}
					}
				}
			}
		}
		foreach ( $featured_updates as $post_id => $item ) {
			$webp_id = $this->attachment_for_file( $item['webp_file'] );
			if ( $webp_id && set_post_thumbnail( $post_id, $webp_id ) ) {
				$changed++;
				$replaced_files[ $item['original_file'] ] = isset( $replaced_files[ $item['original_file'] ] ) ? $replaced_files[ $item['original_file'] ] + 1 : 1;
			}
		}
		$deleted = 0;
		$skipped = 0;
		if ( $delete_originals ) {
			$all_file_counts = array();
			foreach ( $all_items as $item ) {
				$all_file_counts[ $item['original_file'] ] = isset( $all_file_counts[ $item['original_file'] ] ) ? $all_file_counts[ $item['original_file'] ] + 1 : 1;
			}
			foreach ( $selected_files as $file => $selected_count ) {
				if ( $selected_count === $all_file_counts[ $file ] && isset( $replaced_files[ $file ] ) && $selected_count === $replaced_files[ $file ] && is_file( $file ) ) {
					wp_delete_file( $file );
					if ( ! file_exists( $file ) ) {
						$deleted++;
						continue;
					}
				}
				$skipped++;
			}
		}
		$message = sprintf( '已更新 %d 处引用。', $changed );
		if ( $delete_originals ) {
			$message .= sprintf( '已删除 %d 个原图文件。', $deleted );
			if ( $skipped ) {
				$message .= sprintf( '为保护未选引用或替换失败的项目，保留了 %d 个原图文件。', $skipped );
			}
		} else {
			$message .= '原图仍保留在服务器中。';
		}
		return $message;
	}

	private function attachment_for_file( $file ) {
		$upload = wp_get_upload_dir();
		$relative = ltrim( substr( wp_normalize_path( $file ), strlen( wp_normalize_path( $upload['basedir'] ) ) ), '/' );
		$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_wp_attached_file', 'meta_value' => $relative, 'posts_per_page' => 1, 'fields' => 'ids' ) );
		if ( $existing ) {
			return (int) $existing[0];
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$type = wp_check_filetype( basename( $file ), null );
		$id = wp_insert_attachment( array( 'post_mime_type' => $type['type'] ?: 'image/webp', 'post_title' => sanitize_text_field( pathinfo( $file, PATHINFO_FILENAME ) ), 'post_status' => 'inherit' ), $file );
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		return (int) $id;
	}
}

new WSR_WebP_Sibling_Replacer();
