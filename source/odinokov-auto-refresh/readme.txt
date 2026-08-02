=== Odinokov Auto Refresh ===
Contributors: odinokov
Tags: seo, freshness, modified date, woocommerce
Requires at least: 5.5
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Автоматически обновляет дату last modified у записей, страниц, товаров WooCommerce, категорий и меток.

== Description ==

Плагин периодически (по умолчанию раз в 2 недели) обновляет дату `post_modified` у контента, чтобы поисковые системы не считали сайт заброшенным.

Особенности:
* Батчевая обработка — не нагружает shared-хостинг.
* Прямые SQL-запросы через `$wpdb` — минимум overhead.
* Lock через transient — защита от параллельного запуска.
* Cursor-based пагинация — не «упирается» в тысячи записей.
* Минимальный интервал между прогонами.
* Джиттер даты — записи не получают одинаковый timestamp.
* Поддержка записей, страниц, товаров, вложений, категорий, меток, WooCommerce-таксономий.

Настройки доступны в разделе **Одиноков → Auto Refresh**.

== Changelog ==

= 1.0.0 =
* Первый релиз. Ребрендинг из Auto Fresh Date в Odinokov Auto Refresh.
