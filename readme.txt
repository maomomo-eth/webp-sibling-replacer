=== WebP 同目录替换器 ===
Contributors: codex
Tags: webp, image, media, optimize
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPL-2.0-or-later

扫描文章正文和特色图。如果原 PNG、JPG 或 JPEG 同目录下有同名 WebP 文件，即可在后台一键改用 WebP。

== 使用方法 ==

1. 将 `webp-sibling-replacer` 上传至 `/wp-content/plugins/` 并启用。
2. 打开“工具 → WebP 同目录替换器”。
3. 勾选需要处理的项目后点击“替换所选项目”。

可勾选“替换成功后，同时删除所选原图”。若同一个原图还有未勾选的扫描引用，插件会保留文件以避免断图。

工具页还会列出正文图片和特色图中的失效引用：上传目录本地文件缺失，以及外链图片返回 4xx/5xx 或请求失败的情况。

插件内置 GitHub Release 更新检查器。插件列表会提供“检查更新”，发现新版本后可直接使用 WordPress 原生的“立即更新”按钮安装。

== 注意事项 ==

* 仅处理 WordPress 上传目录中的本地图片。
* 支持文章和页面的正文 `img src` 与特色图。
* 特色图的 WebP 若尚未在媒体库登记，插件会登记该既有文件并生成附件元数据。
