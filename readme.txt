=== WebP 同目录替换器 ===
Contributors: codex
Tags: webp, image, media, optimize
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later

扫描文章正文和特色图。如果原 PNG、JPG 或 JPEG 同目录下有同名 WebP 文件，即可在后台一键改用 WebP。

== 使用方法 ==

1. 将 `webp-sibling-replacer` 上传至 `/wp-content/plugins/` 并启用。
2. 打开“工具 → WebP 同目录替换器”。
3. 确认扫描结果后点击“确认替换全部”。

插件不删除原图。替换完成后，页面会提示哪些 PNG/JPG/JPEG 可在确认无误后人工删除。

插件会自动从 GitHub Releases 检查新版本，可直接使用 WordPress 后台的更新功能安装更新。

== 注意事项 ==

* 仅处理 WordPress 上传目录中的本地图片。
* 支持文章和页面的正文 `img src` 与特色图。
* 特色图的 WebP 若尚未在媒体库登记，插件会登记该既有文件并生成附件元数据。
