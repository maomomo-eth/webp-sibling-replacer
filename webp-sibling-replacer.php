<?php
/**
 * Plugin Name: WebP 同目录替换器
 * Description: 扫描文章正文和特色图；当同目录存在 WebP 文件时，可一键改用 WebP，并列出可人工删除的原 PNG/JPG 文件。
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Codex
 * License: GPL-2.0-or-later
 * Text Domain: webp-sibling-replacer
 * Update URI: https://github.com/maomomo-eth/webp-sibling-replacer
 */

defined( 'ABSPATH' ) || exit;

final class WSR_WebP_Sibling_Replacer {
	const SLUG        = 'webp-sibling-replacer';
	const VERSION     = '1.0.0';
	const REPOSITORY  = 'maomomo-eth/webp-sibling-replacer';
	const CACHE_KEY   = 'wsr_github_release';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
	}

	/** 让 WordPress 从 GitHub Release 读取更新信息。 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		$release = $this->github_release();
		if ( ! $release || empty( $release->version ) || version_compare( $release->version, self::VERSION, '<=' ) ) {
			return $transient;
		}
		$plugin = plugin_basename( __FILE__ );
		$transient->response[ $plugin ] = (object) array(
			'slug'         => self::SLUG,
			'plugin'       => $plugin,
			'new_version'  => $release->version,
			'url'          => 'https://github.com/' . self::REPOSITORY,
			'package'      => $release->package,
			'requires'     => '5.8',
			'requires_php' => '7.4',
		);
		return $transient;
	}

	/** 为“查看版本详情”弹窗提供更新说明。 */
	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$release = $this->github_release();
		if ( ! $release ) {
			return $result;
		}
		return (object) array(
			'name'          => 'WebP 同目录替换器',
			'slug'          => self::SLUG,
			'version'       => $release->version,
			'author'        => '<a href="https://github.com/maomomo-eth">maomomo.eth</a>',
			'homepage'      => 'https://github.com/' . self::REPOSITORY,
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'download_link' => $release->package,
			'sections'      => array(
				'description' => '扫描正文和特色图，并在存在同目录 WebP 时替换引用。',
				'changelog'   => wp_kses_post( $release->notes ),
			),
		);
	}

	private function github_release() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest',
			array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json' ) )
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( self::CACHE_KEY, null, HOUR_IN_SECONDS );
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $data->tag_name ) ) {
			set_site_transient( self::CACHE_KEY, null, HOUR_IN_SECONDS );
			return null;
		}
		$package = '';
		if ( ! empty( $data->assets ) ) {
			foreach ( $data->assets as $asset ) {
				if ( self::SLUG . '.zip' === $asset->name && ! empty( $asset->browser_download_url ) ) {
					$package = $asset->browser_download_url;
					break;
				}
			}
		}
		if ( ! $package ) {
			return null;
		}
		$release = (object) array(
			'version' => ltrim( $data->tag_name, "vV \t\n\r\0\x0B" ),
			'package' => esc_url_raw( $package ),
			'notes'   => ! empty( $data->body ) ? $data->body : '暂无更新说明。',
		);
		set_site_transient( self::CACHE_KEY, $release, 12 * HOUR_IN_SECONDS );
		return $release;
	}

	public function add_menu() {
		add_management_page( 'WebP 同目录替换器', 'WebP 同目录替换器', 'edit_others_posts', self::SLUG, array( $this, 'render_page' ) );
	}

	public function render_page() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( '您没有权限使用此工具。' );
		}

		$items = $this->scan();
		$notice = null;
		if ( isset( $_POST['wsr_replace'] ) ) {
			check_admin_referer( 'wsr_replace_webp' );
			$notice = $this->replace_all( $items );
			$items  = $this->scan();
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
				<p>找到 <strong><?php echo esc_html( count( $items ) ); ?></strong> 个可替换引用。替换会修改文章内容或特色图设置；原文件不会被删除。</p>
				<form method="post">
					<?php wp_nonce_field( 'wsr_replace_webp' ); ?>
					<p><button type="submit" class="button button-primary" name="wsr_replace" value="1">确认替换全部</button></p>
				</form>
				<table class="widefat striped">
					<thead><tr><th>文章</th><th>位置</th><th>原图</th><th>将改为</th><th>原文件提示</th></tr></thead>
					<tbody>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $item['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( $item['post_id'] ) ?: '(无标题)' ); ?></a></td>
							<td><?php echo esc_html( 'featured' === $item['kind'] ? '特色图' : '正文图片' ); ?></td>
							<td><code><?php echo esc_html( $item['original_url'] ); ?></code></td>
							<td><code><?php echo esc_html( $item['webp_url'] ); ?></code></td>
							<td>替换成功后可人工删除：<code><?php echo esc_html( $item['original_file'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<p class="description">提示：此工具只处理 WordPress 上传目录中的本地 PNG、JPG、JPEG；外链、GIF、SVG 与已是 WebP 的图片会被跳过。</p>
		</div>
		<?php
	}

	private function scan() {
		$posts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'all',
			)
		);
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
		$parts = wp_parse_url( $url );
		if ( empty( $parts['path'] ) || ! $this->is_supported_original( $parts['path'] ) ) {
			return false;
		}
		$upload = wp_get_upload_dir();
		$upload_host = wp_parse_url( $upload['baseurl'], PHP_URL_HOST );
		if ( ! empty( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( $upload_host ) ) {
			return false;
		}
		$base   = wp_parse_url( $upload['baseurl'], PHP_URL_PATH );
		if ( 0 !== strpos( rawurldecode( $parts['path'] ), $base . '/' ) ) {
			return false;
		}
		$relative = ltrim( substr( rawurldecode( $parts['path'] ), strlen( $base ) ), '/' );
		$original = wp_normalize_path( $upload['basedir'] . '/' . $relative );
		$webp     = preg_replace( '/\.(png|jpe?g)$/i', '.webp', $original );
		if ( ! $webp || ! is_file( $original ) || ! is_file( $webp ) ) {
			return false;
		}
		$webp_url = preg_replace( '/\.(png|jpe?g)(?=([?#]|$))/i', '.webp', $url );
		return array( 'original_url' => $url, 'webp_url' => $webp_url, 'original_file' => $original, 'webp_file' => $webp );
	}

	private function is_supported_original( $file ) {
		return (bool) preg_match( '/\.(png|jpe?g)$/i', (string) $file );
	}

	private function file_to_upload_url( $file ) {
		$upload = wp_get_upload_dir();
		$relative = ltrim( substr( wp_normalize_path( $file ), strlen( wp_normalize_path( $upload['basedir'] ) ) ), '/' );
		return $upload['baseurl'] . '/' . str_replace( ' ', '%20', $relative );
	}

	private function replace_all( $items ) {
		$content_updates = array();
		$featured_updates = array();
		foreach ( $items as $item ) {
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
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_content ) );
				$changed++;
			}
		}
		foreach ( $featured_updates as $post_id => $item ) {
			$webp_id = $this->attachment_for_file( $item['webp_file'] );
			if ( $webp_id && set_post_thumbnail( $post_id, $webp_id ) ) {
				$changed++;
			}
		}
		return sprintf( '已更新 %d 处引用。原图仍保留在服务器中，请确认前台显示正常后再人工删除。', $changed );
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
