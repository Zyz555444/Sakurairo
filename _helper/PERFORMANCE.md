# Sakurairo 性能验证基线

本文件用于记录主题性能优化前后的对比方法与目标指标。

## 推荐工具

- **Lighthouse**（Chrome DevTools 或 CI）：衡量 LCP、INP、CLS、FCP、TBT
- **Query Monitor**（WordPress 插件）：衡量 PHP 查询次数、慢查询、钩子耗时
- **WebPageTest**：多地域、多网络条件下的真实加载

## 建议测试页面

| 页面 | 关注点 |
|------|--------|
| 首页 | LCP（封面/首卡）、PJAX 二次导航、CSS 合并缓存 |
| 单篇文章 | 评论列表查询数、`the_content` 过滤器、代码高亮按需加载 |
| 搜索页 | `WP_Query` 分页、结果数量 |
| 归档用户页 | `time_archive` transient 命中 |
| REST 实时搜索 | `/wp-json/sakura/v1/cache_search/json` 冷/热响应、`X-Index-Stale` 头 |

## 后台操作基准

1. **保存文章**：编辑一篇已发布文章并更新，观察保存耗时（优化前 `save_post` 会全表扫描归档数据）。
2. **归档缓存**：确认 `time_archive` transient 在保存后更新，TTL 为 `DAY_IN_SECONDS`。
3. **搜索索引**：保存文章后检查 `iro_search_index` option 是否增量更新；删除索引时触发 `iro_rebuild_search_index` cron。

## 目标指标（参考）

| 指标 | 优化方向 |
|------|----------|
| LCP | < 2.5s（良好） |
| INP | < 200ms |
| CLS | < 0.1 |
| 首页 DB 查询 | 尽量减少 N+1（Query Monitor） |
| 评论页 SQL | 作者等级应批量查询，非逐条 |
| 搜索 REST（热缓存） | < 200ms TTFB |

## 优化项清单（本次实现）

- 归档 `time_archive`：保存/删除时写入 transient，统一 TTL
- 搜索页：数据库分页，避免 `posts_per_page => -1`
- 实时搜索：增量索引 + cron 全量重建
- 评论：批量预取作者评论数与 comment meta
- 前端：FA/字体非阻塞、customizer CSS transient 缓存、CSS 合并磁盘缓存
- `_iro`：按页面类型精简配置
- 封面：`srcset`/LCP 首卡优化
- 说说：CSS 变量替代逐条内联 `<style>`
- PJAX：`pjax-perf.js` 链接预取与样式表去重

## 记录模板

```
日期：
环境：本地 / 生产
主题版本：

| 页面 | LCP | INP | CLS | DB 查询数 | 备注 |
|------|-----|-----|-----|-----------|------|
| 首页 |     |     |     |           |      |
| 文章 |     |     |     |           |      |
```
