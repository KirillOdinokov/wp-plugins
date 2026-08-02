# Schema Odinokov

Лёгкий плагин, который добавляет JSON-LD разметку `Organization`, `LocalBusiness`, `Product` (WooCommerce) и `Article` (посты/страницы), а также `og:image`. Замена Yoast без наворотов.

## Возможности

- **Organization** — на всех страницах сайта. Поля: название, URL, описание, телефон, email, логотип (через медиа-загрузчик WP).
- **LocalBusiness** — отдельным блоком на всех страницах сайта. Поля: юридический адрес (5 полей), ценовой диапазон, часы работы по дням недели. Выводится, только если заполнен хотя бы один адресный компонент.
- **Product** — на страницах товаров WC. Бренд берётся из настроек Organization.
- **Article** — на страницах постов и страниц. Publisher использует данные Organization.
- **og:image** (1200×630) с приоритетом: главное фото товара → миниатюра записи → логотип Organization.
- Без обязательной зависимости от WooCommerce.

## Установка

1. Скопируйте папку `schema-odinokov` в `wp-content/plugins/`.
2. **Плагины → Установленные** → активируйте **Schema Odinokov**.
3. **Настройки → Schema Odinokov** → заполните данные.

## Структура страницы настроек

**Settings → Schema Odinokov**

### Organization

- **Название организации** — `Organization.name`
- **URL сайта** — `Organization.url`
- **Описание** — `Organization.description`
- **Телефон** — `Organization.contactPoint.telephone` (тип `customer support`)
- **Email** — `Organization.contactPoint.email`
- **Логотип** — `Organization.logo` (ImageObject), медиа-загрузчик WP

### LocalBusiness

- **Улица, дом** — `address.streetAddress`
- **Город** — `address.addressLocality`
- **Регион / область** — `address.addressRegion`
- **Почтовый индекс** — `address.postalCode`
- **Страна** — `address.addressCountry` (ISO 3166-1 alpha-2, например `RU`)
- **Ценовой диапазон** — `priceRange` (например `$$`)
- **Часы работы** — `openingHoursSpecification`. Чекбокс по каждому дню недели + поля `от/до` (HH:MM). Неотмеченные дни в схему не попадают.

Все поля опциональны, но заполненные данные Organization используются и в схемах `Product.brand`, `Article.publisher`, `LocalBusiness.telephone/email`.

## Проверка

- View Source → ищите `<script type="application/ld+json">` с `@type: Organization` и `@type: LocalBusiness`.
- Google Rich Results Test: https://search.google.com/test/rich-results
- Facebook Sharing Debugger: https://developers.facebook.com/tools/debug/

## Совместимость

- WordPress 5.6+
- PHP 7.2+
- WooCommerce 4.0+ (опционально)

## Расширение через фильтры

```php
// Дополнить Organization-схему
add_filter( 'schema_odinokov_organization_schema', function( $schema, $opt ) {
    $schema['sameAs'] = [ 'https://vk.com/yourbrand', 'https://t.me/yourbrand' ];
    return $schema;
}, 10, 2 );

// Добавить geo-координаты и areaServed в LocalBusiness
add_filter( 'schema_odinokov_local_business_schema', function( $schema, $opt ) {
    $schema['geo'] = [
        '@type'     => 'GeoCoordinates',
        'latitude'  => 55.7558,
        'longitude' => 37.6173,
    ];
    $schema['areaServed'] = [ '@type' => 'City', 'name' => 'Москва' ];
    return $schema;
}, 10, 2 );

// Добавить поле в Product-схему
add_filter( 'schema_odinokov_product_schema', function( $schema, $product ) {
    $schema['mpn'] = (string) $product->get_sku();
    return $schema;
}, 10, 2 );

// Кастомизировать Article-схему
add_filter( 'schema_odinokov_article_schema', function( $schema, $post ) {
    $schema['inLanguage'] = get_bloginfo( 'language' );
    return $schema;
}, 10, 2 );
```

## Структура

```
schema-odinokov/
├── schema-odinokov.php           # Главный файл плагина
├── includes/
│   ├── class-schema-odinokov.php # Логика JSON-LD и og:image
│   └── class-admin.php           # Страница настроек
├── assets/
│   ├── admin.js                  # Медиа-загрузчик для логотипа
│   └── admin.css
└── readme.md
```

## Лицензия

GPL-2.0-or-later
