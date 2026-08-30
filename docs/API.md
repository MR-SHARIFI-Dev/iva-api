# مرجع API

آدرس پایه: `https://example.com/iva-api/api.php`

تمام درخواست‌ها `GET` هستند. پارامتر `endpoint` اجباری و خروجی JSON است.

| Endpoint | پارامترها | کاربرد |
|---|---|---|
| `health` | — | سلامت و نسخه |
| `endpoints` | — | فهرست سرویس‌ها |
| `media` | `url` | Instagram، YouTube و GitHub |
| `wikipedia` | `q`, `limit` | جستجوی فارسی |
| `news/yjc` | `limit` | اخبار |
| `market/tgju` | `symbol` | طلا و ارز |
| `market/bonbast` | `currency` | نرخ ارز |
| `market/crypto` | `symbol` اختیاری | رمزارز |
| `finglish` | `text` | فینگلیش به فارسی |
| `text/reverse` | `text` | معکوس‌کردن متن |
| `national-id/validate` | `code` | اعتبارسنج کد ملی |
| `datetime` | `timezone` اختیاری | تاریخ و ساعت |
| `zekr`, `hadith`, `hafez` | — | محتوای فارسی |
| `corona` | `limit` اختیاری | داده هفتگی |
| `random/*` | — | محتوای تصادفی |

کدهای رایج HTTP: `200` موفق، `400/422` ورودی نامعتبر، `404` endpoint ناموجود،
`429` محدودیت درخواست و `502` خطای منبع خارجی.
