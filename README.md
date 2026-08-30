<div align="center" dir="rtl">

<img src="assets/banner.png" alt="بنر IVA API" width="100%">

# IVA API

### وب‌سرویس همه‌کاره، سبک و متن‌باز با PHP 8.1

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-MIT-22c55e.svg)](LICENSE)
[![API](https://img.shields.io/badge/API-JSON-0ea5e9)](docs/API.md)
[![Method](https://img.shields.io/badge/Method-GET-8b5cf6)](#روش-استفاده)
[![Deploy](https://img.shields.io/badge/Deploy-cPanel-f97316)](#نصب-روی-cpanel)
[![Framework](https://img.shields.io/badge/Framework-None-64748b)](#نیازمندیها)

بدون Composer، بدون Framework و بدون API Key اجباری؛ مناسب نصب مستقیم روی هاست اشتراکی و cPanel.

[شروع سریع](#شروع-سریع) · [فهرست سرویس‌ها](#فهرست-endpointها) · [مستندات API](docs/API.md) · [گزارش مشکل](../../issues)

</div>

---

## فهرست مطالب

- [معرفی](#معرفی)
- [قابلیت‌ها](#قابلیتها)
- [نیازمندی‌ها](#نیازمندیها)
- [شروع سریع](#شروع-سریع)
- [نصب روی cPanel](#نصب-روی-cpanel)
- [اجرای محلی](#اجرای-محلی)
- [روش استفاده](#روش-استفاده)
- [فهرست Endpointها](#فهرست-endpointها)
- [ساختار پاسخ](#ساختار-پاسخ)
- [کدهای خطا](#کدهای-خطا)
- [تنظیمات](#تنظیمات)
- [ساختار پروژه](#ساختار-پروژه)
- [امنیت و محدودیت‌ها](#امنیت-و-محدودیتها)
- [مشارکت و انتشار](#مشارکت-و-انتشار)
- [مجوز](#مجوز)

## معرفی

**IVA API** یک درگاه JSON یکپارچه برای سرویس‌های رسانه، محتوای فارسی، اطلاعات بازار و ابزارهای کاربردی است. هسته پروژه در یک فایل PHP نوشته شده و رابط HTML داخلی اجازه می‌دهد endpointها را بدون ابزار اضافه در مرورگر آزمایش کنید.

تمام درخواست‌ها با متد `GET` انجام می‌شوند و پاسخ‌ها ساختار JSON یکسان دارند. هیچ دیتابیس، Composer یا فریم‌ورکی برای راه‌اندازی اولیه لازم نیست.

> [!IMPORTANT]
> برخی endpointها اطلاعات را از providerهای عمومی دریافت می‌کنند. تغییر ساختار یا سیاست دسترسی منبع می‌تواند موقتاً روی همان endpoint اثر بگذارد.

## قابلیت‌ها

- دانلود رسانه از Instagram شامل ویدئو، Reels و پست چنداسلایدی
- پردازش لینک ویدئو و Shorts یوتیوب
- دریافت اطلاعات فایل، مخزن و Release گیت‌هاب
- جستجوی ویکی‌پدیای فارسی و دریافت اخبار YJC
- دریافت قیمت ارز، طلا، Bonbast و رمزارزها
- تبدیل فینگلیش، معکوس‌کردن متن UTF-8 و اعتبارسنجی کد ملی
- تاریخ و ساعت منطقه‌ای، ذکر روز و حدیث تصادفی
- فال حافظ همراه غزل، تعبیر فارسی و فایل صوتی
- بیو، سخن بزرگان، چیستان، دانستنی و جوک تصادفی
- رابط کاربری RTL و واکنش‌گرا برای آزمایش زنده API
- محدودسازی درخواست بر اساس IP
- CORS، timeout، اعتبارسنجی URL و مدیریت خطای یکپارچه
- GitHub Actions برای بررسی PHP 8.1، 8.2 و 8.3
- ساخت خودکار فایل Release هنگام ایجاد Tag

## نیازمندی‌ها

| مورد | وضعیت |
|---|---|
| PHP | نسخه 8.1 یا جدیدتر |
| cURL | الزامی |
| JSON | الزامی |
| DOM / libxml | الزامی برای سرویس‌های HTML |
| mbstring | پیشنهادی |
| وب‌سرور | Apache یا پیکربندی معادل Nginx |
| دیتابیس | نیاز نیست |
| Composer | نیاز نیست |

## شروع سریع

پس از نصب، ابتدا سلامت سرویس را بررسی کنید:

```bash
curl "https://example.com/iva-api/api.php?endpoint=health"
```

پاسخ مورد انتظار:

```json
{
  "ok": true,
  "endpoint": "health",
  "data": {
    "status": "ok",
    "version": "1.0.1-hafez-fix",
    "php": "8.1.x",
    "time": "2026-08-30T12:00:00+00:00"
  }
}
```

برای مشاهده رابط آزمایش، مسیر اصلی پروژه را باز کنید:

```text
https://example.com/iva-api/
```

## نصب روی cPanel

1. فایل Release پروژه را دانلود کنید.
2. در cPanel وارد **File Manager** شوید.
3. پوشه `public_html/iva-api` را بسازید.
4. فایل ZIP را داخل پوشه آپلود و Extract کنید.
5. مطمئن شوید `api.php` و `index.html` مستقیماً داخل پوشه `iva-api` هستند.
6. از بخش **MultiPHP Manager** نسخه PHP را روی 8.1 یا جدیدتر قرار دهید.
7. از بخش **Select PHP Version** افزونه‌های `curl`، `dom`، `json` و ترجیحاً `mbstring` را فعال کنید.
8. آدرس `/iva-api/api.php?endpoint=health` را آزمایش کنید.

اگر پروژه مستقیماً داخل `public_html` قرار گرفت، بخش `/iva-api` را از URLها حذف کنید.

## اجرای محلی

```bash
git clone https://github.com/USERNAME/iva-api.git
cd iva-api
php -S 127.0.0.1:8080
```

سپس باز کنید:

```text
http://127.0.0.1:8080/
```

بررسی نحو PHP:

```bash
php -l api.php
```

## روش استفاده

آدرس پایه:

```text
https://example.com/iva-api/api.php
```

پارامتر `endpoint` نوع سرویس را تعیین می‌کند:

```text
GET api.php?endpoint=ENDPOINT&parameter=value
```

نمونه دانلودر رسانه:

```bash
curl --get "https://example.com/iva-api/api.php" \
  --data-urlencode "endpoint=media" \
  --data-urlencode "url=https://youtu.be/_w9KUsfgGag"
```

نمونه فال حافظ:

```bash
curl "https://example.com/iva-api/api.php?endpoint=hafez"
```

نمونه جستجوی ویکی‌پدیا:

```bash
curl --get "https://example.com/iva-api/api.php" \
  --data-urlencode "endpoint=wikipedia" \
  --data-urlencode "q=تهران" \
  --data-urlencode "limit=5"
```

## فهرست Endpointها

### هسته و رسانه

| Endpoint | پارامتر | توضیح |
|---|---|---|
| `health` | — | وضعیت، نسخه PHP و زمان سرور |
| `endpoints` | — | فهرست ماشینی تمام سرویس‌ها |
| `media` | `url` | Instagram، YouTube و GitHub |

### اطلاعات و بازار

| Endpoint | پارامترها | توضیح |
|---|---|---|
| `wikipedia` | `q`, `limit` | جستجوی ویکی‌پدیای فارسی |
| `news/yjc` | `limit` | آخرین اخبار YJC |
| `market/tgju` | `symbol` | قیمت طلا، ارز و رمزارز |
| `market/bonbast` | `currency` | نرخ ارز Bonbast |
| `market/crypto` | `symbol` اختیاری | فهرست یا جستجوی رمزارز |
| `corona` | `limit` اختیاری | روند هفتگی عمومی کرونا |

### ابزارها و محتوای فارسی

| Endpoint | پارامترها | توضیح |
|---|---|---|
| `finglish` | `text` | تبدیل فینگلیش به فارسی |
| `text/reverse` | `text` | معکوس‌کردن متن UTF-8 |
| `national-id/validate` | `code` | اعتبارسنجی ساختار کد ملی |
| `datetime` | `timezone` اختیاری | تاریخ و ساعت منطقه زمانی |
| `zekr` | — | ذکر روز |
| `hadith` | — | حدیث تصادفی |
| `hafez` | — | غزل، تعبیر فارسی و صوت |
| `random/bio-fa` | — | بیو فارسی تصادفی |
| `random/bio-en` | — | بیو انگلیسی تصادفی |
| `random/quote` | — | سخن بزرگان |
| `random/riddle` | — | چیستان همراه پاسخ |
| `random/fact` | — | دانستنی تصادفی |
| `random/joke` | — | جوک تصادفی |

مرجع فشرده‌تر API در [docs/API.md](docs/API.md) موجود است.

## ساختار پاسخ

پاسخ موفق:

```json
{
  "ok": true,
  "endpoint": "hafez",
  "data": {
    "number": "غزل شماره 261 دیوان حافظ",
    "poem": ["..."],
    "interpretation": "...",
    "audio": "https://example.com/audio.mp3"
  }
}
```

پاسخ ناموفق:

```json
{
  "ok": false,
  "error": {
    "code": "INVALID_URL",
    "message": "یک لینک معتبر با https ارسال کنید."
  }
}
```

## کدهای خطا

| HTTP | نمونه کد | معنی |
|---:|---|---|
| `400` | `MISSING_PARAMETER` | پارامتر ضروری ارسال نشده است |
| `404` | `ENDPOINT_NOT_FOUND` | endpoint وجود ندارد |
| `405` | `METHOD_NOT_ALLOWED` | متدی غیر از GET ارسال شده است |
| `422` | `INVALID_URL` | ورودی یا URL معتبر نیست |
| `429` | `RATE_LIMITED` | محدودیت درخواست رد شده است |
| `500` | `CURL_MISSING` | تنظیمات سرور ناقص است |
| `502` | `UPSTREAM_ERROR` | منبع خارجی پاسخ معتبر نداده است |
| `502` | `UPSTREAM_PARSE_ERROR` | ساختار پاسخ provider تغییر کرده است |

## تنظیمات

### محدودیت درخواست

مقدار پیش‌فرض هر IP برابر ۳۰ درخواست در دقیقه است:

```php
const RATE_LIMIT_PER_MINUTE = 30;
```

### پارامتر provider اینستاگرام

مقدار عمومی provider دارای مقدار پیش‌فرض است و API Key خصوصی پروژه محسوب نمی‌شود. برای جایگزینی آن در Apache:

```apache
SetEnv IVA_INSTAGRAM_AUTH "new-public-provider-value"
```

### CORS

نسخه فعلی برای API عمومی از `Access-Control-Allow-Origin: *` استفاده می‌کند. برای سرویس خصوصی، مقدار آن را به دامنه فرانت‌اند خود محدود کنید.

## ساختار پروژه

```text
iva-api/
├── .github/
│   ├── ISSUE_TEMPLATE/
│   └── workflows/
├── assets/
│   ├── banner.png
│   └── logo.png
├── data/
├── docs/
│   └── API.md
├── api.php
├── index.html
├── .htaccess
├── README.md
├── CHANGELOG.md
├── CONTRIBUTING.md
├── SECURITY.md
├── SUPPORT.md
└── LICENSE
```

## امنیت و محدودیت‌ها

- ورودی `url` فقط دامنه‌های پشتیبانی‌شده و پروتکل HTTPS را می‌پذیرد.
- درخواست‌های خارجی دارای timeout و اعتبارسنجی پاسخ هستند.
- مسیر پوشه `data` با `.htaccess` از دسترسی مستقیم محافظت می‌شود.
- هیچ رمز، کوکی نشست یا API Key خصوصی را Commit نکنید.
- لینک‌های دانلود providerها معمولاً موقت هستند و نباید به‌عنوان لینک دائمی ذخیره شوند.
- endpoint اعتبارسنجی کد ملی فقط صحت الگوریتمی را بررسی می‌کند و مالکیت یا وجود واقعی شخص را تأیید نمی‌کند.
- داده‌های سلامت برای تصمیم‌گیری پزشکی یا درمانی مناسب نیستند.
- استفاده از دانلودر باید مطابق قوانین محل زندگی، شرایط سرویس مقصد و حقوق صاحب محتوا باشد.

برای گزارش خصوصی آسیب‌پذیری، دستورالعمل [SECURITY.md](SECURITY.md) را ببینید.

## مشارکت و انتشار

1. ریپو را Fork کنید.
2. یک Branch موضوعی بسازید.
3. تغییرات را با PHP 8.1 یا جدیدتر آزمایش کنید.
4. مستندات و نمونه خروجی را به‌روزرسانی کنید.
5. Pull Request باز کنید.

جزئیات در [CONTRIBUTING.md](CONTRIBUTING.md) آمده است.

برای ساخت Release خودکار:

```bash
git tag v1.0.1
git push origin v1.0.1
```

Workflow انتشار، فایل ZIP نسخه را ساخته و به GitHub Release متصل می‌کند.

## نقشه راه

- [ ] افزودن تست‌های یکپارچه برای endpointهای مستقل
- [ ] مستندات OpenAPI 3
- [ ] Cache اختیاری برای منابع خارجی
- [ ] پشتیبانی از تنظیمات محیطی بیشتر
- [ ] داشبورد وضعیت providerها

## سلب مسئولیت

IVA API مستقل است و هیچ وابستگی رسمی، حمایت یا تأییدی از طرف Instagram، YouTube، GitHub، Hafez.it یا سایر منابع خارجی ندارد. مسئولیت استفاده قانونی از داده‌ها و لینک‌های خروجی بر عهده استفاده‌کننده است.

## مجوز

کد پروژه تحت مجوز [MIT](LICENSE) منتشر می‌شود. مجوز محتوای گردآوری‌شده در پوشه `data` ممکن است مستقل باشد؛ پیش از استفاده تجاری، وضعیت حقوقی محتوای موردنظر را بررسی کنید.

---

<div align="center" dir="rtl">

ساخته‌شده برای توسعه‌دهندگان فارسی‌زبان — **IVA API**

[بازگشت به بالا](#iva-api)

</div>
