<?php
/**
 * Plugin Name: WebP 同目录替换器
 * Description: 扫描文章正文和特色图；当同目录存在 WebP 文件时，可一键改用 WebP，并列出可人工删除的原 PNG/JPG 文件。
 * Version: 1.0.3
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
	const VERSION     = '1.0.3';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu() {
		add_management_page( 'WebP 同目录替换器', 'WebP 同目录替换器', 'edit_others_posts', self::SLUG, array( $this, 'render_page' ) );
	}

	public function render_page() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( '您没有权限使用此工具。' );
		}

		$items         = $this->scan();
		$missing_items = $this->scan_missing();
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
			$items         = $this->scan();
			$missing_items = $this->scan_missing();
		}
		?>
		<div class="wrap">
			<h1>WebP 同目录替换器</h1>
			<p>扫描已发布和草稿文章/页面的正文图片及特色图。仅当原图旁边存在同名 <code>.webp</code> 文件时才会列出替换项。</p>
			<?php if ( $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<?php if ( empty( $items ) ) : ?>
				<div class="notice notice-info"><p>没有找到可替换的图片。请确认 WebP 与原图同名、位于同一上传目录，且扩展名为 <code>.webp</code>。</p></div>
			<?php else : ?>
				<p>找到 <strong><?php echo esc_html( count( $items ) ); ?></strong> 个可替换引用。勾选需要处理的条目后再执行。</p>
				<form method="post">
					<?php wp_nonce_field( 'wsr_replace_webp' ); ?>
					<p><label><input type="checkbox" name="wsr_delete_originals" value="1"> 替换成功后，同时删除所选原图</label></p>
					<p class="description">删除不可恢复。若同一原图还有未勾选的扫描引用，插件会跳过删除以避免造成断图。</p>
					<p><button type="submit" class="button button-primary" name="wsr_replace" value="1">替换所选项目</button></p>
					<table class="widefat striped">
						<thead><tr><th><input type="checkbox" id="wsr-select-all" aria-label="全选"></th><th>文章</th><th>位置</th><th>原图</th><th>将改为</th><th>原文件提示</th></tr></thead>
					<tbody>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><input type="checkbox" class="wsr-item" name="wsr_items[]" value="<?php echo esc_attr( $this->item_key( $item ) ); ?>" aria-label="选择此项目"></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $item['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( $item['post_id'] ) ?: '(无标题)' ); ?></a></td>
							<td><?php echo esc_html( 'featured' === $item['kind'] ? '特色图' : '正文图片' ); ?></td>
							<td><code><?php echo esc_html( $item['original_url'] ); ?></code></td>
							<td><code><?php echo esc_html( $item['webp_url'] ); ?></code></td>
							<td>替换成功后可人工删除：<code><?php echo esc_html( $item['original_file'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</form>
				<script>document.getElementById('wsr-select-all').addEventListener('change',function(){document.querySelectorAll('.wsr-item').forEach(function(item){item.checked=this.checked;},this);});</script>
			<?php endif; ?>
			<h2>失效图片</h2>
			<?php if ( empty( $missing_items ) ) : ?>
				<p>未发现失效的正文图片或特色图。</p>
			<?php else : ?>
				<p>发现 <strong><?php echo esc_html( count( $missing_items ) ); ?></strong> 个失效图片引用。</p>
				<table class="widefat striped">
					<thead><tr><th>文章</th><th>位置</th><th>图片地址/文件</th><th>问题</th></tr></thead>
					<tbody>
					<?php foreach ( $missing_items as $item ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $item['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( $item['post_id'] ) ?: '(无标题)' ); ?></a></td>
							<td><?php echo esc_html( 'featured' === $item['kind'] ? '特色图' : '正文图片' ); ?></td>
							<td><code><?php echo esc_html( $item['source'] ); ?></code></td>
							<td><?php echo esc_html( $item['status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<p class="description">提示：WebP 替换仅处理 WordPress 上传目录中的本地 PNG、JPG、JPEG；失效扫描还会检查外链图片的 HTTP 状态。</p>
		</div>
		<?php
	}

	private function scan() {
		$posts = $this->scannable_posts();
		$items = array();
		foreach ( $posts as $post ) {
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
						$items[] = array(
							'post_id'       => $post->ID,
							'kind'          => 'featured',
							'original_url'  => wp_get_attachment_url( $thumbnail_id ),
							'webp_url'      => $this->file_to_upload_url( $webp ),
							'original_file' => $original,
							'webp_file'     => $webp,
							'thumbnail_id'  => $thumbnail_id,
						);
					}
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
					$items[] = array(
						'post_id' => $post->ID,
						'kind'    => 'featured',
						'source'  => $file ?: ( wp_get_attachment_url( $thumbnail_id ) ?: '媒体附件 #' . $thumbnail_id ),
						'status'  => '本地特色图文件不存在（404）',
					);
				}
			}
		}
		return $items;
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
